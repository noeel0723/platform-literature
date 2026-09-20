<?php

namespace App\Services\Reading;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\ReadingLog;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\Literature\CanonicalWorkIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReadingManager
{
    public function __construct(
        private readonly ActivityRecorder $activityRecorder,
        private readonly CanonicalWorkIdentity $canonicalIdentity,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(User $user, Literature $literature, array $data): ReadingList
    {
        $literature = $this->canonicalIdentity->representative($literature);
        $equivalentIds = $this->canonicalIdentity->equivalentLiteratureIds($literature);

        return DB::transaction(function () use ($user, $literature, $equivalentIds, $data): ReadingList {
            $readingLists = ReadingList::query()
                ->where('user_id', $user->id)
                ->whereIn('literature_id', $equivalentIds)
                ->lockForUpdate()
                ->get();

            if ($readingLists->count() > 1) {
                throw ValidationException::withMessages([
                    'status' => 'This work has conflicting legacy reading entries. Run the interaction audit and resolve them before updating.',
                ]);
            }

            $readingList = $readingLists->first() ?? new ReadingList(['user_id' => $user->id]);
            $readingList->literature()->associate($literature);

            $isNew = ! $readingList->exists;
            $previousStatus = $readingList->status;
            $previousCompletedDate = $readingList->completed_at?->toDateString();
            $isReread = (bool) ($data['reread'] ?? false);
            $status = $isReread ? 'reading' : $data['status'];
            $requestedCompletedAt = filled($data['completed_at'] ?? null)
                ? Carbon::createFromFormat('Y-m-d', (string) $data['completed_at'], config('app.timezone'))->startOfDay()
                : null;

            $readingList->status = $status;
            $readingList->reread_count ??= 0;

            if ($isReread) {
                $readingList->reread_count++;
                $readingList->started_at = now();
                $readingList->completed_at = null;
            } elseif ($status === 'want_to_read') {
                $readingList->started_at = null;
                $readingList->completed_at = null;
            } elseif ($status === 'reading' || $status === 'dnf') {
                $readingList->started_at ??= now();
                $readingList->completed_at = null;
            } elseif ($status === 'completed') {
                $readingList->started_at ??= $requestedCompletedAt ?? now();
                $readingList->completed_at = $requestedCompletedAt ?? $readingList->completed_at ?? now();
            }

            $readingList->save();

            $hasProgressInput = filled($data['progress_value'] ?? null)
                || filled($data['progress_total'] ?? null)
                || $isReread;

            if ($hasProgressInput) {
                $existingProgress = $readingList->progress;
                $currentValue = $isReread ? 0 : (int) ($data['progress_value'] ?? $existingProgress?->current_value ?? 0);
                $totalValue = filled($data['progress_total'] ?? null)
                    ? (int) $data['progress_total']
                    : $existingProgress?->total_value;
                $unit = (string) ($data['progress_unit'] ?? $existingProgress?->unit ?? 'page');

                if ($unit === 'percent') {
                    $totalValue = 100;
                }

                $readingList->progress()->updateOrCreate([], [
                    'current_value' => $currentValue,
                    'total_value' => $totalValue,
                    'unit' => $unit,
                ]);
            }

            $progress = $readingList->progress()->first();
            $note = filled($data['note'] ?? null) ? trim((string) $data['note']) : null;
            $statusChanged = ! $isNew && $previousStatus !== $status;
            $completionDateChanged = $status === 'completed'
                && $previousCompletedDate !== $readingList->completed_at?->toDateString();
            $forceCompletedActivity = $status === 'completed'
                && (bool) ($data['force_completed_activity'] ?? false);

            $eventType = match (true) {
                $isReread => 'reread',
                $status === 'completed' && ($isNew || $statusChanged || $completionDateChanged || $forceCompletedActivity) => 'completed',
                $isNew => 'added_to_readlist',
                $statusChanged && $status === 'reading' => 'started',
                $statusChanged && $status === 'completed' => 'completed',
                $statusChanged && $status === 'dnf' => 'dnf',
                $statusChanged => 'status_updated',
                $hasProgressInput => 'progress_updated',
                $note !== null => 'note_added',
                default => null,
            };

            if ($eventType !== null) {
                ReadingLog::query()->create([
                    'reading_list_id' => $readingList->id,
                    'event_type' => $eventType,
                    'status' => $status,
                    'progress_value' => $progress?->current_value,
                    'progress_total' => $progress?->total_value,
                    'progress_unit' => $progress?->unit,
                    'note' => $note,
                    'occurred_at' => $eventType === 'completed'
                        ? $readingList->completed_at ?? now()
                        : now(),
                ]);
            }

            $this->activityRecorder->recordReadingStatus(
                $user,
                $literature,
                $isNew ? null : $previousStatus,
                $status,
                $isReread,
                $data['activity_occurred_at'] ?? ($status === 'completed' ? $readingList->completed_at : null),
                $forceCompletedActivity,
            );

            return $readingList->load(['progress', 'logs']);
        });
    }
}
