<?php

namespace App\Services\Accounts;

use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Like;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

class DeleteUserAccount
{
    public function delete(User $account): void
    {
        $paths = DB::transaction(function () use ($account): array {
            $user = User::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $paths = array_filter([$user->avatar_path, $user->banner_path]);

            foreach ([Review::class => 'reviews', Discussion::class => 'discussions'] as $class => $relation) {
                $types = [(new $class)->getMorphClass(), $class];
                Like::query()->whereIn('likeable_type', $types)
                    ->whereIn('likeable_id', $user->{$relation}()->select('id'))->delete();
                Report::query()->whereIn('reportable_type', $types)
                    ->whereIn('reportable_id', $user->{$relation}()->select('id'))->delete();
            }

            // Foreign-key cascades also remove replies written by other readers.
            $commentIds = Comment::query()->where('user_id', $user->id)
                ->orWhereIn('discussion_id', $user->discussions()->select('id'))->pluck('id')->all();
            while ($commentIds !== []) {
                $replies = [];
                foreach (array_chunk($commentIds, 500) as $ids) {
                    Report::query()->whereIn('reportable_type', [Comment::class, 'comment'])
                        ->whereIn('reportable_id', $ids)->delete();
                    array_push($replies, ...Comment::query()->whereIn('parent_id', $ids)->pluck('id')->all());
                }
                $commentIds = $replies;
            }
            Report::query()->whereIn('reportable_type', [User::class, 'user'])
                ->where('reportable_id', $user->id)->delete();

            Password::broker()->deleteToken($user);
            $connection = $user->getConnection();
            foreach (['notifications' => 'notifiable', 'personal_access_tokens' => 'tokenable'] as $table => $morph) {
                if ($connection->getSchemaBuilder()->hasTable($table)) {
                    $connection->table($table)->whereIn($morph.'_type', [User::class, 'user'])
                        ->where($morph.'_id', $user->id)->delete();
                }
            }
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)->delete();
            }

            $user->delete();

            return $paths;
        }, 3);

        foreach ($paths as $path) {
            if (preg_match('#\A(?:avatars|profile-banners)/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z#', $path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
