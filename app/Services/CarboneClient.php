<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CarboneClient
{
    /**
     * Merge $data into the given template binary and return the rendered
     * document as raw bytes. Carbone escapes values automatically, so
     * callers must pass raw (unescaped) strings.
     */
    public function render(string $templateBinary, array $data, string $convertTo = 'pdf'): string
    {
        $response = Http::baseUrl(config('services.carbone.base_url'))
            ->timeout(30)
            ->post('/render/template?download=true', [
                'template' => base64_encode($templateBinary),
                'convertTo' => $convertTo,
                'data' => $data,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Carbone render failed: '.$response->body()
            );
        }

        return $response->body();
    }
}
