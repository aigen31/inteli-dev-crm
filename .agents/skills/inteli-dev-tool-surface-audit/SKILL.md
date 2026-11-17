---
name: inteli-dev-tool-surface-audit
description: Use when changing CRM, the inteli-dev.ru site, the Kiki kernel, MCP servers or AI assistants and you need to verify that every current service capability is still exposed in the right place. Audits MCP tools, internal provider tools, kernel-native tools and assistant access; detects stale/removed tools and missing new ones.
license: MIT
metadata:
  version: "1.0.0"
  domain: platform
  triggers: MCP audit, tool parity, Kiki tools, provider profile, API surface, stale MCP tool, inteli-dev services
  role: auditor
  scope: cross-service
---

# Аудит поверхности инструментов inteli-dev

Единый процесс проверки: **доменная возможность → MCP-инструмент → internal provider → ядро → ассистент**. Цель — ни один актуальный функционал не потерян, ни один удалённый не остался враньём.

## Когда использовать

- После изменения CRM, сайта, ядра или MCP-сервера.
- Перед релизом/деплоем.
- Когда ассистент говорит «инструмента нет», а функционал в сервисе есть.
- Когда MCP-клиент видит старый список tools.
- При добавлении/удалении доменной сущности или действия.

## Границы сервисов

| Сервис | Владеет | Наружу |
|---|---|---|
| CRM | клиенты, проекты, задачи, счета, отчёты, доступы | MCP `/api/mcp`; internal `/api/internal/assistant` |
| Сайт | контент, услуги, кейсы, статьи, прайс, статус, заявки | MCP `/mcp`; internal `/internal/assistant` |
| Ядро Кики | модель, история, tool loop, маршрутизация | HTTP `/v1/*` |
| DSH/внешние агенты | клиент MCP | CRM MCP и/или сайт MCP |

Ассистент сервиса получает **provider tools** от своего сервиса. Внешний агент получает **MCP tools**. Это разные контракты; нельзя считать, что MCP-инструмент автоматически доступен Кики, и наоборот.

## Правило полноты

1. У каждой актуальной доменной операции должен быть владелец: MCP tool, provider tool, или явное решение «только UI» (секреты, опасные настройки).
2. Если функционал переработан — переименуйте/обновите tool и схему, не оставляйте старый алиас без причины.
3. Если функционал удалён — удалите tool или верните понятную ошибку/алиас с deprecated-пометкой.
4. Read-only и write-tools разделяются; деньги/разрушение требуют подтверждения.
5. Секреты, API-токены и платёжные ключи не выставляются в MCP/provider.
6. Изменение поверхности сопровождается тестом и записью в `references/audit-YYYY-MM-DD.md`.

## Workflow

1. **Зафиксируйте изменения**: `git log -1 --name-only` в CRM, ядре и сайте; выпишите затронутые доменные операции.
2. **Соберите поверхность**:
   - CRM MCP: `app/Services/Mcp/CrmMcpService.php` (`$this->tool(...)`);
   - CRM Kiki: `app/Services/Ai/CrmToolbox.php` (`catalog()`);
   - сайт MCP: `src/mcp.rs` (`TOOLS`);
   - сайт provider: `src/api/kernel.rs` (`profile`);
   - ядро: `src/service.rs` (`price_tool` и native tools).
3. **Запустите аудит**:
   ```bash
   python3 .agents/skills/inteli-dev-tool-surface-audit/scripts/audit_tools.py \
     --crm /mnt/disk1/work/inteli-dev-infrastructure/inteli-dev-crm \
     --kernel /mnt/disk1/work/inteli-dev-infrastructure/inteli-dev-kiki-assistant-kernel-api \
     --site /mnt/disk1/work/inteli-dev-infrastructure/inteli-dev.ru
   ```
4. **Разберите отчёт**: critical = ассистент/ядро не видит существующий функционал; warning = расхождение MCP/provider или возможный stale.
5. **Исправьте поверхность**, а не только отчёт: добавьте tool, обновите schema/description, удалите stale, обновите prompt/labels.
6. **Проверьте**:
   - unit/feature tests;
   - живой `profile`/`tools/list` (токен и канал);
   - ассистент вызывает новый tool;
   - MCP-клиент видит новый список после переподключения.
7. **Запишите аудит** в `references/audit-YYYY-MM-DD.md`: что изменено, что удалено, что осталось.

## Проверки, которые нельзя пропускать

- `site MCP tools` ⊆ `site provider tools` (или provider явно генерирует все из MCP registry).
- Kiki CRM имеет tools для Reports, task budget/mass update, custom fields и текущих доменных действий.
- MCP CRM имеет read/write для всех актуальных сущностей; assistant-only tools (documents, plans) не обязаны быть в MCP.
- Kernel native `get_price_list` доступен всем каналам; provider prices не дублирует stale-копию.
- После переименования tool старый MCP-клиент получает ошибку с подсказкой, а не молчаливый сбой.
- Никакой tool не обещает действие, которого нет в домене.

## Reference guide

| Файл | Когда |
|---|---|
| `references/surfaces.md` | Карта контрактов, владельцев и известных исключений |
| `references/audit-2026-11-13.md` | Полный аудит последних изменений и остаточные gaps |
| `scripts/audit_tools.py` | Статический аудит и сравнение наборов tools |
