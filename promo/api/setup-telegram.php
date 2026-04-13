<?php

declare(strict_types=1);

/**
 * Telegram Bot setup utility.
 *
 * Usage (from inside the app container):
 *   php /var/www/promo/api/setup-telegram.php
 *
 * This script polls getUpdates to find the chat_id after you send /start to the bot.
 * It will print the chat_id and a test message.
 */

$config = require __DIR__ . '/config.php';
$token  = $config['telegram_bot_token'] ?? '';

if ($token === '') {
    echo "ERROR: telegram_bot_token is not configured.\n";
    exit(1);
}

echo "Bot token: {$token}\n";
echo "Waiting for /start message... Send /start to the bot in Telegram.\n";
echo "Polling for 60 seconds...\n\n";

$startTime = time();
$chatId = null;

while (time() - $startTime < 60) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates?timeout=5";
    $response = @file_get_contents($url);

    if ($response === false) {
        echo "  Failed to connect to Telegram API.\n";
        sleep(2);
        continue;
    }

    $data = json_decode($response, true);

    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $msg = $update['message'] ?? null;
            if ($msg && isset($msg['chat']['id'])) {
                $chatId = $msg['chat']['id'];
                $from   = $msg['from']['first_name'] ?? 'Unknown';
                $text   = $msg['text'] ?? '';
                echo "  Found message from: {$from} (chat_id: {$chatId})\n";
                echo "  Text: {$text}\n\n";

                // Acknowledge the update
                $lastUpdateId = $update['update_id'] + 1;
                @file_get_contents("https://api.telegram.org/bot{$token}/getUpdates?offset={$lastUpdateId}");
                break 2;
            }
        }
    }

    echo ".";
}

echo "\n";

if ($chatId === null) {
    echo "No messages received. Make sure you sent /start to @" . getBotUsername($token) . "\n";
    exit(1);
}

echo "=== RESULT ===\n";
echo "Chat ID: {$chatId}\n\n";

// Send test message
echo "Sending test message...\n";
$testText = "✅ Бот настроен\\!\n\nЗаявки с лендинга будут приходить в этот чат\\.";
$payload = json_encode([
    'chat_id'    => $chatId,
    'text'       => $testText,
    'parse_mode' => 'MarkdownV2',
]);

$ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
]);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "Test message sent successfully!\n\n";
} else {
    echo "Failed to send test message (HTTP {$httpCode}): {$result}\n\n";
}

echo "=== NEXT STEPS ===\n";
echo "Add this to your .env file:\n";
echo "  TELEGRAM_CHAT_ID={$chatId}\n\n";
echo "Then restart the app container:\n";
echo "  docker compose restart app\n";

function getBotUsername(string $token): string
{
    $response = @file_get_contents("https://api.telegram.org/bot{$token}/getMe");
    if ($response) {
        $data = json_decode($response, true);
        return $data['result']['username'] ?? 'unknown';
    }
    return 'unknown';
}
