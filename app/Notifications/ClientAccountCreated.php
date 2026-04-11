<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientAccountCreated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $plainPassword,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ваш аккаунт создан')
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line('Для вас создан аккаунт в CRM-системе.')
            ->line("Email: {$notifiable->email}")
            ->line("Временный пароль: {$this->plainPassword}")
            ->line('Рекомендуем сменить пароль после первого входа.')
            ->action('Войти в систему', url('/login'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_created',
            'message' => 'Добро пожаловать! Ваш аккаунт создан.',
        ];
    }
}
