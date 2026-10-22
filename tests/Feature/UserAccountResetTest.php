<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\CanonicalWorkLink;
use App\Models\Category;
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
use App\Services\Accounts\DatabaseBackup;
use App\Services\Accounts\UserAccountReset;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Tests\TestCase;

class UserAccountResetTest extends TestCase
{
    use DatabaseMigrations;

    private string $backupRoot;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();

        $this->beforeApplicationDestroyed(function (): void {
            // Discard the in-memory database; historical rollback migrations are not SQLite-compatible.
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Account reset tests must only run against in-memory SQLite.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('env', 'local');
        $this->backupRoot = sys_get_temp_dir().'/literahaven-reset-test-'.Str::uuid();
        config([
            'user_accounts.reset_enabled' => true,
            'user_accounts.backup_directory' => $this->backupRoot,
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
        ]);
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true);
    }

    protected function tearDown(): void
    {
        if (isset($this->backupRoot) && str_starts_with($this->backupRoot, sys_get_temp_dir().'/literahaven-reset-test-')) {
            File::deleteDirectory($this->backupRoot);
        }
        parent::tearDown();
    }

    public function test_reset_removes_all_personal_data_and_preserves_catalog_with_a_restorable_backup(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/reader.png', 'avatar-image');
        Storage::disk('public')->put('catalog/cover.png', 'catalog-image');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'avatar_path' => 'avatars/reader.png']);
        $reader = User::factory()->create(['deactivated_by' => $admin->id]);
        $literature = Literature::factory()->create();
        $author = Author::factory()->create();
        $category = Category::factory()->create();
        $literature->authors()->attach($author);
        $literature->categories()->attach($category);
        $work = CanonicalWork::factory()->create(['primary_author_id' => $author->id]);
        $mapping = LiteratureSourceMapping::factory()->create(['literature_id' => $literature->id, 'canonical_work_id' => $work->id]);
        CanonicalWorkLink::query()->create(['canonical_work_id' => $work->id, 'provider' => 'Publisher', 'url' => 'https://example.com/read', 'link_type' => 'read', 'source' => 'fixture']);
        DB::table('canonical_work_identifiers')->insert(['canonical_work_id' => $work->id, 'scheme' => 'isbn', 'value' => '9781234567890']);
        DB::table('author_aliases')->insert(['author_id' => $author->id, 'name' => 'Alias', 'normalized_name' => 'alias', 'source' => 'fixture']);
        $override = LiteratureMetadataOverride::factory()->create(['literature_id' => $literature->id, 'edited_by' => $admin->id, 'title' => 'Preserved title']);
        $reading = ReadingList::factory()->create(['user_id' => $reader->id, 'literature_id' => $literature->id]);
        ReadingLog::factory()->create(['reading_list_id' => $reading->id]);
        ReadingProgress::factory()->create(['reading_list_id' => $reading->id]);
        $review = Review::factory()->create(['user_id' => $reader->id, 'literature_id' => $literature->id]);
        $discussion = Discussion::factory()->create(['user_id' => $reader->id, 'literature_id' => $literature->id]);
        Comment::factory()->create(['user_id' => $admin->id, 'discussion_id' => $discussion->id]);
        Like::factory()->create(['user_id' => $admin->id, 'likeable_id' => $review->id]);
        Report::factory()->create(['reporter_id' => $admin->id, 'reportable_type' => 'review', 'reportable_id' => $review->id]);
        Activity::factory()->create(['user_id' => $reader->id, 'literature_id' => $literature->id, 'review_id' => $review->id]);
        $list = CustomList::factory()->create(['user_id' => $reader->id]);
        CustomListItem::factory()->create(['custom_list_id' => $list->id, 'canonical_work_id' => $work->id, 'literature_id' => $literature->id]);
        $reader->favoriteLiteratures()->attach($literature, ['position' => 1]);
        $reader->favoriteAuthors()->attach($author, ['position' => 1]);
        $reader->following()->attach($admin);
        $admin->blockedUsers()->attach($reader);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $reader->id, 'payload' => 'session-data', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $reader->email, 'token' => 'old-token']);
        DB::table('cache')->insert(['key' => 'catalog-api-cache', 'value' => 'metadata', 'expiration' => time() + 3600]);
        $catalog = collect(UserAccountReset::CATALOG_TABLES)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);
        $catalogRows = collect(UserAccountReset::CATALOG_TABLES)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all()]);
        $catalogRows['literature_metadata_overrides'] = array_map(fn (array $row): array => [...$row, 'edited_by' => null], $catalogRows['literature_metadata_overrides']);

        $this->artisan('accounts:reset')
            ->expectsQuestion('Type RESET ALL USER ACCOUNTS to continue', 'RESET ALL USER ACCOUNTS')
            ->assertSuccessful();

        foreach (UserAccountReset::PERSONAL_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $this->assertDatabaseCount($table, 0);
            }
        }
        foreach ($catalog as $table => $count) {
            $this->assertDatabaseCount($table, $count);
            $this->assertSame($catalogRows[$table], DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all());
        }
        $this->assertModelExists($mapping);
        $this->assertSame('Preserved title', $override->fresh()->title);
        $this->assertNull($override->fresh()->edited_by);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertDatabaseHas('cache', ['key' => 'catalog-api-cache', 'value' => 'metadata']);
        Storage::disk('public')->assertMissing('avatars/reader.png');
        Storage::disk('public')->assertExists('catalog/cover.png');
        $directories = File::directories($this->backupRoot);
        $this->assertCount(1, $directories);
        $this->assertFileExists($directories[0].'/avatars/reader.png');
        $backup = new PDO('sqlite:'.$directories[0].'/database.sqlite');
        $this->assertSame(2, (int) $backup->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame(1, (int) $backup->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
        $this->assertSame(1, (int) $backup->query('SELECT COUNT(*) FROM literatures')->fetchColumn());
        $backup = null;
    }

    public function test_production_reset_is_refused_even_when_enabled(): void
    {
        $this->app->instance('env', 'production');
        $reader = User::factory()->create();

        $this->artisan('accounts:reset')->assertFailed();

        $this->assertModelExists($reader);
        $this->assertDirectoryDoesNotExist($this->backupRoot);
    }

    public function test_disabled_or_non_interactive_reset_is_refused(): void
    {
        $reader = User::factory()->create();
        config(['user_accounts.reset_enabled' => false]);
        $this->artisan('accounts:reset')->assertFailed();
        config(['user_accounts.reset_enabled' => true]);

        $this->artisan('accounts:reset', ['--no-interaction' => true])->assertFailed();

        $this->assertModelExists($reader);
    }

    public function test_missing_maintenance_mode_or_wrong_confirmation_does_not_delete_data(): void
    {
        $reader = User::factory()->create();
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(false);
        $this->artisan('accounts:reset')->assertFailed();
        $this->mock(MaintenanceMode::class)->shouldReceive('active')->andReturn(true);

        $this->artisan('accounts:reset')->expectsQuestion('Type RESET ALL USER ACCOUNTS to continue', 'no')->assertFailed();

        $this->assertModelExists($reader);
        $this->assertDirectoryDoesNotExist($this->backupRoot);
    }

    public function test_dry_run_reports_counts_without_deletion_or_backup(): void
    {
        $reader = User::factory()->create();
        config(['user_accounts.reset_enabled' => false]);

        $this->artisan('accounts:reset', ['--dry-run' => true])->assertSuccessful();

        $this->assertModelExists($reader);
        $this->assertDirectoryDoesNotExist($this->backupRoot);
    }

    public function test_failed_backup_does_not_delete_data(): void
    {
        $reader = User::factory()->create();
        $this->mock(DatabaseBackup::class)->shouldReceive('create')->once()->andThrow(new RuntimeException('Backup unavailable'));

        $this->artisan('accounts:reset')->expectsQuestion('Type RESET ALL USER ACCOUNTS to continue', 'RESET ALL USER ACCOUNTS')->assertFailed();

        $this->assertModelExists($reader);
    }

    public function test_unknown_table_prevents_reset_until_its_ownership_is_reviewed(): void
    {
        $reader = User::factory()->create();
        Schema::create('new_personal_data', fn (Blueprint $table) => $table->id());

        try {
            $this->artisan('accounts:reset')->assertFailed();
            $this->assertModelExists($reader);
        } finally {
            Schema::dropIfExists('new_personal_data');
        }
    }

    public function test_pending_jobs_prevent_reset(): void
    {
        $reader = User::factory()->create();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'personal-data', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);

        $this->artisan('accounts:reset')->assertFailed();

        $this->assertModelExists($reader);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_unsupported_session_storage_prevents_reset(): void
    {
        $reader = User::factory()->create();
        config(['session.driver' => 'file']);

        $this->artisan('accounts:reset')->assertFailed();

        $this->assertModelExists($reader);
    }

    public function test_avatar_path_outside_avatar_directory_prevents_reset(): void
    {
        $reader = User::factory()->create(['avatar_path' => '../catalog/cover.png']);

        $this->artisan('accounts:reset')->assertFailed();

        $this->assertModelExists($reader);
    }

    public function test_optional_tokens_and_notifications_are_removed_without_orphans(): void
    {
        $reader = User::factory()->create();
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('token');
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->morphs('notifiable');
            $table->text('data');
        });
        DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => $reader->id, 'token' => 'token']);
        DB::table('notifications')->insert(['notifiable_type' => User::class, 'notifiable_id' => $reader->id, 'data' => 'private']);

        $this->artisan('accounts:reset')->expectsQuestion('Type RESET ALL USER ACCOUNTS to continue', 'RESET ALL USER ACCOUNTS')->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_unsafe_catalog_cascade_prevents_reset(): void
    {
        $reader = User::factory()->create();
        Schema::table('literatures', function (Blueprint $table): void {
            $table->foreignId('unsafe_user_id')->nullable()->constrained('users')->cascadeOnDelete();
        });
        $literature = Literature::factory()->create();
        DB::table('literatures')->where('id', $literature->id)->update(['unsafe_user_id' => $reader->id]);

        $this->artisan('accounts:reset')->assertFailed();

        $this->assertModelExists($reader);
        $this->assertModelExists($literature);
    }

    public function test_personal_table_trigger_prevents_unexpected_catalog_mutations(): void
    {
        $reader = User::factory()->create();
        $literature = Literature::factory()->create();
        DB::unprepared('CREATE TRIGGER unsafe_user_delete AFTER DELETE ON users BEGIN DELETE FROM literatures; END');

        $this->artisan('accounts:reset')->assertFailed();

        $this->assertModelExists($reader);
        $this->assertModelExists($literature);
    }
}
