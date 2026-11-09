<?php

namespace Tests\Unit\Research;

use App\Services\Literature\LiteratureIdentityNormalizer;
use App\Services\Research\ResolutionBaselines;
use App\Services\Research\ResolutionMetrics;
use PHPUnit\Framework\TestCase;

class ResolutionMetricsTest extends TestCase
{
    public function test_confusion_matrix_and_rates_use_the_documented_denominators(): void
    {
        $result = (new ResolutionMetrics)->calculate([
            ['actual' => true, 'predicted' => true],
            ['actual' => true, 'predicted' => true],
            ['actual' => false, 'predicted' => true],
            ['actual' => true, 'predicted' => false],
            ['actual' => false, 'predicted' => false],
            ['actual' => false, 'predicted' => false],
        ]);
        $this->assertSame(2, $result['tp']);
        $this->assertSame(1, $result['fp']);
        $this->assertSame(1, $result['fn']);
        $this->assertSame(2, $result['tn']);
        $this->assertSame(6, $result['n']);
        foreach (['accuracy', 'precision', 'recall', 'f1'] as $metric) {
            $this->assertEqualsWithDelta(2 / 3, $result[$metric], 0.000001);
        }
        $this->assertEqualsWithDelta(1 / 3, $result['false_merge_rate'], 0.000001);
    }

    public function test_empty_dataset_has_undefined_rates_instead_of_fabricated_scores(): void
    {
        $result = (new ResolutionMetrics)->calculate([]);
        $this->assertSame(0, $result['n']);
        foreach (['accuracy', 'precision', 'recall', 'f1', 'false_merge_rate'] as $metric) {
            $this->assertNull($result[$metric]);
        }
    }

    public function test_all_negative_and_all_missed_positive_cases_handle_zero_denominators(): void
    {
        $metrics = new ResolutionMetrics;
        $negative = $metrics->calculate([['actual' => false, 'predicted' => false]]);
        $this->assertSame(1.0, $negative['accuracy']);
        $this->assertNull($negative['precision']);
        $this->assertNull($negative['recall']);
        $this->assertNull($negative['f1']);
        $missed = $metrics->calculate([['actual' => true, 'predicted' => false]]);
        $this->assertNull($missed['precision']);
        $this->assertSame(0.0, $missed['recall']);
        $this->assertSame(0.0, $missed['f1']);
    }

    public function test_baselines_are_exact_and_do_not_infer_missing_or_same_named_author_identities(): void
    {
        $baselines = new ResolutionBaselines(new LiteratureIdentityNormalizer);
        $a = ['attributes' => ['title' => 'The Silver-Chair'], 'authors' => [['id' => 1, 'name' => 'C. S. Lewis']]];
        $b = ['attributes' => ['title' => ' THE SILVER CHAIR '], 'authors' => [['id' => 1, 'name' => 'CS Lewis']]];
        $this->assertSame(['exact_title' => true, 'exact_title_author' => true], $baselines->predict($a, $b));
        $b['authors'][0]['id'] = 2;
        $this->assertSame(['exact_title' => true, 'exact_title_author' => false], $baselines->predict($a, $b));
        $a['authors'] = [];
        $b['authors'] = [];
        $this->assertSame(['exact_title' => true, 'exact_title_author' => false], $baselines->predict($a, $b));
        $b['attributes']['title'] = 'The Silver Chair Companion';
        $this->assertSame(['exact_title' => false, 'exact_title_author' => false], $baselines->predict($a, $b));
        $a['attributes']['title'] = '';
        $b['attributes']['title'] = '';
        $this->assertFalse($baselines->predict($a, $b)['exact_title']);
    }
}
