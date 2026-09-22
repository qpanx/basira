<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\ReadImageRequest;
use App\Services\OCRService;
use Illuminate\Http\JsonResponse;

class OCRController extends Controller
{
    public function __invoke(ReadImageRequest $request, OCRService $ocr): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $ocr->read($request->file('image'), $request->validated('language', app()->getLocale())),
        ]);
    }
}
