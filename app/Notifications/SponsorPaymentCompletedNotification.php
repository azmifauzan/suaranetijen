<?php

namespace App\Notifications;

use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SponsorPaymentCompletedNotification extends Notification
{
    public function __construct(
        public readonly SponsorshipOrder $order,
        public readonly ?SponsoredEntry $entry = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $entityName = $this->entry?->entity?->name ?? 'Entitas';
        $formattedAmount = 'Rp '.number_format($this->order->amount, 0, ',', '.');
        $leaderboardUrl = route('sponsor.index');
        $orderCode = $this->order->provider_order_id ?? "#{$this->order->id}";

        return (new MailMessage)
            ->subject("Pembayaran Sponsor Berhasil — {$entityName}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Pembayaran untuk pesanan **{$orderCode}** sebesar **{$formattedAmount}** telah berhasil kami terima.")
            ->line("Entitas **{$entityName}** kini telah aktif dan tercatat pada Papan Peringkat Sponsor SuaraNetijen.")
            ->action('Lihat Papan Peringkat Sponsor', $leaderboardUrl)
            ->line('Terima kasih banyak atas dukungan Anda dalam memajukan transparansi sentimen publik di Indonesia.');
    }
}
