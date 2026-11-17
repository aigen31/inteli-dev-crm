<!DOCTYPE html>
@php
    $currentUser = auth()->user();
    $interfaceTheme = $currentUser?->theme;

    if ($interfaceTheme === 'xp' && ! $currentUser?->hasFeature('xp_theme')) {
        $interfaceTheme = 'default';
    }

    $interfaceTheme = in_array($interfaceTheme, ['default', 'dark', 'xp', 'system'], true) ? $interfaceTheme : 'default';
@endphp
<html lang="ru" data-theme="{{ in_array($interfaceTheme, ['dark', 'xp'], true) ? $interfaceTheme : 'default' }}">
<head>
    <script>
        (function () {
            var theme = @json($interfaceTheme);
            if (theme === 'system') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'default';
            }
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F6F3EC">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
    <link rel="icon" href="/favicon-16x16.png" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    <title data-inertia>CRM</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|unbounded:400,500,600|jetbrains-mono:400,500|noto-sans-sc:400,500,600,700,800"
        rel="stylesheet"
    />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="min-h-full font-sans antialiased">
    @inertia
</body>
</html>
