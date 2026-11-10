<?php

namespace App\Services\Research;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

final class StudyFiles
{
    public static function localOnly(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            throw new RuntimeException('Research tools are restricted to local/development/testing.');
        }
    }

    public static function directory(string $area, string $name, bool $create = false): string
    {
        self::localOnly();
        if (! in_array($area, ['exports', 'outputs'], true) || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Invalid study or run name. Paths are not accepted.');
        }
        $root = config('research.root', base_path('research/evaluation'));
        $path = $root.'/'.$area.'/'.$name;
        for ($check = $path; strlen($check) > 3; $check = dirname($check)) {
            if (is_link($check)) {
                throw new InvalidArgumentException('Research directories must not contain symlinks.');
            }
            if (dirname($check) === $check) {
                break;
            }
        }
        if ($create) {
            self::newDirectory($path);
        } elseif (! is_dir($path)) {
            throw new InvalidArgumentException('Study directory not found. Export a fresh dataset first.');
        }

        return $path;
    }

    public static function key(string $source, string $externalId): string
    {
        return json_encode([$source, $externalId], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public static function pairId(string $a, string $b): string
    {
        $keys = [$a, $b];
        sort($keys, SORT_STRING);

        return 'pair-'.substr(hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR)), 0, 24);
    }

    public static function atomic(string $path, string $contents): void
    {
        if (is_link($path)) {
            throw new InvalidArgumentException('Symlink files are forbidden.');
        }
        $temporary = tempnam(dirname($path), '.study-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create temporary research file.');
        }
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot commit research file atomically.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function json(string $path, array $data): void
    {
        self::atomic($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    public static function readJson(string $path): array
    {
        if (is_link($path) || ! is_file($path)) {
            throw new InvalidArgumentException('Missing or unsafe research file.');
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function csvText(array $columns, array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        try {
            fputcsv($stream, $columns, ',', '"', '', "\n");
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn (string $column): mixed => $row[$column] ?? '', $columns), ',', '"', '', "\n");
            }
            rewind($stream);

            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    public static function csv(string $path, array $columns, array $rows): void
    {
        self::atomic($path, self::csvText($columns, $rows));
    }

    public static function readCsv(string $path): array
    {
        if (is_link($path) || ! is_file($path)) {
            throw new InvalidArgumentException('Missing or unsafe CSV.');
        }
        $stream = fopen($path, 'rb');
        try {
            $columns = fgetcsv($stream, null, ',', '"', '');
            if (! is_array($columns) || count(array_unique($columns)) !== count($columns)) {
                throw new InvalidArgumentException('CSV header is invalid.');
            }
            $rows = [];
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if (count($values) !== count($columns)) {
                    throw new InvalidArgumentException('Malformed CSV row.');
                }
                $rows[] = array_combine($columns, array_map(fn ($v): string => (string) $v, $values));
            }

            return ['columns' => $columns, 'rows' => $rows];
        } finally {
            fclose($stream);
        }
    }

    public static function newDirectory(string $path): string
    {
        if (file_exists($path) || ! mkdir($path, 0775, true)) {
            throw new InvalidArgumentException('Output must be a new directory; existing data is never overwritten.');
        }

        return realpath($path);
    }

    public static function fingerprints(): array
    {
        $paths = [
            ...glob(base_path('app/Services/Literature/*.php')),
            ...glob(base_path('app/Models/Canonical*.php')),
            base_path('app/Models/LiteratureSourceMapping.php'), base_path('app/Models/Literature.php'),
            base_path('composer.lock'), ...glob(base_path('app/Services/Research/*.php')),
            ...glob(base_path('database/migrations/*.php')),
        ];
        $hashes = [];
        foreach ($paths as $path) {
            $relative = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', base_path())) + 1);
            $hashes[$relative] = hash_file('sha256', $path);
        }
        ksort($hashes);

        return $hashes;
    }

    public static function gitState(): array
    {
        $sha = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $sha->run();
        $status = new Process(['git', 'status', '--porcelain'], base_path());
        $status->run();

        return ['commit_sha' => trim($sha->getOutput()), 'working_tree_dirty' => trim($status->getOutput()) !== ''];
    }
}
