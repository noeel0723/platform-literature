<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModerationController extends Controller
{
    public function index(Request $request): Response
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
        $reports->getCollection()->loadMorph('reportable', [
            Review::class => [
                'user',
                'literature.metadataOverride',
                'literature.sourceMapping.canonicalWork.metadataOverride',
            ],
            Discussion::class => [
                'user',
                'literature.metadataOverride',
                'literature.sourceMapping.canonicalWork.metadataOverride',
            ],
            Comment::class => [
                'user',
                'discussion.literature.metadataOverride',
                'discussion.literature.sourceMapping.canonicalWork.metadataOverride',
            ],
        ]);
        $reports->through(fn (Report $report): array => $this->presentReport($report));

        $statusCounts = Report::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return Inertia::render('Admin/Moderation/Index', [
            'reports' => $reports,
            'status' => $status,
            'statusCounts' => collect(Report::STATUS_LABELS)
                ->mapWithKeys(fn (string $label, string $value): array => [$value => (int) ($statusCounts[$value] ?? 0)])
                ->all(),
            'statusLabels' => Report::STATUS_LABELS,
            'reasonLabels' => Report::REASON_LABELS,
            'routes' => [
                'index' => route('admin.moderation.index'),
            ],
            'viewer' => [
                'name' => $request->user()->name,
                'username' => $request->user()->username,
            ],
            'successMessage' => $request->session()->get('success'),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentReport(Report $report): array
    {
        $target = $report->reportable;
        $targetOwner = match (true) {
            $target instanceof User => $target,
            $target instanceof Review, $target instanceof Discussion, $target instanceof Comment => $target->user,
            default => null,
        };

        $targetSummary = match (true) {
            $target instanceof User => $target->name.' (@'.$target->username.')',
            $target instanceof Review => filled($target->body)
                ? $target->body
                : 'Rating: '.number_format((float) $target->rating, 1).' / 5',
            $target instanceof Discussion => $target->title.' - '.$target->body,
            $target instanceof Comment => $target->body,
            default => 'The reported item is no longer available.',
        };

        $targetUrl = match (true) {
            $target instanceof User => route('profiles.show', $target),
            $target instanceof Review => route('literatures.show', $target->literature).'#review-'.$target->id,
            $target instanceof Discussion => route('literatures.show', $target->literature).'#discussion-'.$target->id,
            $target instanceof Comment => route('literatures.show', $target->discussion->literature).'#discussion-'.$target->discussion_id,
            default => null,
        };

        $targetTitle = match (true) {
            $target instanceof User => 'Profile "'.$target->name.'"',
            $target instanceof Review => 'Review on "'.$target->literature->displayTitle().'"',
            $target instanceof Discussion => 'Discussion "'.$target->title.'"',
            $target instanceof Comment => 'Comment on "'.$target->discussion->title.'"',
            default => 'Reported item unavailable',
        };

        $targetContext = match (true) {
            $target instanceof User => '@'.$target->username,
            $target instanceof Review => $target->literature->displayTitle(),
            $target instanceof Discussion => $target->literature->displayTitle(),
            $target instanceof Comment => $target->discussion->literature->displayTitle(),
            default => null,
        };

        $availableActions = [];
        $targetState = null;

        if ($report->status === 'pending') {
            if ($target instanceof Review || $target instanceof Discussion || $target instanceof Comment) {
                if ($target->hidden_at === null) {
                    $availableActions[] = 'hide';
                } else {
                    $targetState = 'This content is already hidden.';
                }
            } elseif ($target instanceof User) {
                if ($target->deactivated_at !== null) {
                    $targetState = 'This account is already deactivated.';
                } elseif (! $target->isAdmin()) {
                    $availableActions[] = 'deactivate';
                }
            }

            $availableActions[] = 'dismiss';
        }

        return [
            'id' => $report->id,
            'reason' => $report->reason,
            'reason_label' => Report::REASON_LABELS[$report->reason] ?? Str::headline($report->reason),
            'status' => $report->status,
            'status_label' => Report::STATUS_LABELS[$report->status] ?? Str::headline($report->status),
            'target_type' => $target ? class_basename($target) : 'Removed content',
            'target_title' => $targetTitle,
            'target_context' => $targetContext,
            'target_summary' => $targetSummary,
            'target_url' => $targetUrl,
            'target_state' => $targetState,
            'reporter' => [
                'name' => $report->reporter->name,
                'username' => $report->reporter->username,
            ],
            'target_owner' => $targetOwner ? [
                'name' => $targetOwner->name,
                'username' => $targetOwner->username,
            ] : null,
            'created_at' => $report->created_at->utc()->toIso8601String(),
            'details' => $report->details,
            'resolver' => $report->resolver ? [
                'name' => $report->resolver->name,
                'username' => $report->resolver->username,
            ] : null,
            'resolution_note' => $report->resolution_note,
            'update_url' => route('admin.moderation.update', $report),
            'available_actions' => $availableActions,
        ];
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
