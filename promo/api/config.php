<?php

declare(strict_types=1);

/**
 * SMTP configuration for the landing contact form.
 *
 * Set these values via environment variables or edit directly for standalone use.
 * In Docker, they are inherited from the main .env file.
 */

return [
    // Use direct IP because outbound ports 465/587 are blocked on VPS;
    // port 25 with STARTTLS works via the resolved IP.
    'smtp_host'     => getenv('LANDING_SMTP_HOST') ?: '5.101.158.98',
    'smtp_port'     => (int) (getenv('LANDING_SMTP_PORT') ?: 25),
    'smtp_username' => getenv('MAIL_USERNAME') ?: '',
    'smtp_password' => getenv('MAIL_PASSWORD') ?: '',
    'smtp_secure'   => 'tls',  // STARTTLS on port 25
    'smtp_helo'     => 'inteli-dev.ru',

    // Peer name for TLS certificate verification (IP doesn't match CN)
    'smtp_options'  => [
        'ssl' => [
            'verify_peer_name' => false,
            'peer_name'        => 'smtp.beget.com',
        ],
    ],

    'from_email'    => getenv('MAIL_FROM_ADDRESS') ?: 'info@inteli-dev.ru',
    'from_name'     => getenv('MAIL_FROM_NAME') ?: 'Inteli-Dev Landing',

    'to_email'      => 'info@inteli-dev.ru',
    'to_name'       => 'Евгений Биль',

    // ── Telegram bot (second notification channel) ────
    'telegram_bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '8684474797:AAH_fpZ4suV2f7un5VyNo-mNxtI-njEq6Tc',
    'telegram_chat_id'   => getenv('TELEGRAM_CHAT_ID') ?: '',
];
