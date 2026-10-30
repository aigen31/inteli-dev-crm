# Тесты и проверка изменений

## Что есть

- Pest 5 / PHPUnit 13, `tests/Feature` и `tests/Unit`; `RefreshDatabase` на sqlite `:memory:` (`phpunit.xml`).
- 12 тестов: API-токены и скоуп, custom fields (значения и дефолты), применение шаблона, sanitizer, fallback генератора, подтверждение финансового действия Кики.
- MCP: 5 тестов на протокол и инструменты (`cd mcp && npm test`), без живого CRM — mock HTTP API.

## Запуск

Production-образ без dev-зависимостей. Используйте host-`vendor/` (в нём есть Pest) и контейнер с PHP 8.5:

```bash
rm -f bootstrap/cache/*.php
docker run --rm --network inteli-dev-crm_crm --env-file .env \
  -v "$PWD":/var/www -w /var/www --entrypoint php inteli-dev-crm-app \
  artisan test --compact
```

Перед запуском удаляйте `bootstrap/cache/*.php`: host-кеш собран с dev-пакетами (Pail) и не совместим с production-vendor.

## Линт

```bash
docker run --rm -v "$PWD":/var/www -w /var/www --entrypoint php \
  inteli-dev-crm-app vendor/bin/pint --test app routes tests database
```

Стиль: `declare(strict_types=1)`, нативные enum, типизированные сигнатуры. `promo/` — отдельный статический проект, его Pint не проверяет.

## Ручной сценарий

- **Шаблоны:** создать/изменить шаблон, применить к клиенту, проверить чек-лист и `usage_count`; сгенерировать черновик через Кики без API-ключа (fallback).
- **Поля:** создать поле разных типов, заполнить в карточке, проверить дефолт и `GET /api/v1/custom-fields/{entity}/{id}/values`.
- **API:** создать токен `read`, проверить `GET /clients`, `GET /tasks`, `GET /custom-fields`; `POST /tasks` должен вернуть 403; токен `write` — создать задачу.
- **Кики:** без ключа — понятная ошибка; с OpenAI-совместимым провайдером — tool call, ответ, confirm финансового действия; клиентский аккаунт не видит чужие данные.
- **MCP:** `tools/list`, `crm_overview`, `list_tasks` с живым токеном.
- **UI:** 1440px и 390px, пустые состояния, мобильный бар, панель Кики, WYSIWYG, чек-лист.

## CI

CI-пайплайна нет. Минимум перед коммитом: `artisan test`, `pint --test`, `npm run build`, `cd mcp && npm test`.
