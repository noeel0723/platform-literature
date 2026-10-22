<?php

namespace App\Console\Commands;

use App\Services\Accounts\DatabaseBackup;
use App\Services\Accounts\UserAccountReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResetUserAccounts extends Command
{
    protected $signature = 'accounts:reset {--dry-run : Inspect user data without backing up or deleting anything}';

    protected $description = 'Manually back up and reset all accounts in local/development only, preserving the global catalog';

    public function handle(UserAccountReset $reset, DatabaseBackup $backup): int
    {
        if (! app()->environment(['local', 'development'])) {
            $this->error('Account reset is only available in local/development. Production cannot be overridden.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && (! config('user_accounts.reset_enabled') || ! $this->input->isInteractive())) {
            $this->error('Enable USER_ACCOUNT_RESET_ENABLED and run interactively. There is no --force or --yes bypass.');

            return self::FAILURE;
        }

        $lock = fopen(storage_path('framework/account-reset.lock'), 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->error('Another account reset is running.');

            return self::FAILURE;
        }

        try {
            $counts = $reset->inspect();
            $avatars = $reset->avatarPaths();
            $this->info('Database: '.DB::connection()->getDatabaseName());
            $this->table(['Personal table', 'Rows to remove'], collect($counts)->map(fn (int $count, string $table): array => [$table, $count])->values()->all());

            if ($this->option('dry-run')) {
                $this->info('Dry run only. No database rows or user files were changed.');

                return self::SUCCESS;
            }

            if (! app()->isDownForMaintenance()) {
                $this->error('Run php artisan down and stop queue workers and scheduled imports before resetting.');

                return self::FAILURE;
            }

            $this->warn('This deletes ALL accounts including admins and ALL personal interactions. The global catalog is preserved.');

            if ($this->ask('Type RESET ALL USER ACCOUNTS to continue') !== 'RESET ALL USER ACCOUNTS') {
                $this->warn('Reset cancelled. No data was deleted.');

                return self::FAILURE;
            }

            $directory = $backup->create($avatars);
            $this->info('Backup completed: '.$directory);
            $reset->delete(array_keys($counts));

            if (! $reset->removeAvatars($avatars)) {
                $this->error('Accounts reset, but some avatar files could not be removed. Remove only the avatar paths in the backup manifest.');

                return self::FAILURE;
            }

            $this->info('All accounts and personal data removed. Catalog preserved. Backup retained privately.');
            $this->info('Run php artisan accounts:create-admin, disable USER_ACCOUNT_RESET_ENABLED, then php artisan up.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
