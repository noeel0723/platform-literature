<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ModerationController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

        if (! array_key_exists($status, Report::STATUS_LABELS)) {
            $status = 'pending';
        }

        $reports = Report::query()
            ->with(['reporter', 'resolver', 'reportable'])
            ->where('status', $status)
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $statusCounts = Report::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.moderation.index', [
            'reports' => $reports,
            'status' => $status,
            'statusCounts' => $statusCounts,
            'statusLabels' => Report::STATUS_LABELS,
            'reasonLabels' => Report::REASON_LABELS,
        ]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'hide', 'deactivate'])],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($report->status !== 'pending') {
            throw ValidationException::withMessages([
                'action' => 'This report has already been reviewed.',
            ]);
        }

        DB::transaction(function () use ($data, $report, $request): void {
            $target = $report->reportable;

            if ($data['action'] === 'hide') {
                if (! $target instanceof Review && ! $target instanceof Discussion && ! $target instanceof Comment) {
                    throw ValidationException::withMessages([
                        'action' => 'Only reviews, discussions, and comments can be hidden.',
                    ]);
                }

                $target->update([
                    'hidden_at' => now(),
                    'hidden_by' => $request->user()->id,
                ]);
            }

            if ($data['action'] === 'deactivate') {
                if (! $target instanceof User) {
                    throw ValidationException::withMessages([
                        'action' => 'Only a reported user account can be deactivated.',
                    ]);
                }

                if ($target->is($request->user()) || $target->isAdmin()) {
                    throw ValidationException::withMessages([
                        'action' => 'Administrators cannot deactivate themselves or another administrator here.',
                    ]);
                }

                $target->update([
                    'deactivated_at' => now(),
                    'deactivated_by' => $request->user()->id,
                ]);
            }

            $report->update([
                'status' => $data['action'] === 'dismiss' ? 'dismissed' : 'resolved',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'resolution_note' => filled($data['resolution_note'] ?? null)
                    ? trim($data['resolution_note'])
                    : null,
            ]);
        });

        return back()->with('success', 'The moderation report has been updated.');
    }
}
