<?php

declare(strict_types=1);

test('every CRM message has English and Chinese translations with stable placeholders', function (): void {
    $path = resource_path('js/i18n/messages.json');
    $messages = json_decode((string) file_get_contents($path), true);

    expect($messages)->toBeArray()->not->toBeEmpty();

    foreach ($messages as $key => $row) {
        expect($row)->toBeArray()->toHaveKeys(['en', 'zh']);
        expect(trim((string) $row['en']))->not->toBe('');
        expect(trim((string) $row['zh']))->not->toBe('');

        preg_match_all('/:[A-Za-z_]+|\{[A-Za-z_]+\}|%[sd]/', (string) $key, $source);
        preg_match_all('/:[A-Za-z_]+|\{[A-Za-z_]+\}|%[sd]/', (string) $row['en'], $english);
        preg_match_all('/:[A-Za-z_]+|\{[A-Za-z_]+\}|%[sd]/', (string) $row['zh'], $chinese);

        sort($source[0]);
        sort($english[0]);
        sort($chinese[0]);

        expect($english[0])->toBe($source[0], "EN placeholders differ for {$key}");
        expect($chinese[0])->toBe($source[0], "ZH placeholders differ for {$key}");
    }
});
