# Kiki assistant (CRM adapter)

> С 2026-11 модель, история и tool loop вынесены в
> `inteli-dev-kiki-assistant-kernel-api`. CRM остаётся владельцем клиентов,
> задач, счетов, прав и подтверждений. Ниже — актуальный поток.

## Поток

`Components/Assistant/AssistantPanel.vue` → `POST /assistant/chat` →
`AgentService::chat` → `KernelClient` → `POST /v1/chat` ядра.

Ядро запрашивает у CRM:

- `GET /api/internal/assistant/profile` — системные правила роли и JSON-схемы
  инструментов;
- `POST /api/internal/assistant/tools/{tool}` — выполнение инструмента.

CRM проверяет `KERNEL_PROVIDER_TOKEN` и `X-Kernel-Channel: crm`, загружает
пользователя по `actor.id`, применяет роли в `CrmToolbox`.

## Подтверждения

Инструмент с `confirm=true` создаёт `ai_actions(status=proposed)` и
возвращает `pending_action`. Ядро останавливает tool loop и отдаёт действие
в UI. `POST /assistant/actions/{id}/confirm` выполняет его в CRM, после чего
`KernelClient::appendNote` добавляет результат в историю ядра.

## Данные

- модель и диалог: ядро;
- клиенты, задачи, счета, `ai_actions`: PostgreSQL CRM;
- публичные знания сайта: сайт;
- внутренние и секретные данные CRM идут только в локальную модель
  (`KERNEL_DATA_CLASS=internal`, `KERNEL_ALLOW_CLOUD_FOR_INTERNAL=false`).

## Ограничения

- `AgentService` ведёт зеркало сообщений для UI и аудита; источник истины по
  диалогу — ядро. Переход на read-through — следующий шаг.
- `OpenAiClient`, `ContextBuilder` и админские AI-настройки остаются для
  генератора шаблонов и старых тестов; на Кики они больше не влияют.
- Rate limit 30/мин остаётся на CRM-маршруте; ядро дополнительно проверяет
  токен канала и `data_class`.
