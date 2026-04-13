<?php

declare(strict_types=1);

/**
 * Landing contact form handler.
 * Independent from Laravel — uses PHPMailer directly.
 *
 * Expects POST JSON:
 *   { "name": "...", "contact": "...", "description": "...", "budget": "..." }
 *
 * Returns JSON:
 *   { "success": true } or { "success": false, "error": "..." }
 */

// ── CORS & headers ────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Allow same-origin requests only (no CORS needed for same domain)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Rate limiting (simple file-based) ─────────────────
$rateLimitDir = sys_get_temp_dir() . '/landing_rate_limit';
if (!is_dir($rateLimitDir)) {
    mkdir($rateLimitDir, 0700, true);
}

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
// Take the first IP if X-Forwarded-For contains multiple
$clientIp = trim(explode(',', $clientIp)[0]);
$rateLimitFile = $rateLimitDir . '/' . md5($clientIp);
$rateLimitWindow = 60; // seconds
$rateLimitMax = 3;     // max requests per window

if (file_exists($rateLimitFile)) {
    $data = json_decode(file_get_contents($rateLimitFile), true);
    if ($data && isset($data['ts'], $data['count'])) {
        if (time() - $data['ts'] < $rateLimitWindow) {
            if ($data['count'] >= $rateLimitMax) {
                http_response_code(429);
                echo json_encode(['success' => false, 'error' => 'Слишком много запросов. Попробуйте через минуту.']);
                exit;
            }
            $data['count']++;
        } else {
            $data = ['ts' => time(), 'count' => 1];
        }
    } else {
        $data = ['ts' => time(), 'count' => 1];
    }
} else {
    $data = ['ts' => time(), 'count' => 1];
}
file_put_contents($rateLimitFile, json_encode($data), LOCK_EX);

// ── Parse input ───────────────────────────────────────
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректный формат данных']);
    exit;
}

$name        = trim((string) ($input['name'] ?? ''));
$contact     = trim((string) ($input['contact'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$budget      = trim((string) ($input['budget'] ?? ''));

// ── Validation ────────────────────────────────────────
$errors = [];

if ($name === '' || mb_strlen($name) > 255) {
    $errors['name'] = 'Укажите ваше имя (до 255 символов)';
}

if ($contact === '' || mb_strlen($contact) > 255) {
    $errors['contact'] = 'Укажите способ связи (до 255 символов)';
}

if ($description === '' || mb_strlen($description) > 5000) {
    $errors['description'] = 'Опишите задачу (до 5000 символов)';
}

$allowedBudgets = ['', 'under_30k', '30k_100k', 'over_100k'];
if (!in_array($budget, $allowedBudgets, true)) {
    $budget = '';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Sanitize for email body ───────────────────────────
$name        = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$contact     = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

$budgetLabels = [
    ''          => 'Не определён',
    'under_30k' => 'до 30 000 ₽',
    '30k_100k'  => '30 000 – 100 000 ₽',
    'over_100k'  => '100 000+ ₽',
];
$budgetLabel = $budgetLabels[$budget] ?? 'Не определён';

// ── Load PHPMailer ────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Сервер не настроен. Обратитесь к администратору.']);
    exit;
}

require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$config = require __DIR__ . '/config.php';

// ── Build email ───────────────────────────────────────
$mail = new PHPMailer(true);

try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_username'];
    $mail->Password   = $config['smtp_password'];
    $mail->SMTPSecure = $config['smtp_secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $config['smtp_port'];
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 10;

    if (!empty($config['smtp_helo'])) {
        $mail->Helo = $config['smtp_helo'];
    }
    if (!empty($config['smtp_options'])) {
        $mail->SMTPOptions = $config['smtp_options'];
    }

    // Sender / recipient
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email'], $config['to_name']);

    // Add Reply-To if contact looks like email
    if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($contact, $name);
    }

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Новая заявка с лендинга от {$name}";

    $date = date('d.m.Y H:i');

    $mail->Body = <<<HTML
    <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background: linear-gradient(135deg, #4f46e5, #3730a3); padding: 24px 32px; border-radius: 8px 8px 0 0;">
            <h1 style="color: #ffffff; margin: 0; font-size: 20px;">Новая заявка с сайта</h1>
        </div>
        <div style="background: #ffffff; padding: 24px 32px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280; width: 140px; vertical-align: top;">Имя</td>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; color: #111827; font-weight: 500;">{$name}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280; vertical-align: top;">Контакт</td>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; color: #111827; font-weight: 500;">{$contact}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; color: #6b7280; vertical-align: top;">Бюджет</td>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6; color: #111827;">{$budgetLabel}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 0; color: #6b7280; vertical-align: top;">Описание</td>
                    <td style="padding: 12px 0; color: #111827; white-space: pre-wrap;">{$description}</td>
                </tr>
            </table>
            <div style="margin-top: 20px; padding: 12px 16px; background: #f9fafb; border-radius: 6px; font-size: 13px; color: #6b7280;">
                IP: {$clientIp} · Дата: {$date}
            </div>
        </div>
    </div>
    HTML;

    $mail->AltBody = "Новая заявка с сайта\n\n"
        . "Имя: {$name}\n"
        . "Контакт: {$contact}\n"
        . "Бюджет: {$budgetLabel}\n"
        . "Описание:\n{$description}\n\n"
        . "IP: {$clientIp}\n"
        . "Дата: {$date}";

    $mail->send();

    // ── Send Telegram notification (second channel) ───
    sendTelegram($config, $name, $contact, $budgetLabel, $description, $clientIp, $date);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Landing contact form mail error: " . $e->getMessage());

    // Try Telegram even if email failed
    $telegramOk = sendTelegram($config, $name, $contact, $budgetLabel, $description, $clientIp, $date ?? date('d.m.Y H:i'));

    if ($telegramOk) {
        // Telegram succeeded — still report success to user
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Не удалось отправить сообщение. Попробуйте написать в Telegram.']);
    }
}

// ── Telegram helper ───────────────────────────────────
function sendTelegram(array $config, string $name, string $contact, string $budgetLabel, string $description, string $ip, string $date): bool
{
    $token  = $config['telegram_bot_token'] ?? '';
    $chatId = $config['telegram_chat_id'] ?? '';

    if ($token === '' || $chatId === '') {
        return false;
    }

    // Decode HTML entities back for Telegram (they were escaped for email)
    $nameText = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
    $contactText = html_entity_decode($contact, ENT_QUOTES, 'UTF-8');
    $descText = html_entity_decode($description, ENT_QUOTES, 'UTF-8');

    // Escape MarkdownV2 special chars
    $esc = static function (string $text): string {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/u', '\\\\$1', $text);
    };

    $text = "📩 *Новая заявка с сайта*\n\n"
        . "👤 *Имя:* {$esc($nameText)}\n"
        . "📱 *Контакт:* {$esc($contactText)}\n"
        . "💰 *Бюджет:* {$esc($budgetLabel)}\n\n"
        . "📝 *Описание:*\n{$esc($descText)}\n\n"
        . "🕐 {$esc($date)} · IP: {$esc($ip)}";

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $payload = json_encode([
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'MarkdownV2',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Telegram send failed (HTTP {$httpCode}): " . ($response ?: 'no response'));
        return false;
    }

    return true;
}
