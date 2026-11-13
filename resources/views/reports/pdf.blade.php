<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 32px 38px 38px; }
        body { font-family: "DejaVu Sans", sans-serif; color: #1c1823; font-size: 11px; line-height: 1.45; }
        .top { border-bottom: 3px solid #5b48d6; padding-bottom: 14px; }
        .eyebrow { color: #6b6478; font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { font-size: 24px; line-height: 1.15; margin: 5px 0 8px; color: #1c1823; }
        .meta { color: #6b6478; font-size: 9px; }
        .meta strong { color: #1c1823; }
        .badge { display: inline-block; background: #f0ecff; color: #4432a3; border-radius: 10px; padding: 3px 8px; font-size: 8px; font-weight: bold; margin-right: 4px; }
        .intro { margin: 18px 0 12px; }
        .group { page-break-inside: avoid; border: 1px solid #e7e1d6; border-radius: 10px; margin: 12px 0; overflow: hidden; }
        .group-head { background: #faf8f4; padding: 8px 12px; border-bottom: 1px solid #eee8dd; }
        .group-head strong { font-size: 10px; }
        .group-body { padding: 12px; }
        .summary { white-space: pre-wrap; font-size: 11px; }
        .link { display: block; margin-top: 9px; color: #4d3bc4; font-size: 9px; word-break: break-all; }
        .shot { display: block; max-width: 100%; max-height: 300px; margin-top: 10px; border: 1px solid #e7e1d6; border-radius: 7px; }
        .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #eee8dd; color: #8b8496; font-size: 8px; }
    </style>
</head>
<body>
    <div class="top">
        <div class="eyebrow">inteli dev · отчёт о выполнении</div>
        <h1>{{ $title }}</h1>
        <div class="meta">
            @if($taskReference)<span class="badge">{{ $taskReference }}</span>@endif
            <span>Автор: <strong>{{ $report->author?->name ?: '—' }}</strong></span> ·
            <span>Получатель: <strong>{{ $report->recipient?->name ?: '—' }}</strong></span> ·
            <span>{{ $date }}</span>
        </div>
    </div>

    <div class="intro meta">
        <strong>{{ count($items) }}</strong> {{ count($items) === 1 ? 'группа' : 'групп' }} · краткое объяснение, ссылка для проверки и скриншот.
    </div>

    @foreach($items as $item)
        <div class="group">
            <div class="group-head"><strong>Группа {{ $item['number'] }}</strong> · что проверено</div>
            <div class="group-body">
                <div class="summary">{{ $item['summary'] }}</div>
                @if($item['test_url'])
                    <a class="link" href="{{ $item['test_url'] }}">{{ $item['test_url'] }}</a>
                @endif
                @if($item['image'])
                    <img class="shot" src="{{ $item['image'] }}" alt="Скриншот группы {{ $item['number'] }}">
                @endif
            </div>
        </div>
    @endforeach

    <div class="footer">Сформировано в CRM inteli-dev · {{ $date }}</div>
</body>
</html>
