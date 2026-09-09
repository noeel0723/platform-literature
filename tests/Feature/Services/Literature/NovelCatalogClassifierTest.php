<?php

namespace Tests\Feature\Services\Literature;

use App\Services\Literature\NovelCatalogClassifier;
use Tests\TestCase;

class NovelCatalogClassifierTest extends TestCase
{
    public function test_it_accepts_an_exact_novel_title_with_an_author_even_when_optional_metadata_is_missing(): void
    {
        $accepted = app(NovelCatalogClassifier::class)->accepts(
            title: 'The Silver Chair',
            categories: [],
            description: null,
            authors: ['C. S. Lewis'],
            publisher: null,
            identifier: null,
            query: 'The Silver Chair',
        );

        $this->assertTrue($accepted);
    }

    public function test_it_rejects_a_study_guide_even_when_the_source_labels_it_as_fiction(): void
    {
        $accepted = app(NovelCatalogClassifier::class)->accepts(
            title: 'The Chronicles of Narnia Study Guide and Workbook',
            categories: ['Fiction'],
            description: null,
            authors: ['Example Teacher'],
            publisher: 'Example Learning',
            identifier: '9780066238500',
            query: 'The Chronicles of Narnia',
        );

        $this->assertFalse($accepted);
    }

    public function test_it_rejects_non_novel_categories(): void
    {
        $classifier = app(NovelCatalogClassifier::class);

        $this->assertTrue($classifier->isExplicitlyExcluded(
            title: 'C. S. Lewis: A Life',
            categories: ['Biography & Autobiography'],
        ));
        $this->assertTrue($classifier->isExplicitlyExcluded(
            title: 'The Sandman',
            categories: ['Comics & Graphic Novels'],
        ));
    }

    public function test_it_does_not_accept_an_ambiguous_result_without_an_author(): void
    {
        $accepted = app(NovelCatalogClassifier::class)->accepts(
            title: 'A Story Without Categories',
            categories: [],
            description: null,
            authors: [],
            publisher: null,
            identifier: null,
            query: 'A Story',
        );

        $this->assertFalse($accepted);
    }
}
