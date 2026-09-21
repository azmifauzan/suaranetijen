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
                ? 'Akun SuaraNetijen Anda Telah Dibuat'
                : 'Tautan Masuk ke Akun SuaraNetijen Anda')
            ->greeting($this->isNewUser ? 'Terima kasih telah menjadi sponsor!' : 'Halo kembali!')
            ->line('Pesanan sponsor Anda di Papan Peringkat Sponsor SuaraNetijen sedang diproses.');

        if ($this->isNewUser) {
            $message->line('Kami telah membuatkan akun otomatis menggunakan alamat email ini agar Anda dapat memantau status sponsor Anda kapan saja — tanpa perlu kata sandi.');
        }

        return $message
            ->action('Masuk ke Akun Saya', $url)
            ->line('Tautan masuk ini berlaku selama 7 hari. Simpan email ini untuk akses berikutnya.');
    }
}
