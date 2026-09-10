<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CanonicalWork;
use App\Models\LiteratureSourceMapping;
use App\Services\Literature\ResolveLiteratureMappingReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogReviewController extends Controller
{
    public function index(): View
    {
        $mappings = LiteratureSourceMapping::query()
            ->needsReview()
            ->with([
                'apiSource',
                'literature.authors',
                'canonicalWork.preferredLiterature.authors',
            ])
            ->latest()
            ->paginate(15);
        $candidateIds = $mappings->getCollection()
            ->flatMap(fn (LiteratureSourceMapping $mapping): array => $mapping->candidate_work_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $candidateWorks = CanonicalWork::query()
            ->with(['primaryAuthor', 'preferredLiterature.authors'])
            ->whereKey($candidateIds)
            ->get()
            ->keyBy('id');

        return view('admin.catalog-review.index', [
            'mappings' => $mappings,
            'candidateWorks' => $candidateWorks,
        ]);
    }

    public function update(
        Request $request,
        LiteratureSourceMapping $mapping,
        ResolveLiteratureMappingReview $resolver,
    ): RedirectResponse {
        $data = $request->validate([
            'action' => ['required', Rule::in(['merge', 'keep_separate'])],
            'canonical_work_id' => [
                Rule::requiredIf($request->input('action') === 'merge'),
                'nullable',
                'integer',
            ],
        ]);

        $resolver->resolve(
            $mapping,
            $data['action'],
            isset($data['canonical_work_id']) ? (int) $data['canonical_work_id'] : null,
        );

        return back()->with('success', 'The catalog match has been reviewed.');
    }
}
