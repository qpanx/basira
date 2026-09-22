<?php

namespace App\Services;

use App\Contracts\OCRProvider;
use App\Exceptions\AssistiveServiceException;
use Illuminate\Http\UploadedFile;

class OCRService
{
    public function __construct(private OCRProvider $provider) {}

    /** @return array{text: string, language: string|null} */
    public function read(UploadedFile $image, string $language): array
    {
        $result = $this->provider->read($image, $language);
        $text = trim(str_replace(["\r\n", "\r"], "\n", $result['text']));
        if (preg_match('/^\s*$/u', $text)) {
            throw new AssistiveServiceException('no_text_detected', 422);
        }

        return ['text' => $text, 'language' => $result['language']];
    }
}
