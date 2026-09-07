<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const REPORTABLE_TYPES = [
        'review' => Review::class,
        'discussion' => Discussion::class,
        'comment' => Comment::class,
        'user' => User::class,
    ];

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $targetClass = self::REPORTABLE_TYPES[$data['target_type']];
        $target = $targetClass::query()->findOrFail($data['target_id']);
        $targetOwner = $target instanceof User ? $target : $target->user;

        if ($targetOwner?->is($request->user())) {
            throw ValidationException::withMessages([
                'report' => 'You cannot report your own profile or content.',
            ]);
        }

        $report = $request->user()->submittedReports()->firstOrCreate(
            [
                'reportable_type' => $target::class,
                'reportable_id' => $target->getKey(),
                'status' => 'pending',
            ],
            [
                'reason' => $data['reason'],
                'details' => filled($data['details'] ?? null) ? trim($data['details']) : null,
            ],
        );

        $message = $report->wasRecentlyCreated
            ? 'Your report has been sent to the moderation team.'
            : 'You already have a pending report for this item.';

        return back()->with('success', $message);
    }
}
