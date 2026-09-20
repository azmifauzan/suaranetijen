<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SponsorGuestAccessNotification extends Notification
{
    public function __construct(private readonly bool $isNewUser) {}

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

        $message = (new MailMessage)
            ->subject($this->isNewUser
                ? 'Akun SuaraNetijen Anda sudah dibuat'
                : 'Link masuk ke akun SuaraNetijen Anda')
            ->greeting($this->isNewUser ? 'Terima kasih sudah menjadi sponsor!' : 'Halo lagi!')
            ->line('Pesanan sponsor Anda di Papan Sponsor SuaraNetijen sedang diproses.');

        if ($this->isNewUser) {
            $message->line('Kami membuatkan akun otomatis dari email ini agar Anda bisa memantau status sponsor kapan saja — tidak perlu kata sandi.');
        }

        return $message
            ->action('Masuk ke Akun Saya', $url)
            ->line('Link ini berlaku 7 hari. Simpan email ini untuk akses berikutnya.');
    }
}
