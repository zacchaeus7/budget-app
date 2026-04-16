<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class InfobipEmailService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.infobip.key');
        $this->baseUrl = config('services.infobip.url');
    }

    public function sendEmail($to, $subject, $content, $placeholders = [], $from = null)
    {
        // $from = $from ?? config('mail.from.address');

        $response = Http::withHeaders([
            'Authorization' => 'App ' . env('INFOBIP_API_KEY'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->post(env("INFOBIP_API_KEY")."/email/3/send", [
            'from' => "zackabemba4@gmail.com",
            'subject' => $subject,
            'to' => json_encode([
                'to' => $to,
                'placeholders' => $placeholders
            ]),
            'text' => $content
        ]);

        return $response->json();
    }
}

?>
