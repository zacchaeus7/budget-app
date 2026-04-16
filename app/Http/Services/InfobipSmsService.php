<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class InfobipSmsService
{
    protected $apiKey;
    protected $baseUrl;
    protected $sender;

    public function __construct()
    {
        $this->apiKey = config('services.infobip.api_key');
        $this->baseUrl = config('services.infobip.base_url');
        $this->sender = config('services.infobip.sender');
    }

    // ✅ Envoyer un SMS
    public function sendSms($to, $message)
    {

        $response = Http::withHeaders([
            'Authorization' => 'App ' . env('INFOBIP_API_KEY'), // Utilise `.env` pour stocker la clé
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post(env('INFOBIP_BASE_URL') . "/sms/2/text/advanced", [
            'messages' => [
                [
                    'from' => env('INFOBIP_SENDER', '243842761493'), // ID d'expéditeur
                    'destinations' => [
                        ['to' => $to],
                    ],
                    'text' => $message
                ]
            ]
        ]);
        
        // 📌 Vérifier la réponse
        if ($response->successful()) {
            return $response->json(); // Retourne la réponse en JSON
        } else {
            return response()->json([
                'error' => 'Unexpected HTTP status: ' . $response->status() . ' ' . $response->body()
            ], $response->status());
        }
    }

    // ✅ Récupérer les messages reçus (si ton compte a un numéro dédié)
    public function getReceivedMessages()
    {
        $response = Http::withHeaders([
            'Authorization' => 'App ' . $this->apiKey,
            'Accept'        => 'application/json',
        ])->get("{$this->baseUrl}/sms/1/inbox/reports");

        return $response->json();
    }
}

?>
