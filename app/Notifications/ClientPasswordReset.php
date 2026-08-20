<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Mot de passe oublié" email for storefront customers. Points at the client
 * reset route (the framework default targets the staff `password.reset` route).
 */
class ClientPasswordReset extends Notification
{
    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = Setting::get('store_name') ?: config('app.name');
        $url = route('account.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.clients.expire', 60);

        return (new MailMessage)
            ->subject("Réinitialisation de votre mot de passe — {$store}")
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line("Vous avez demandé à réinitialiser le mot de passe de votre compte {$store}.")
            ->action('Choisir un nouveau mot de passe', $url)
            ->line("Ce lien expire dans {$minutes} minutes.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.")
            ->salutation("À bientôt,\n{$store}");
    }
}
