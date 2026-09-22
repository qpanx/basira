<?php

namespace App\Services;

use App\Exceptions\AssistiveServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageProviderClient
{
    /** @return array<string, mixed> */
    public function send(string $provider, UploadedFile $image, string $language): array
    {
        $url = config("services.{$provider}.url");
        if (! is_string($url) || $url === '') {
            throw new AssistiveServiceException('provider_not_configured');
        }

        $client = Http::acceptJson()->connectTimeout(3)
            ->timeout(max(1, min(20, (int) config("services.{$provider}.timeout", 10))))
            ->withoutRedirecting();
        $key = config("services.{$provider}.api_key");
        if (is_string($key) && $key !== '') {
            $client->withToken($key);
        }

        try {
            $response = $client->attach('image', $image->getContent(), 'capture.'.$image->guessExtension())
                ->post($url, ['language' => $language]);
        } catch (ConnectionException) {
            Log::warning('Image provider connection failed.', ['provider' => $provider]);
            throw new AssistiveServiceException('provider_unavailable');
        }

        if (! $response->successful()) {
            Log::warning('Image provider request failed.', ['provider' => $provider, 'status' => $response->status()]);
            throw new AssistiveServiceException('provider_unavailable');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        return $data;
    }
}
