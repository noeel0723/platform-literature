<?php

namespace App\Services\Research;

use InvalidArgumentException;

final class ResolutionMetrics
{
    /** @param list<array{actual: bool, predicted: bool}> $observations */
    public function calculate(array $observations): array
    {
        $counts = ['tp' => 0, 'fp' => 0, 'fn' => 0, 'tn' => 0];
        foreach ($observations as $observation) {
            if (! is_bool($observation['actual']) || ! is_bool($observation['predicted'])) {
                throw new InvalidArgumentException('Metrics require boolean observations.');
            }
            $key = $observation['actual']
                ? ($observation['predicted'] ? 'tp' : 'fn')
                : ($observation['predicted'] ? 'fp' : 'tn');
            $counts[$key]++;
        }
        ['tp' => $tp, 'fp' => $fp, 'fn' => $fn, 'tn' => $tn] = $counts;
        $divide = fn (int $numerator, int $denominator): ?float => $denominator === 0 ? null : $numerator / $denominator;

        return $counts + [
            'n' => count($observations),
            'accuracy' => $divide($tp + $tn, $tp + $fp + $fn + $tn),
            'precision' => $divide($tp, $tp + $fp),
            'recall' => $divide($tp, $tp + $fn),
            'f1' => $divide(2 * $tp, 2 * $tp + $fp + $fn),
            'false_merge_rate' => $divide($fp, $tp + $fp),
        ];
    }
}
