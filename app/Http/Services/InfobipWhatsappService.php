<?php

namespace App\Http\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class InfobipWhatsappService
{
    public function sendWhatsappMessage(string $to, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'App ' . config('services.infobip.api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->resolveBaseUrl() . '/whatsapp/1/message/template', [
            'messages' => [
                [
                    'from' => config('services.infobip.whatsapp_sender'),
                    'to' => $to,
                    'content' => [
                        'templateName' => config('services.infobip.whatsapp_template'),
                        'templateData' => [
                            'body' => [
                                'placeholders' => ['Alternatives-Plus', $message],
                            ],
                        ],
                        'language' => 'fr',
                    ],
                ],
            ],
        ]);

        try {
            $response->throw();
        } catch (RequestException $exception) {
            throw new \RuntimeException(
                'Echec de l envoi WhatsApp via Infobip: ' . $exception->response->body(),
                previous: $exception
            );
        }

        return $response->json();
    }

    private function resolveBaseUrl(): string
    {
        $baseUrl = (string) config('services.infobip.base_url');

        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }

        return rtrim($baseUrl, '/');
    }
}
