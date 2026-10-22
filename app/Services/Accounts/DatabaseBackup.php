<?php

namespace App\Services\Accounts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackup
{
    /** @param list<string> $avatarPaths */
    public function create(array $avatarPaths): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $directory = config('user_accounts.backup_directory').'/'.now()->format('Ymd-His').'-'.Str::uuid();
        File::makeDirectory($directory, 0700, true);
        $path = $directory.'/database.'.($driver === 'sqlite' ? 'sqlite' : 'sql');

        if ($driver === 'sqlite') {
            if ($connection->transactionLevel() > 0) {
                throw new RuntimeException('SQLite backup requires a connection without an open transaction.');
            }

            $connection->getPdo()->exec('VACUUM INTO '.$connection->getPdo()->quote($path));
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $config = $connection->getConfig();
            $arguments = [
                config('user_accounts.mysql_dump_binary'),
                '--single-transaction', '--quick', '--hex-blob', '--routines', '--events', '--triggers',
                '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
                '--result-file='.$path,
            ];

            if (filled($config['unix_socket'] ?? null)) {
                $arguments[] = '--socket='.$config['unix_socket'];
            }

            $arguments[] = '--databases';
            $arguments[] = $connection->getDatabaseName();
            $process = new Process($arguments, base_path(), ['MYSQL_PWD' => $config['password'] ?? '']);
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Database backup failed. Check MYSQLDUMP_BINARY, database credentials and dump privileges. No accounts were deleted.');
            }
        } else {
            throw new RuntimeException('Account reset supports MySQL, MariaDB and SQLite only.');
        }

        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('Database backup is missing or empty. No accounts were deleted.');
        }

        chmod($path, 0600);

        foreach ($avatarPaths as $avatarPath) {
            if (Storage::disk('public')->exists($avatarPath)) {
                $target = $directory.'/'.$avatarPath;
                File::ensureDirectoryExists(dirname($target), 0700);
                $stream = Storage::disk('public')->readStream($avatarPath);

                if ($stream === false) {
                    throw new RuntimeException('An avatar could not be backed up. No accounts were deleted.');
                }

                try {
                    if (file_put_contents($target, $stream) === false) {
                        throw new RuntimeException('An avatar could not be backed up. No accounts were deleted.');
                    }
                } finally {
                    fclose($stream);
                }
            }
        }

        File::put($directory.'/manifest.json', json_encode([
            'database' => $connection->getDatabaseName(),
            'driver' => $driver,
            'created_at' => now()->toIso8601String(),
            'database_sha256' => hash_file('sha256', $path),
            'avatars' => $avatarPaths,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $directory;
    }
}
