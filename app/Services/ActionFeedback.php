<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CustomList;
use App\Models\Literature;
use App\Models\User;
use App\Services\Literature\CanonicalWorkIdentity;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

final class ActionFeedback
{
    private const LIFETIME_SECONDS = 90;

    public function __construct(private readonly CanonicalWorkIdentity $identity) {}

    /**
     * Snapshots stay on the server; clients receive only a short-lived, user-bound token.
     *
     * @param  array<string, int>  $context
     * @return array{result: mixed, feedback: array<string, mixed>}
     */
    public function run(User $user, string $kind, array $context, Closure $operation, string $message): array
    {
        if ($kind === 'reading') {
            $context['literature_id'] = (int) $this->identity->representative(Literature::query()->findOrFail($context['literature_id']))->id;
        }
        [$result, $before, $after] = DB::transaction(function () use ($user, $kind, $context, $operation): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($user, $kind, $context);
            $result = $operation();

            return [$result, $before, $this->snapshot($user, $kind, $context)];
        }, 3);

        $id = (string) Str::uuid();
        $feedback = ['id' => $id, 'message' => $message, 'scope' => $kind.':'.json_encode($context), 'undo' => null];

        $changed = $this->actionValues($before) !== $this->actionValues($after);
        if ($changed && ($kind !== 'favorites' || $this->favoritesCanBeRestored($before))) {
            $expires = now()->addSeconds(self::LIFETIME_SECONDS);
            try {
                $stored = Cache::put($this->key($id), [
                    'user_id' => $user->id,
                    'kind' => $kind,
                    'context' => $context,
                    'before' => $before,
                    'after' => $after,
                    'expires_at' => $expires->timestamp,
                ], $expires);
                if ($stored) {
                    $feedback['undo'] = ['url' => route('action-feedback.undo', $id), 'expires_at' => $expires->toIso8601String()];
                }
            } catch (Throwable $exception) {
                // Cache failure must not turn an already committed action into a failed submission.
                report($exception);
            }
        }

        return ['result' => $result, 'feedback' => $feedback];
    }

    /** @return array<string, mixed> */
    public function undo(User $user, string $token): array
    {
        try {
            $scope = DB::transaction(function () use ($user, $token): string {
                $currentUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless($currentUser->isActive() && $currentUser->hasVerifiedEmail(), 403);
                $record = Cache::get($this->key($token));
                abort_unless(is_array($record) && $record['user_id'] === $user->id, 404, 'This Undo is no longer available.');
                abort_if($record['expires_at'] <= now()->timestamp, 410, 'This Undo has expired.');
                $current = $this->snapshot($user, $record['kind'], $record['context']);
                abort_unless($current === $record['after'], 409, 'This item has changed since that action. Refresh before trying again.');
                abort_if($record['kind'] === 'favorites' && ! $this->favoritesCanBeRestored($record['before']), 409, 'The original favorites have changed. Please refresh the page.');
                $this->restore($user, $record['kind'], $record['context'], $record['before']);

                return $record['kind'].':'.json_encode($record['context']);
            }, 3);
        } catch (QueryException $exception) {
            report($exception);
            abort(409, 'The original data can no longer be restored. Please refresh the page.');
        }
        try {
            Cache::forget($this->key($token));
        } catch (Throwable $exception) {
            report($exception);
        }

        return ['id' => (string) Str::uuid(), 'message' => 'Action undone.', 'scope' => $scope, 'undo' => null];
    }

    /** @param array<string, int> $context */
    private function snapshot(User $user, string $kind, array $context): array
    {
        if ($kind === 'reading') {
            $literature = Literature::query()->findOrFail($context['literature_id']);
            $ids = $this->identity->equivalentLiteratureIds($literature)->sort()->values()->all();
            $reading = DB::table('reading_lists')
                ->where('user_id', $user->id)
                ->whereIn('literature_id', $ids)
                ->orderBy('id')->lockForUpdate()->get()
                ->map(fn ($row): array => (array) $row)->all();
            $readingIds = array_column($reading, 'id');

            return [
                'literature_ids' => $ids,
                'reading_lists' => $reading,
                'reading_progress' => $this->rows('reading_progress', 'reading_list_id', $readingIds),
                'reading_logs' => $this->rows('reading_logs', 'reading_list_id', $readingIds),
                'activities' => $this->readingActivities($user, $ids)->orderBy('id')->lockForUpdate()->get()->map(fn ($row): array => (array) $row)->all(),
            ];
        }
        if ($kind === 'list') {
            $list = CustomList::query()->whereKey($context['list_id'])->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $list);

            return ['custom_list_items' => $this->rows('custom_list_items', 'custom_list_id', [$list->id])];
        }
        abort_unless($kind === 'favorites', 400);

        return [
            'user_favorite_literatures' => $this->favoriteRows('user_favorite_literatures', $user),
            'user_favorite_authors' => $this->favoriteRows('user_favorite_authors', $user),
        ];
    }

    /** @param array<string, int> $context */
    private function restore(User $user, string $kind, array $context, array $before): void
    {
        if ($kind === 'reading') {
            $ids = $before['literature_ids'];
            DB::table('reading_lists')->where('user_id', $user->id)->whereIn('literature_id', $ids)->delete();
            $this->readingActivities($user, $ids)->delete();
            foreach (['reading_lists', 'reading_progress', 'reading_logs', 'activities'] as $table) {
                $this->insert($table, $before[$table]);
            }
        } elseif ($kind === 'list') {
            DB::table('custom_list_items')->where('custom_list_id', $context['list_id'])->delete();
            $this->insert('custom_list_items', $before['custom_list_items']);
        } else {
            foreach (['user_favorite_literatures', 'user_favorite_authors'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
                $this->insert($table, $before[$table]);
            }
        }
    }

    private function rows(string $table, string $foreignKey, array $ids): array
    {
        return DB::table($table)->whereIn($foreignKey, $ids)
            ->orderBy('id')->lockForUpdate()->get()
            ->map(fn ($row): array => (array) $row)->all();
    }

    private function favoriteRows(string $table, User $user): array
    {
        return DB::table($table)->where('user_id', $user->id)
            ->orderBy('position')->lockForUpdate()->get()
            ->map(fn ($row): array => (array) $row)->all();
    }

    private function readingActivities(User $user, array $ids): Builder
    {
        return DB::table('activities')->where('user_id', $user->id)
            ->whereIn('literature_id', $ids)
            ->whereIn('type', [Activity::TYPE_ADDED_TO_READLIST, Activity::TYPE_STARTED_READING, Activity::TYPE_COMPLETED]);
    }

    private function favoritesCanBeRestored(array $before): bool
    {
        $ids = array_column($before['user_favorite_literatures'], 'literature_id');
        $representatives = Literature::query()->whereKey($ids)->get()->map(fn (Literature $literature): int => (int) $this->identity->representative($literature)->id)->all();
        sort($ids);
        sort($representatives);

        return $ids === $representatives && count($ids) === count(array_unique($representatives));
    }

    private function actionValues(array $snapshot): array
    {
        foreach ($snapshot as $table => $rows) {
            if ($table !== 'literature_ids') {
                $snapshot[$table] = array_map(fn (array $row): array => array_diff_key($row, array_flip(['created_at', 'updated_at'])), $rows);
            }
        }

        return $snapshot;
    }

    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function key(string $token): string
    {
        return 'action-undo:'.$token;
    }
}
