<?php

namespace App\Domains\Sponsorships\Services;

use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TelegramSponsorNotifier
{
    public function send(SponsorshipOrder $order, ?SponsoredEntry $entry): void
    {
        $botToken = trim((string) config('sponsorship.telegram.bot_token'));
        $chatId = trim((string) config('sponsorship.telegram.chat_id'));

        if ($botToken === '' || $chatId === '') {
            return;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $this->message($order, $entry),
                    'disable_web_page_preview' => true,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Telegram sendMessage request failed.', previous: $exception);
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            $description = $response->json('description');

            throw new RuntimeException(is_string($description) ? $description : 'Telegram sendMessage failed.');
        }
    }

    private function message(SponsorshipOrder $order, ?SponsoredEntry $entry): string
    {
        $entityName = $entry === null ? 'Entitas tidak diketahui' : $entry->entity->name;
        $websiteUrl = $entry?->entity?->website_url ?: '-';
        $orderCode = $order->provider_order_id ?: "#{$order->id}";
        $paidAt = $order->paid_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

        return implode("\n", [
            '📣 SuaraNetijen | Paid Sponsor Berhasil',
            "Entitas: {$entityName}",
            "Website: {$websiteUrl}",
            'Nominal: Rp '.number_format($order->amount, 0, ',', '.'),
            "Order: {$orderCode}",
            "Waktu: {$paidAt}",
        ]);
    }
}
