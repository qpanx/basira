<?php

namespace App\Http\Controllers\Api\Device;

use App\Contracts\DestinationProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\SearchDestinationRequest;
use Illuminate\Http\JsonResponse;

class NavigationSearchController extends Controller
{
    public function __invoke(SearchDestinationRequest $request, DestinationProvider $destinations): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $destinations->search($request->validated('query'), $request->validated('language', app()->getLocale())),
            'meta' => ['attribution' => $destinations->attribution()],
        ]);
    }
}
