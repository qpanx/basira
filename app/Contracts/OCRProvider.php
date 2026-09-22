<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface OCRProvider
{
    /** @return array{text: string, language: string|null} */
    public function read(UploadedFile $image, string $language): array;
}
