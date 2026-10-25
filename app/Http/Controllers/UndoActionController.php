<?php

namespace App\Http\Controllers;

use App\Services\ActionFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UndoActionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $token, ActionFeedback $feedback): JsonResponse
    {
        return response()->json(['feedback' => $feedback->undo($request->user(), $token)]);
    }
}
