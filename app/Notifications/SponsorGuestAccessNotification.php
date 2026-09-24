<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SponsorGuestAccessNotification extends Notification
{
    public function __construct(public readonly bool $isNewUser = true) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'sponsor.access.login',
            now()->addDays(7),
            ['user' => $notifiable->id]
        );

        return (new MailMessage)
            ->subject('Akun SuaraNetijen Anda Telah Dibuat')
            ->greeting('Terima kasih telah menjadi sponsor!')
            ->line('Pesanan sponsor Anda di Papan Peringkat Sponsor SuaraNetijen sedang diproses.')
            ->line('Kami telah membuatkan akun otomatis menggunakan alamat email ini agar Anda dapat memantau status sponsor Anda kapan saja — tanpa perlu kata sandi.')
            ->action('Masuk ke Akun Saya', $url)
            ->line('Tautan masuk ini berlaku selama 7 hari. Simpan email ini untuk akses berikutnya.');
    }
}
