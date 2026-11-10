<?php

namespace Tests\Unit\Research;

use App\Services\Literature\LiteratureIdentityNormalizer;
use App\Services\Research\ResolutionBaselines;
use App\Services\Research\ResolutionMetrics;
use PHPUnit\Framework\TestCase;

class ResolutionMetricsTest extends TestCase
{
    public function test_confusion_counts_and_metrics_are_computed_from_booleans(): void
    {
        $metrics = (new ResolutionMetrics)->calculate([
            ['actual' => true, 'predicted' => true], ['actual' => true, 'predicted' => true],
            ['actual' => false, 'predicted' => true], ['actual' => true, 'predicted' => false], ['actual' => false, 'predicted' => false],
        ]);
        $this->assertSame(2, $metrics['tp']);
        $this->assertSame(1, $metrics['fp']);
        $this->assertSame(1, $metrics['fn']);
        $this->assertSame(1, $metrics['tn']);
        $this->assertEqualsWithDelta(0.6, $metrics['accuracy'], 1e-10);
        foreach (['precision', 'recall', 'f1'] as $key) {
            $this->assertEqualsWithDelta(2 / 3, $metrics[$key], 1e-10);
        }
        $this->assertEqualsWithDelta(1 / 3, $metrics['false_merge_rate'], 1e-10);
    }

    public function test_zero_denominators_are_null_instead_of_fabricated_scores(): void
    {
        $empty = (new ResolutionMetrics)->calculate([]);
        foreach (['accuracy', 'precision', 'recall', 'f1', 'false_merge_rate'] as $key) {
            $this->assertNull($empty[$key]);
        }
    }

    public function test_baselines_use_title_normalization_and_frozen_primary_author_without_work_mapping(): void
    {
        $baselines = new ResolutionBaselines(new LiteratureIdentityNormalizer);
        $a = ['attributes' => ['title' => '  ÉTÉ: A Tale! '], 'authors' => [['id' => 1]]];
        $b = ['attributes' => ['title' => 'été a tale'], 'authors' => [['id' => 1]]];
        $this->assertSame(['exact_title' => true, 'exact_title_author' => true], $baselines->predict($a, $b));
        $b['authors'][0]['id'] = 2;
        $this->assertSame(['exact_title' => true, 'exact_title_author' => false], $baselines->predict($a, $b));
        $b['authors'] = [];
        $this->assertFalse($baselines->predict($a, $b)['exact_title_author']);
        $b['attributes']['title'] = 'Other title';
        $this->assertFalse($baselines->predict($a, $b)['exact_title']);
    }
}
