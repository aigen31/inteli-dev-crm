#!/usr/bin/env python3
"""Audit CRM messages.json and hardcoded UI strings.

- Catalog structure/completeness/placeholders for en + zh_CN.
- Locale lists in JS/PHP.
- Russian text in Vue templates and attributes that has no catalog key.
- JSON/Markdown-ish output; exit 1 on catalog critical issues.
"""
from __future__ import annotations

import argparse
import html
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path

DEFAULT_CRM = Path('/mnt/disk1/work/inteli-dev-infrastructure/inteli-dev-crm')
LOCALES = ('en', 'zh')
CYRILLIC = re.compile(r'[А-Яа-яЁё]')
DYNAMIC = re.compile(r'\{\{|\}\}|:[A-Za-z_]|\{[A-Za-z_]|%[sd]')
TAG = re.compile(r'<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>', re.S)
COMMENT = re.compile(r'<!--.*?-->', re.S)
SPACE = re.compile(r'\s+')


def read(path: Path) -> str:
    return path.read_text(encoding='utf-8') if path.exists() else ''


def normalized(value: str) -> str:
    return SPACE.sub(' ', html.unescape(value)).strip()


def catalog_lookup(catalog: dict, value: str) -> bool:
    key = normalized(value)
    if not key:
        return True
    variants = {key, key.lower(), key[:1].lower() + key[1:] if key else key, key[:1].upper() + key[1:] if key else key}
    stripped = re.sub(r'[,:;.!?]$', '', key)
    variants.add(stripped)
    return any(variant in catalog for variant in variants)


def placeholder_set(value: str) -> list[str]:
    return sorted(re.findall(r':[A-Za-z_]+|\{[A-Za-z_]+\}|%[sd]', value))


def html_tags(value: str) -> list[str]:
    return sorted(re.findall(r'</?([a-z][a-z0-9-]*)\b', value, re.I))


def scan_vue(path: Path) -> list[tuple[str, str, int]]:
    text = read(path)
    # Keep template only; scripts contain emit names and JS APIs.
    match = re.search(r'<template>(.*?)</template>', text, re.S | re.I)
    body = match.group(1) if match else text
    body = re.sub(r'<script\b.*?</script>|<style\b.*?</style>', ' ', body, flags=re.S | re.I)
    body = COMMENT.sub(' ', body)
    # These are deliberately skipped by translateDom or contain code/markdown.
    body = re.sub(r'<textarea\b.*?</textarea>', ' ', body, flags=re.S | re.I)
    body = re.sub(r'<([a-z][a-z0-9-]*)\b[^>]*data-i18n-skip[^>]*>.*?</\1>', ' ', body, flags=re.S | re.I)
    body = re.sub(r'<([a-z][a-z0-9-]*)\b[^>]*class="[^"]*prose-kiki[^"]*"[^>]*>.*?</\1>', ' ', body, flags=re.S | re.I)

    # Attributes are user-visible too. Capture before tags are removed.
    found: list[tuple[str, str, int]] = []
    for attr in ('placeholder', 'title', 'aria-label', 'alt'):
        for match_attr in re.finditer(r'(?<!:)' + attr + r'=\"([^\"]+)\"', body, re.I | re.S):
            value = normalized(match_attr.group(1))
            if len(value) >= 2 and CYRILLIC.search(value) and '{{' not in value:
                line = body.count('\n', 0, match_attr.start()) + 1
                found.append((str(path), value, line))

    # Remove all tags across newlines, then treat what remains as text nodes.
    stripped = TAG.sub('\n', body)
    stripped = re.sub(r'\{\{.*?\}\}', ' ', stripped, flags=re.S)
    for line_no, piece in enumerate(stripped.splitlines(), 1):
        value = normalized(piece)
        if len(value) >= 2 and CYRILLIC.search(value):
            found.append((str(path), value, line_no))

    return found


