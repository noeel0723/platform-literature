<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\Comment;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Discussion;
use App\Models\Like;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\ReadingLog;
use App\Models\ReadingProgress;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_settings_exposes_the_self_deletion_destination(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profiles.edit'))->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')->where('routes.destroy', route('profiles.destroy'))
            ->where('routes.home', route('home')));
    }

    public function test_guest_cannot_delete_an_account(): void
    {
        $user = User::factory()->create();

        $this->delete(route('profiles.destroy'), ['password' => 'password', 'confirmation' => true])
            ->assertRedirect(route('login'));

        $this->assertModelExists($user);
    }

    #[TestWith([''])]
    #[TestWith(['wrong-password'])]
    public function test_deletion_rejects_missing_or_incorrect_password(string $password): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('profiles.edit'))->delete(route('profiles.destroy'), [
            'password' => $password, 'confirmation' => true,
        ])->assertRedirect(route('profiles.edit'))->assertSessionHasErrorsIn('deleteAccount', 'password');

        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith([null])]
    #[TestWith([false])]
    public function test_deletion_requires_explicit_confirmation(?bool $confirmation): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson(route('profiles.destroy'), [
            'password' => 'password', 'confirmation' => $confirmation,
        ])->assertUnprocessable()->assertJsonValidationErrors('confirmation');

        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_unverified_accounts_can_delete_themselves_in_either_verification_mode(bool $enabled): void
    {
        config(['auth.verification.enabled' => $enabled]);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->delete(route('profiles.destroy'), ['password' => 'password', 'confirmation' => true])
            ->assertRedirect(route('home'));

        $this->assertModelMissing($user);
        $this->assertGuest();
    }

    public function test_deletion_removes_only_the_current_account_and_its_data_while_preserving_catalog(): void
    {
        Storage::fake('public');
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['avatar_path' => 'avatars/reader.png', 'banner_path' => 'profile-banners/reader.webp']);
        $other = User::factory()->create(['avatar_path' => 'avatars/other.png']);
        foreach (['avatars/reader.png', 'profile-banners/reader.webp', 'avatars/other.png', 'catalog/cover.png'] as $path) {
            Storage::disk('public')->put($path, 'image');
        }
        $literature = Literature::factory()->create();
        $author = Author::factory()->create();
        $literature->authors()->attach($author);
        $work = CanonicalWork::factory()->create();
        $mapping = LiteratureSourceMapping::factory()->create(['literature_id' => $literature->id, 'canonical_work_id' => $work->id]);
        $override = LiteratureMetadataOverride::factory()->create(['literature_id' => $literature->id, 'edited_by' => $user->id]);
        $reading = ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $literature->id]);
        $log = ReadingLog::factory()->create(['reading_list_id' => $reading->id]);
        $progress = ReadingProgress::factory()->create(['reading_list_id' => $reading->id]);
        $review = Review::factory()->create(['user_id' => $user->id, 'literature_id' => $literature->id]);
        $activity = Activity::factory()->create(['user_id' => $user->id, 'literature_id' => $literature->id, 'review_id' => $review->id]);
        $list = CustomList::factory()->create(['user_id' => $user->id]);
        $item = CustomListItem::factory()->create(['custom_list_id' => $list->id, 'canonical_work_id' => $work->id, 'literature_id' => $literature->id]);
        $user->favoriteLiteratures()->attach($literature, ['position' => 1]);
        $user->favoriteAuthors()->attach($author, ['position' => 1]);
        $user->following()->attach($other);
        $other->following()->attach($user);
        $other->blockedUsers()->attach($user);
        $otherReview = Review::factory()->create(['user_id' => $other->id, 'literature_id' => $literature->id]);
        $otherList = CustomList::factory()->create(['user_id' => $other->id]);
        Password::broker()->createToken($user);
        Password::broker()->createToken($other);
        DB::table('sessions')->insert([
            ['id' => 'owner-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'other-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->delete(route('profiles.destroy'), [
            'password' => 'password', 'confirmation' => true, 'user_id' => $other->id,
        ])->assertRedirect(route('home'));

        $this->assertGuest();
        foreach ([$user, $reading, $log, $progress, $review, $activity, $list, $item] as $model) {
            $this->assertModelMissing($model);
        }
        foreach ([$other, $otherReview, $otherList, $literature, $author, $work, $mapping, $override] as $model) {
            $this->assertModelExists($model);
        }
        $this->assertNull($override->fresh()->edited_by);
        foreach (['user_favorite_literatures', 'user_favorite_authors', 'follows', 'user_blocks'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $other->email]);
        Storage::disk('public')->assertMissing(['avatars/reader.png', 'profile-banners/reader.webp']);
        Storage::disk('public')->assertExists(['avatars/other.png', 'catalog/cover.png']);
    }

    public function test_deletion_cleans_polymorphic_references_and_cascaded_reply_reports(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);
        $discussion = Discussion::factory()->create(['user_id' => $user->id]);
        $otherDiscussion = Discussion::factory()->create(['user_id' => $other->id]);
        $commentInDeletedThread = Comment::factory()->create(['user_id' => $other->id, 'discussion_id' => $discussion->id]);
        $ownComment = Comment::factory()->create(['user_id' => $user->id, 'discussion_id' => $otherDiscussion->id]);
        $reply = Comment::factory()->create(['user_id' => $other->id, 'discussion_id' => $otherDiscussion->id, 'parent_id' => $ownComment->id]);
        $retainedComment = Comment::factory()->create(['user_id' => $other->id, 'discussion_id' => $otherDiscussion->id]);
        foreach ([$user, $review, $discussion, $ownComment, $reply, $commentInDeletedThread] as $target) {
            Report::factory()->create(['reporter_id' => $other->id, 'reportable_type' => $target::class, 'reportable_id' => $target->id]);
        }
        Report::factory()->create(['reporter_id' => $other->id, 'reportable_type' => 'review', 'reportable_id' => $review->id]);
        Report::factory()->create(['reporter_id' => $other->id, 'reportable_type' => 'discussion', 'reportable_id' => $discussion->id]);
        $retainedReport = Report::factory()->create(['reporter_id' => $other->id, 'reportable_type' => Comment::class, 'reportable_id' => $retainedComment->id]);
        foreach ([$review, $discussion] as $target) {
            Like::factory()->create(['user_id' => $other->id, 'likeable_type' => $target->getMorphClass(), 'likeable_id' => $target->id]);
        }
        $retainedLike = Like::factory()->create(['user_id' => $other->id, 'likeable_type' => 'discussion', 'likeable_id' => $otherDiscussion->id]);

        $this->actingAs($user)->delete(route('profiles.destroy'), ['password' => 'password', 'confirmation' => true])
            ->assertRedirect(route('home'));

        foreach ([$discussion, $review, $ownComment, $reply, $commentInDeletedThread] as $model) {
            $this->assertModelMissing($model);
        }
        foreach ([$otherDiscussion, $retainedComment, $retainedReport, $retainedLike] as $model) {
            $this->assertModelExists($model);
        }
        $this->assertDatabaseCount('likes', 1);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_database_failure_rolls_back_user_data_and_keeps_profile_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/reader.png', 'image');
        $user = User::factory()->create(['avatar_path' => 'avatars/reader.png']);
        $review = Review::factory()->create(['user_id' => $user->id]);
        $like = Like::factory()->create(['likeable_type' => 'review', 'likeable_id' => $review->id]);
        Password::broker()->createToken($user);
        Exceptions::fake([RuntimeException::class]);
        Event::listen('eloquent.deleting: '.User::class, function (): void {
            throw new RuntimeException('Deletion failed.');
        });

        $this->actingAs($user)->delete(route('profiles.destroy'), ['password' => 'password', 'confirmation' => true])
            ->assertInternalServerError();

        $this->assertAuthenticatedAs($user);
        $this->assertModelExists($user);
        $this->assertModelExists($review);
        $this->assertModelExists($like);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        Storage::disk('public')->assertExists('avatars/reader.png');
        Exceptions::assertReported(RuntimeException::class);
    }
}
