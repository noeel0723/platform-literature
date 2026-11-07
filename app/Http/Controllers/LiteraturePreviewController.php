<?php

namespace App\Http\Controllers;

use App\Models\Literature;
use App\Support\LiteraturePreviewData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiteraturePreviewController extends Controller
{
    public function __invoke(Request $request, Literature $literature, LiteraturePreviewData $data): JsonResponse
    {
        return response()->json($data->present($literature, $request->user()))->header('Cache-Control', 'private, no-store');
    }
}