def locale_lists(root: Path) -> dict[str, list[str]]:
    js = read(root / 'resources/js/i18n/index.js')
    inertia = read(root / 'app/Http/Middleware/HandleInertiaRequests.php')
    controller = read(root / 'app/Http/Controllers/LocaleController.php')
    js_codes = re.findall(r"code:\s*'([^']+)'", js)
    inertia_codes = re.findall(r"'code'\s*=>\s*'([^']+)'", inertia)
    controller_codes = re.findall(r"'([a-z]{2}(?:_[A-Z]{2})?)'", controller)
    return {'js': js_codes, 'inertia': inertia_codes, 'controller': controller_codes}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--crm', type=Path, default=DEFAULT_CRM)
    parser.add_argument('--json', action='store_true')
    parser.add_argument('--limit', type=int, default=80)
    args = parser.parse_args()

    catalog_path = args.crm / 'resources/js/i18n/messages.json'
    catalog = json.loads(read(catalog_path) or '{}')
    critical: list[str] = []
    warnings: list[str] = []

    if not catalog:
        critical.append('messages.json is empty or missing')
    for locale in LOCALES:
        missing = [key for key, row in catalog.items() if not isinstance(row, dict) or not str(row.get(locale, '')).strip()]
        if missing:
            critical.append(f'{locale}: {len(missing)} missing values (e.g. {missing[:3]})')

    for key, row in catalog.items():
        if not isinstance(row, dict):
            critical.append(f'key {key!r} is not an object')
            continue
        for locale in LOCALES:
            if not isinstance(row.get(locale), str):
                continue
            if placeholder_set(key) != placeholder_set(row[locale]):
                critical.append(f'{locale}: placeholder mismatch in {key!r}')
            if html_tags(key) != html_tags(row[locale]):
                critical.append(f'{locale}: HTML tag mismatch in {key!r}')

    allowlist_path = args.crm / '.agents/skills/inteli-dev-crm-localization-audit/references/allowlist.txt'
    allowlist = {
        line.strip() for line in read(allowlist_path).splitlines()
        if line.strip() and not line.lstrip().startswith('#')
    }

    same = {locale: [key for key, row in catalog.items() if isinstance(row, dict) and str(row.get(locale, '')).strip() == key.strip() and key not in allowlist] for locale in LOCALES}
    for locale, keys in same.items():
        if keys:
            warnings.append(f'{locale}: {len(keys)} values equal the Russian key (check allowlist): {keys[:5]}')

    casefold: dict[str, list[str]] = defaultdict(list)
    for key in catalog:
        casefold[key.casefold()].append(key)
    duplicates = {
        key: values for key, values in casefold.items()
        if len(values) > 1
        and len({tuple(str(catalog[value].get(locale, '')) for locale in LOCALES) for value in values}) > 1
    }
    if duplicates:
        warnings.append(f'case-insensitive duplicate keys with different translations: {len(duplicates)} (e.g. {list(duplicates.values())[:3]})')

    locales = locale_lists(args.crm)
    unique = {name: set(codes) for name, codes in locales.items()}
    if len({frozenset(codes) for codes in unique.values()}) > 1:
        critical.append(f'locale lists drift: {locales}')

    # Scan Vue UI text. Technical allowlist keeps the report useful.
    unmatched: list[tuple[str, str, int]] = []
    files = sorted((args.crm / 'resources/js').rglob('*.vue'))
    for path in files:
        if '/i18n/' in path.as_posix():
            continue
        for file, value, line in scan_vue(path):
            if value in allowlist:
                continue
            if DYNAMIC.search(value):
                continue
            if catalog_lookup(catalog, value):
                continue
            if re.fullmatch(r'[A-Za-z0-9 .:/_-]+', value):
                continue
            unmatched.append((file.replace(str(args.crm) + '/', ''), value, line))

    dedup: dict[str, tuple[str, int]] = {}
    for file, value, line in unmatched:
        dedup.setdefault(value, (file, line))

    if dedup:
        warnings.append(f'Vue UI strings not found in messages.json: {len(dedup)} unique')

    report = {
        'catalog_keys': len(catalog),
        'locales': locales,
        'critical': critical,
        'warnings': warnings,
        'unmatched_ui': [
            {'text': text, 'file': file, 'line': line}
            for text, (file, line) in list(dedup.items())[: args.limit]
        ],
        'unmatched_total': len(dedup),
    }

    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
    else:
        print(f"catalog keys: {len(catalog)}")
        print(f"locales: {locales}")
        print(f"critical: {len(critical)} | warnings: {len(warnings)}")
        for item in critical:
            print(' CRITICAL:', item)
        for item in warnings:
            print(' WARN:', item)
        if report['unmatched_ui']:
            print('\nUnmatched UI text (first {} of {}):'.format(len(report['unmatched_ui']), report['unmatched_total']))
            for item in report['unmatched_ui']:
                print(f" - {item['file']}:{item['line']}  {item['text']}")

    return 1 if critical else 0


if __name__ == '__main__':
    sys.exit(main())
