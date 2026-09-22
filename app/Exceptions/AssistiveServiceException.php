<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AssistiveServiceException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 503)
    {
        parent::__construct($errorCode);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $this->errorCode,
            'message' => __('assistive.errors.'.$this->errorCode),
        ], $this->status);
    }
}
