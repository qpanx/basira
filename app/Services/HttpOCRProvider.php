<?php

namespace App\Services;

use App\Contracts\OCRProvider;
use App\Exceptions\AssistiveServiceException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class HttpOCRProvider implements OCRProvider
{
    public function __construct(private ImageProviderClient $client) {}

    public function read(UploadedFile $image, string $language): array
    {
        $data = $this->client->send('ocr', $image, $language);
        if (Validator::make($data, [
            'text' => ['present', 'string', 'max:50000'],
            'language' => ['present', 'nullable', 'in:en,ar'],
        ])->fails()) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        return ['text' => $data['text'], 'language' => $data['language']];
    }
}
