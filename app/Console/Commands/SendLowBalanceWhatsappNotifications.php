<?php

namespace App\Console\Commands;

use App\Http\Services\InfobipWhatsappService;
use App\Models\Account;
use App\Models\Notification;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

class SendLowBalanceWhatsappNotifications extends Command
{
    protected $signature = 'notifications:send-low-balance-whatsapp {--threshold=100}';

    protected $description = 'Envoie les alertes WhatsApp quand un compte est en dessous du seuil defini.';

    public function handle(InfobipWhatsappService $whatsappService): int
    {
        $threshold = (float) $this->option('threshold');
        $slot = $this->resolveScheduledSlot(now());
        $sentCount = 0;

        Account::query()
            ->with('user')
            ->where('balance', '<', $threshold)
            ->whereHas('user', fn ($query) => $query->whereNotNull('whatsapp_number'))
            ->orderBy('id')
            ->chunkById(100, function ($accounts) use ($threshold, $slot, $whatsappService, &$sentCount) {
                foreach ($accounts as $account) {
                    if ($this->notificationAlreadySentForCurrentSlot($account->id, $slot)) {
                        continue;
                    }

                    $message = sprintf(
                        'Alerte budget: le compte %s est a %.2f$, en dessous du seuil de %.2f$.',
                        $account->name,
                        (float) $account->balance,
                        $threshold
                    );

                    $notification = Notification::create([
                        'user_id' => $account->user_id,
                        'account_id' => $account->id,
                        'type' => 'whatsapp',
                        'scheduled_slot' => $slot,
                        'message' => $message,
                        'is_sent' => false,
                    ]);

                    try {
                        $whatsappService->sendWhatsappMessage($account->user->whatsapp_number, $message);

                        $notification->update([
                            'is_sent' => true,
                            'sent_at' => now(),
                        ]);

                        $sentCount++;
                    } catch (\Throwable $exception) {
                        $this->error("Echec d envoi pour le compte #{$account->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info("Notifications WhatsApp envoyees: {$sentCount}");

        return self::SUCCESS;
    }

    private function resolveScheduledSlot(CarbonInterface $dateTime): string
    {
        return $dateTime->hour < 12 ? 'morning' : 'evening';
    }

    private function notificationAlreadySentForCurrentSlot(int $accountId, string $slot): bool
    {
        return Notification::query()
            ->where('account_id', $accountId)
            ->where('type', 'whatsapp')
            ->where('scheduled_slot', $slot)
            ->whereDate('created_at', today())
            ->exists();
    }
}
