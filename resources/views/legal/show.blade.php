<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — inteli dev</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">
    <header class="border-b border-line/70 bg-surface/70">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-3 px-4 py-4">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <span class="text-lg font-bold tracking-tight text-ink">inteli dev</span>
                <span class="rounded-full border border-line px-2 py-[2px] font-mono text-[8px] uppercase tracking-[0.18em] text-muted">legal</span>
            </a>
            <nav class="ml-auto flex flex-wrap gap-3 text-[11px] font-semibold text-muted">
                <a href="{{ route('legal.privacy') }}" class="{{ $document === 'privacy' ? 'text-ink' : 'hover:text-ink' }}">Политика</a>
                <a href="{{ route('legal.consent') }}" class="{{ $document === 'consent' ? 'text-ink' : 'hover:text-ink' }}">Согласие</a>
                <a href="{{ route('legal.requisites') }}" class="{{ $document === 'requisites' ? 'text-ink' : 'hover:text-ink' }}">Реквизиты</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-10">
        <article class="surface-card prose-kiki p-6 sm:p-10">
            {!! $content !!}
        </article>

        <footer class="mt-6 flex flex-wrap items-center gap-3 text-[10px] text-muted">
            <span>© 2020–2026 Евгений Биль</span>
            <a href="{{ route('legal.privacy') }}" class="hover:text-ink">Политика</a>
            <a href="{{ route('legal.consent') }}" class="hover:text-ink">Согласие</a>
            <a href="{{ route('legal.requisites') }}" class="hover:text-ink">Реквизиты</a>
            <a href="mailto:contact@inteli-dev.ru" class="ml-auto hover:text-ink">contact@inteli-dev.ru</a>
        </footer>
    </main>
</body>
</html>
