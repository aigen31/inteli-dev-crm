<?php

declare(strict_types=1);

use App\Services\HtmlSanitizer;

test('sanitizer keeps formatting and strips scripts', function (): void {
    $html = (new HtmlSanitizer)->sanitize('<p>ok</p><script>alert(1)</script><strong>bold</strong>');

    expect($html)->toContain('<strong>bold</strong>')
        ->and($html)->not->toContain('<script');
});
