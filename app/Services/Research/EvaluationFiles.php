<?php

namespace App\Services\Research;

use InvalidArgumentException;
use RuntimeException;

final class EvaluationFiles
{
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

    public static function json(string $path, array $data): void
    {
        if (file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n") === false) {
            throw new RuntimeException("Cannot write {$path}");
        }
    }

    public static function csv(string $path, array $columns, array $rows): void
    {
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException("Cannot write {$path}");
        }
        try {
            fputcsv($stream, $columns, ',', '"', '', "\n");
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn (string $column): mixed => $row[$column] ?? '', $columns), ',', '"', '', "\n");
            }
        } finally {
            fclose($stream);
        }
    }

    public static function newDirectory(string $path): string
    {
        if (file_exists($path) || ! mkdir($path, 0775, true)) {
            throw new InvalidArgumentException('Output must be a new directory; existing files are never overwritten.');
        }

        return realpath($path);
    }

    public static function fingerprints(): array
    {
        $paths = [
            'app/Services/Literature/SemanticLiteratureResolver.php',
            'app/Services/Literature/LiteratureIdentityNormalizer.php',
            'app/Services/Literature/CanonicalLiteratureProjector.php',
            'app/Services/Literature/CatalogSyncService.php',
            'app/Services/Literature/AuthorEntityResolver.php',
            'app/Services/Literature/AuthorNameNormalizer.php',
            'app/Models/CanonicalWork.php', 'app/Models/LiteratureSourceMapping.php',
            'app/Models/Literature.php', 'database/seeders/CatalogSeeder.php', 'composer.lock',
            ...glob(base_path('app/Services/Research/*.php')),
            ...glob(base_path('database/migrations/*.php')),
        ];
        $hashes = [];
        foreach ($paths as $path) {
            $absolute = str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', base_path()).'/') ? $path : base_path($path);
            $relative = substr(str_replace('\\', '/', $absolute), strlen(str_replace('\\', '/', base_path())) + 1);
            $hashes[$relative] = hash_file('sha256', $absolute);
        }
        ksort($hashes);

        return $hashes;
    }
}
