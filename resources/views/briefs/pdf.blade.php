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
        .section { page-break-inside: avoid; border: 1px solid #e7e1d6; border-radius: 10px; margin: 12px 0; overflow: hidden; }
        .section-head { background: #faf8f4; padding: 8px 12px; border-bottom: 1px solid #eee8dd; }
        .section-head strong { font-size: 10px; }
        .section-head span { color: #8b8496; font-size: 8px; }
        .rows { padding: 4px 12px; }
        .row { border-bottom: 1px solid #f2ece1; padding: 7px 0; }
        .row:last-child { border-bottom: 0; }
        .label { color: #6b6478; font-size: 8px; letter-spacing: 0.4px; text-transform: uppercase; }
        .value { margin-top: 2px; white-space: pre-wrap; }
        .empty { color: #a89fae; }
        .note { margin-top: 16px; border-left: 3px solid #e9956f; padding: 6px 12px; background: #fdf6f1; }
        .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #eee8dd; color: #8b8496; font-size: 8px; }
    </style>
</head>
<body>
    <div class="top">
        <div class="eyebrow">inteli dev · бриф клиента</div>
        <h1>{{ $brief->title }}</h1>
        <div class="meta">
            @if($brief->template)<span class="badge">{{ $brief->template->title }}</span>@endif
            @if($brief->client)<span>Клиент: <strong>{{ $brief->client->name }}</strong></span> · @endif
            @if($brief->project)<span>Проект: <strong>{{ $brief->project->key }}</strong></span> · @endif
            <span>{{ $brief->submitted_at?->format('d.m.Y H:i') ?? $brief->updated_at?->format('d.m.Y H:i') }}</span>
        </div>
    </div>

    @foreach($sections as $section)
        <div class="section">
            <div class="section-head">
                <strong>{{ $section['title'] }}</strong>
                @if($section['description'] !== '')
                    <br><span>{{ $section['description'] }}</span>
                @endif
            </div>
            <div class="rows">
                @foreach($section['rows'] as $row)
                    @continue(! $row['answered'])
                    <div class="row">
                        <div class="label">{{ $row['label'] }}</div>
                        <div class="value">{{ $row['display'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if(($brief->admin_note ?? '') !== '')
        <div class="note">
            <div class="label">Заметка</div>
            <div class="value">{{ $brief->admin_note }}</div>
        </div>
    @endif

    <div class="footer">
        Сформирован {{ $generatedAt->format('d.m.Y H:i') }} · inteli-dev.ru
    </div>
</body>
</html>
