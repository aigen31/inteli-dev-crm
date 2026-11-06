<!DOCTYPE html>
@php
    $currentUser = auth()->user();
    $interfaceTheme = $currentUser?->theme;

    if ($interfaceTheme === 'xp' && ! $currentUser?->hasFeature('xp_theme')) {
        $interfaceTheme = 'default';
    }

    $interfaceTheme = in_array($interfaceTheme, ['default', 'dark', 'xp'], true) ? $interfaceTheme : 'default';
@endphp
<html lang="ru" data-theme="{{ $interfaceTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F6F3EC">
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
