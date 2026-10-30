# PMS-ландшафт 2026: шаблоны, rich text, AI-агенты, API и MCP

Что из платных project-management систем реально нужно соло-практику, а что — корпоративный балласт. Срезка: 31.10.2026.

## Как читать и насколько можно верить

Сеть режет часть доменов: `developers.asana.com`, `developer.clickup.com`, `monday.com`, `airtable.com`, `wrike.com`, `height.app` не отвечают или отдают заглушку:

- ✅ — проверено по открывшемуся первоисточнику: официальная дока, OpenAPI-спека, репозиторий, живой MCP-эндпоинт.
- ⚠️ — восстановлено по вторичным источникам; первоисточник недоступен, детали могли измениться.

У Asana продуктовые страницы открываются, а `developers.asana.com` — нет; зато официальная OpenAPI-спека на GitHub содержит ~190 путей, включая шаблоны, агентов, AI Studio и audit log.

## Сравнительная таблица

| Продукт | Шаблоны | Rich text | AI-агенты | Публичный API | MCP | Соло-фит |
|---|---|---|---|---|---|---|
| **Asana** | Проектные + задачные, галерея, даты-переменные, роли, зависимости ✅ | HTML-описания, rich text в задачах и комментариях ✅ | AI Teammates (30+ готовых), AI Studio, Asana Dash ✅ | REST, PAT/OAuth2, offset-пагинация, webhooks, audit log ✅ | Официальный remote, v2 ✅ (эндпоинт живой) | Средний: мощно, но тяжело |
| **ClickUp** | Space/Folder/List-шаблоны, Template Center, PMO-шаблоны ⚠️ | Rich text + Docs-блоки, чек-листы ⚠️ | Brain, Super Agents: скоупы, approval mode ⚠️ | REST v2, pk_-токен/OAuth без refresh и скоупов, 100 req/min ✅* | Официальный, `mcp.clickup.com` ⚠️ | Средний: много функций, много шума |
| **monday.com** | Board-шаблоны (колонки, группы, автоматизации) ⚠️ | Docs + rich text в updates ⚠️ | monday AI, «agentic era», external agents ⚠️ | GraphQL, токен/OAuth, complexity-лимиты ⚠️ | Официальный hosted, OAuth, read-only режим ✅ | Низкий: заточен под процессы команд |
| **Notion** | Database-шаблоны: свойства + контент, повторяющиеся, вложенность ≤3 ✅ | Блочный редактор, любые блоки и embeds ✅ | Агенты (personal, custom, external), память, skills ✅ | REST, `Notion-Version` обязателен, cursor-пагинация ✅ | Официальный, ~25 инструментов ✅ | Высокий для контента, низкий для учёта |
| **Linear** | Проектные (вехи + задачи) и issue-шаблоны с placeholder-текстом и form-полями ✅ | Markdown-подобный rich text ✅ | Linear Agent, skills, Loops, coding sessions ✅ | GraphQL, 2 500 req/ч на ключ, cursor ✅ | Официальный, OAuth 2.1 + readonly ✅ | Высокий, если работа = задачи |
| **Jira** | Проектные шаблоны при создании; шаблонов задач нативно нет ⚠️ | ADF (JSON-документ), wiki markup в v2 ✅ | Rovo: агенты, Studio, automation flows ✅ | REST v3, OAuth2 3LO/Forge/токен, points-квоты ✅ | Официальный v2, permission groups ✅ | Низкий: оверкилл |
| **Basecamp** | Проектные (dock-инструменты), card table и to-do list шаблоны ✅ | Rich text в сообщениях и to-do ⚠️ | Нет агента; agent-аккаунты + Skills/CLI ✅ | REST, OAuth2, flat routes, Link-пагинация, 50 req/10 c ✅ | Официального нет ⚠️ | Высокий по простоте, низкий по API |
| **Wrike** | Проектные/папки/задачи, blueprints ⚠️ | Rich text ⚠️ | AI Agents (февраль 2026) ⚠️ | REST v4, OAuth2/постоянный токен ⚠️ | Есть help-статья про MCP ⚠️ | Низкий: enterprise-процессы |
| **Airtable** | Record-шаблоны с под-записями, галерея баз ✅ | Rich text/long text поля, не редактор документов ✅ | Omni (multi-step), AI-поля ✅ | REST, PAT/OAuth, 5 rps/base, 50 rps/токен ✅ | Официальный, права зеркалятся ✅ | Средний: база, не PMS |
| **Smartsheet** | Галерея шаблонов ⚠️ | Rich text в ячейках ⚠️ | Smart Assist ⚠️ | REST 2.0, токен/OAuth, token+offset пагинация ✅ | Официальный, 30+ инструментов ✅ | Низкий: табличная логика |

\* лимиты ClickUp — по интеграционной документации, не по первоисточнику.

## 1. Шаблоны: что внутри, как применяются, чем лечат расплывчатость

Шаблон в зрелых продуктах — это не «заготовка текста», а контракт: он фиксирует структуру работы (даты, роли, зависимости, артефакты) и оставляет человеку только содержательную часть. Разница между продуктами — в том, сколько именно структуры шаблон умеет протащить.

**Asana.** Самый богатый набор полей в шаблоне задачи: имя, `description`/`html_description` (rich text), исполнитель, подписчики, проект, теги, кастомные поля, вложения, подзадачи, зависимости и `relative_start_on` / `relative_due_on` — сроки считаются в днях от момента создания задачи, а не календарём ✅ ([TaskTemplateRecipe](https://raw.githubusercontent.com/Asana/openapi/master/defs/asana_oas.yaml)). Проектный шаблон добавляет `requested_dates` (переменные дат, обязательные при инстанцировании), `requested_roles`, вехи, статус, руководителя и участников; инстанцирование — асинхронная job `POST /project_templates/{gid}/instantiateProject` с флагом `is_strict`, который падает, если не заполнена переменная даты ✅. Обратная операция — `POST /projects/{gid}/saveAsTemplate`. Галерея шаблонов публичная, с «AI workflow templates» ✅ ([asana.com/templates](https://asana.com/templates)).

**Linear.** Два уровня. Проектный шаблон задаёт имя, описание, команду, статус, лида, участников, инициативы, вехи и список задач, включая подзадачи; шаблон команды доступен только ей, workspace-шаблон — всем; можно назначить шаблон по умолчанию ✅ ([project templates](https://linear.app/docs/project-templates)). Issue-шаблоны интереснее для нашей задачи: обычный шаблон задаёт свойства и текст описания, причём текст можно размечать как **placeholder** — он подсвечивается автору как «заполни меня»; form-шаблон превращает создание задачи в форму с обязательными полями ✅ ([issue templates](https://linear.app/docs/issue-templates)). Это единственный из просмотренных продуктов, где «обязательность заполнения» встроена в шаблон, а не в процесс.

**Notion.** Шаблон живёт внутри базы: предзаполненные свойства (например, Priority = P1 и конкретный PM) плюс произвольное содержимое страницы — текст, изображения, embeds, подстраницы. Шаблоны бывают повторяющимися (daily/weekly/monthly/yearly) и вложенными до трёх уровней ✅ ([database templates](https://www.notion.com/help/database-templates)). Нюанс: relation в шаблоне лучше не заполнять, иначе все созданные страницы сошлются на один объект.

**Jira.** Шаблоны проектов есть на уровне создания проекта, и есть REST-ресурс project templates ✅ (виден в навигации [REST API v3](https://developer.atlassian.com/cloud/jira/platform/rest/v3/intro/)). А вот переиспользуемого шаблона задачи с предзаполненным описанием в Jira нативно нет — это делается приложениями и обходными путями ⚠️ ([KB про копирование проекта](https://support.atlassian.com/jira/kb/copy-jira-project-as-templates/), сторонние гайды).

**Basecamp.** Шаблон проекта — это, по сути, набор инструментов (dock): Message Board, To-dos, Docs & Files, Schedule, Card Table, каждый со своим содержимым ✅ ([templates API](https://github.com/basecamp/bc3-api/blob/master/sections/templates.md)). Инстанцирование идёт через «project construction» с именем и описанием; отдельно существуют шаблоны card table и to-do list. То есть Basecamp шаблонизирует **структуру проекта**, а не формулировки задач.

**ClickUp.** Шаблоны уровней Space / Folder / List, Template Center и отдельные PMO-шаблоны; переносят статусы, кастомные поля, представления, задачи и автоматизации ⚠️ ([Use List templates](https://help.clickup.com/hc/en-us/articles/6308883440407-Use-List-templates), [Find a template](https://help.clickup.com/hc/en-us/articles/6326080034199-Find-a-template)). Первоисточник закрыт Cloudflare, детали не сверены.

**monday.com.** Шаблон доски переносит колонки, группы, элементы и автоматизации; галерея шаблонов — основной способ онбординга ⚠️ ([monday templates](https://support.monday.com/hc/en-us/articles/360001362625-monday-com-templates)). MCP-сервер умеет создавать доски с колонками и группы, то есть шаблон собирается программно ✅ ([mondaycom/mcp](https://github.com/mondaycom/mcp)).

**Airtable.** Record-шаблон — предзаполненная запись плюс связанные под-записи (tasks/subtasks/milestones), применяется одним кликом; доступно на платных планах ✅ ([record templates](https://support.airtable.com/docs/using-record-templates-in-airtable)). Это ближе к «пакету работ», чем к шаблону задачи.

**Как шаблоны борются с расплывчатыми формулировками.** Механизмы, которые я нашёл, выстраиваются в четыре уровня:

1. **Предзаполненный текст** (Asana html_description, Notion body, Linear placeholder). Помогает, только если автор шаблона написал хороший текст.
2. **Обязательные поля** (Linear form templates, Asana `is_strict` + `requested_dates`, Notion form-views). Заставляют ввести данные, но не проверяют смысл.
3. **Структурные якоря** — относительные сроки, зависимости, роли, вехи. Убирают «сделать когда-нибудь»: задача не может существовать без даты и владельца.
4. **Прикреплённая автоматизация** — правило или flow, который срабатывает на переход статуса (Asana rules, monday automations, Jira automation, ClickUp automations). Это уже не шаблон, а исполняемая часть процесса.

Вывод: ни один продукт не проверяет, что описание задачи осмысленное. Максимум — требует заполнить поле. Для CRM это означает, что «защита от воды» — наша ответственность: шаблон должен содержать не только заголовок, но и чек-лист результата и критерии приёмки.

## 2. Rich text: блочные редакторы против HTML

Здесь рынок расколот на четыре модели, и выбор влияет на то, как с текстом будет работать LLM.

**Структурированный документ.** Jira хранит описания в Atlassian Document Format — JSON-дерево с типизированными нодами (paragraph, bulletList, table, panel, status, media) и марками ✅ ([ADF](https://developer.atlassian.com/cloud/jira/platform/apis/document/structure/)). В API v3 вы отдаёте и получаете ADF, в v2 — wiki markup. Плюс: предсказуемый парсинг, можно достать чек-лист или таблицу как структуру. Минус: писать ADF руками больно, а «свободного» HTML нет.

**Блочный редактор.** Notion — эталон: страница это массив блоков, шаблон может содержать любой тип блока, включая embeds и подстраницы ✅. ClickUp Docs и monday Docs идут по тому же пути ⚠️. Плюс: богатый контент и AI-навигация по блокам. Минус: API отдаёт блоки, а не готовый HTML, и собирать из них документ — отдельная работа.

**HTML-описания.** Asana отдаёт `html_description` и поддерживает rich text с марками ✅. Basecamp хранит rich text в сообщениях и to-do ⚠️. Плюс: просто рендерить. Минус: чек-листы и таблицы — уже не структура, а разметка.

**Поля вместо документа.** Airtable и Smartsheet: rich text — тип поля, а не редактор ✅. Для учёта это честнее: описание не конкурирует со структурой записи.

**Чек-листы** как отдельная сущность есть у ClickUp, monday (subitems), Notion (to-do блоки); у Asana чек-лист заменяют подзадачи. Вывод: если нужен машинно-читаемый «definition of done», надёжнее отдельная сущность, а не список в тексте.

## 3. AI: кто именно, что может, кто утверждает

По автономности продукты делятся на три слоя.

**Слой 1 — ассистент внутри интерфейса.** Пишет текст, суммирует, ищет (ClickUp Brain ⚠️, monday AI ⚠️, Notion AI, Asana AI, Jira Rovo Chat, Wrike ⚠️).

**Слой 2 — конструктор автоматизаций.** Asana AI Studio — no-code сценарии, у которых есть API-ресурсы `ai_studio/runs` и `ai_studio/seats` ✅, то есть запуски видны и управляемы извне. Jira Rovo Studio — агенты, automation flows и no-code apps поверх Teamwork Graph, с триггерами, ветками и условиями ✅ ([Rovo Studio](https://support.atlassian.com/rovo/docs/studio/)). ClickUp Super Agents — агенты со скоупами («где агенту можно действовать») и approval mode: перед отправкой клиенту или изменением записи требуется подтверждение человека ⚠️ ([разбор Super Agents](https://clickup.com/ja/blog/547422/super-agents)).

**Слой 3 — «коллега» с памятью и правами.** Asana AI Teammates — 30+ предустановленных агентов под функции, плюс Asana Dash как «AI chief of staff» и отдельный разговор про AI governance для админов ✅ ([asana.com/product/ai](https://asana.com/product/ai)). Linear Agent — чат (⌘J), упоминание `@Linear` в комментариях, сохранение удачных сценариев в skills, фоновые Loops по расписанию или событиям, coding sessions; работает строго в рамках прав пользователя ✅ ([Linear Agent](https://linear.app/docs/linear-agent)). Notion — personal agent, Custom Agents с запуском по расписанию/триггеру, «instructions page» и память, внешние агенты; в 3.7 добавили skills для команды ✅ ([Notion 3.0](https://www.notion.com/releases/2025-09-18)).

**Human approval.** Явное подтверждение нашёл у трёх: ClickUp approval mode ⚠️, Atlassian MCP (`executeRead` / `executeWrite` / `executeDestructive`, тип execution задаёт уровень подтверждения) ✅ ([supported tools](https://support.atlassian.com/atlassian-rovo-mcp-server/docs/supported-tools/)), monday MCP — read-only режим ✅. У Asana, Linear и Notion отдельного примитива нет: ставка на то, что агент не выходит за права пользователя.

**Permissions/scoping.** Общий паттерн 2026 года: агент наследует права человека, а не работает от суперпользователя. Linear Agent «operates within your existing permissions» ✅, Notion Agents «always within your permissions» ✅, Airtable MCP «your MCP permissions mirror the same permission level(s)» ✅, Rovo «respecting your existing permissions» ✅.

**Audit/history.** Отдельный audit log как API есть у Asana (`audit_log_events`) ✅; у Jira — через Atlassian Guard ⚠️; у Airtable audit logs появились в Fall 2026 ⚠️ (по заголовку релиза). У остальных история действий агента живёт в истории задачи или страницы, а не в отдельном журнале.

**Memory/context.** Notion — память на страницах и базах плюс `notion-memory-search` в MCP ✅. Linear — guidance (как называть issues, что включать в баг-репорт) на уровне workspace/team/personal ✅. Jira — Teamwork Graph как контекст людей, работы и контента ✅.

**Suggested next actions.** Явно заявлены у Linear (triage-рекомендации, «что в зоне риска») ✅ и Notion (план проекта) ✅. У остальных это часть промпта, а не фича.

**Height** (за пределами девятки) — продукт с AI-ядром и агентами как основным интерфейсом; публичный API есть по сторонним источникам, MCP не подтверждён ⚠️.

## 4. Публичные API: пять осей сравнения

**Транспорт.** REST у Asana, ClickUp, Notion, Jira, Basecamp, Airtable, Smartsheet, Wrike; GraphQL только у Linear и monday ✅/⚠️. Для интеграции это значит: у большинства предсказуемые ресурсы и HTTP-коды, у Linear и monday — один эндпоинт и выборка полей запросом.

**Аутентификация.** Asana — personal access token (Bearer) или OAuth 2.0 authorization code ✅. ClickUp — личный `pk_...` токен или OAuth, причём **токены бессрочные, гранулярных скоупов и refresh-flow нет** ✅ (по интеграционной доке), что важно для мультитенантности. Notion — internal connection token, PAT или OAuth, заголовок `Notion-Version` **обязателен** ✅. Linear — OAuth 2.0 со скоупами (`read`, `write`, `issues:create`, `comments:create`, `admin`) или API-ключ ✅. Jira — OAuth 2.0 3LO, Forge/Connect или API-токен, гранулярные скоупы ✅. Basecamp — OAuth 2.0 Bearer плюс обязательный `User-Agent` с контактом ✅. Airtable — PAT или OAuth ✅. Smartsheet — токен или OAuth 2.0 (3-legged) ✅.

**Гранулярность ресурсов.** Asana — задачи, проекты, шаблоны, портфели, цели, кастомные поля, вебхуки, audit log; ~190 путей в спеке ✅. Jira — issue, project, board, sprint, worklog, webhook, project template ✅. Basecamp — плоские маршруты: ресурс доступен по своему id без проекта в URL ✅. Notion — блоки, страницы, базы, data sources, комментарии, views ✅.

**Пагинация.**
- offset/limit + `next_page` (Asana) ✅;
- `startAt` / `maxResults` / `total` / `isLast` (Jira) ✅;
- cursor `start_cursor` / `next_cursor`, максимум 100 (Notion) ✅;
- cursor, по умолчанию 50 (Linear) ✅;
- `page` по 100 задач, комментарии — парой `start`+`startId` (ClickUp) ✅;
- Link-заголовок с geared-пагинацией 15/30/50/100 (Basecamp) ✅;
- offset до 100 записей (Airtable) ✅;
- token-based и offset-based в одном API (Smartsheet) ✅.

**Вебхуки.** Есть у всех: Asana (`/webhooks`), Jira (dynamic webhooks), Linear (Issues, Projects, Documents, Cycles…), Notion, Basecamp, ClickUp (HMAC-SHA256 по телу, заголовок `X-Signature`), Airtable, Smartsheet, monday ⚠️. Для соло-практики это ключевая вещь: не polling, а событие.

**Лимиты.**
- Notion: 180 запросов/мин на соединение (600 на Business/Enterprise) плюс общий лимит workspace ✅.
- Linear: 2 500 запросов/час на API-ключ, 5 000 на OAuth-приложение, leaky bucket ✅.
- ClickUp: 100 req/min (Free–Business), 1 000 (Business Plus), 10 000 (Enterprise) ✅ (вторичный источник).
- Basecamp: первый лимит — 50 запросов за 10 секунд на IP, дальше динамически ✅.
- Airtable: 5 rps на базу и 50 rps на PAT ✅.
- Jira: points-based квоты — 65k points/hour глобально, per-tenant 100k + 10/user (Standard), 130k + 20/user (Premium), 150k + 30/user (Enterprise), потолок 500k; плюс burst-лимит на каждый эндпоинт отдельно ✅.
- Asana: cost limits и 429; точные цифры в `/docs/rate-limits`, недоступном из этой сети ⚠️.
- Smartsheet: лимиты по токену, числа не сверены ⚠️.

**Версионирование и OpenAPI.** Notion — версия датой в заголовке (`2026-03-11`) ✅. Jira — `/rest/api/3` (ADF) против `/rest/api/2` (wiki markup) ✅. Linear — changelog и GraphQL-схема в Apollo Studio ✅. Asana — OpenAPI в GitHub, обратная совместимость через `opt_fields` ✅. Basecamp — OpenAPI в SDK ✅. Smartsheet — `/2.0` в пути ✅. ClickUp — v2 без внятной политики версий ⚠️.

## 5. MCP: у кого официальный сервер и что он умеет

К концу 2026 MCP стал гигиеническим минимумом: у восьми из десяти продуктов есть **официальный** сервер, почти всегда remote + OAuth. Живые эндпоинты без токена отвечают 401/403/405, то есть существуют: `mcp.asana.com/v2/mcp`, `mcp.atlassian.com/v2/mcp`, `mcp.linear.app/mcp`, `mcp.notion.com/mcp`, `mcp.airtable.com/mcp`, `mcp.clickup.com/mcp` (302), `mcp.smartsheet.com` (документирован) ✅. Это подтверждает наличие серверов, но не их инструменты.

| Продукт | Транспорт / эндпоинт | Авторизация | Что умеет |
|---|---|---|---|
| **Asana** | remote, `mcp.asana.com/v2/mcp` ✅ | OAuth | Доступ к задачам/проектам; отдельный справочник инструментов в доке ⚠️ |
| **Atlassian (Jira)** | remote, `mcp.atlassian.com/v2/mcp` ✅ | OAuth 2.1 или API-токен ✅ | Jira + Confluence + JSM + Bitbucket + Loom; permission groups; **deferred tools** — клиенту отдаётся малый набор, остальное через `discover`/`execute`; `executeRead/Write/Destructive` с подтверждением ✅ |
| **Linear** | remote, `mcp.linear.app/mcp` (+ `/readonly`) ✅ | OAuth 2.1 + DCR, Bearer или API-ключ ✅ | Поиск/создание/обновление issues, projects, comments ✅ |
| **Notion** | remote, `mcp.notion.com/mcp` ✅ | OAuth; workspace-owned connections с allowlist инструментов ✅ | ~25 инструментов: search, fetch, create/update pages, query data sources, comments, views, memory, connector-search ✅ |
| **monday** | hosted, `mcp.monday.com/mcp` ✅ | OAuth или Bearer-токен ✅ | create_item, change_item_column_values, create_board/group/column, create_form; read-only режим; Dynamic API Tools (beta) — произвольные GraphQL-запросы ✅ |
| **ClickUp** | `mcp.clickup.com/mcp` ⚠️ | OAuth ⚠️ | Набор инструментов не сверен (домен закрыт) ⚠️ |
| **Airtable** | `mcp.airtable.com/mcp` ✅ | OAuth | Чтение/запись записей, создание баз; права зеркалят права пользователя ✅ |
| **Smartsheet** | `mcp.smartsheet.com` (US/EU) ✅ | API-токен или OAuth ✅ | 30+ инструментов для листов, строк, отчётов ✅ |
| **Wrike** | сервер есть ⚠️ | ⚠️ | ⚠️ |
| **Basecamp** | **официального MCP нет** (в README только Skills/CLI) ⚠️ | — | Вместо MCP — [Basecamp Skills](https://github.com/basecamp/skills) и CLI; агент подключается как отдельный участник с client-credentials токеном и урезанными правами (не видит вебхуки, не администрирует аккаунт) ✅ |

В дизайн стоит утащить два приёма: **read-only эндпоинт** (Linear `/mcp/readonly`) и **отложенную выдачу инструментов** (Atlassian: 5–10 в контексте, остальные через `discover`) — второе решает проблему «MCP съел весь контекст».

## 6. Что из этого нужно соло-практику

**Оверкилл.** Портфели и программы, resource management, SSO/SCIM, кастомные роли, audit log для комплаенса, velocity, workflow-конструктор на 20 статусов, несколько воркспейсов. Всё это нужно для координации между людьми, которых у самозанятого нет.

**Критично.**
- Шаблон задачи с чек-листом результата и критериями приёмки — не «заголовок + описание», а структура.
- Шаблон проекта/пакета услуг с относительными сроками: «аудит → 2 дня», «правки → 3 дня», без ручного пересчёта дат.
- Клиентский портал: клиент видит свои задачи и счёт, не видит чужих. У PMS это «guest»-роль, у нас — вторая половина продукта.
- Счёт + оплата в один клик. Ни один PMS этого не делает нормально для РФ; это наше преимущество, а не повод догонять их.
- Событийная интеграция (webhooks) вместо polling: уведомление клиенту при смене статуса, обновление счёта при оплате.
- AI для черновика ТЗ и follow-up, но с обязательным подтверждением: цена ошибки — репутация перед клиентом.

**Приятно, но позже.** Публичная галерея шаблонов, form-шаблоны с обязательными полями, MCP-сервер, память агента, skills.

## 7. Что внедряем в CRM самозанятого

Приоритеты: **P0** — без этого сценарий не работает; **P1** — заметно ускоряет; **P2** — можно отложить до первых десяти клиентов.

| # | Фича | Зачем | MVP scope | Приоритет |
|---|---|---|---|---|
| 1 | Шаблон задачи со структурой: цель, чек-лист, критерии приёмки, оценка | Лечит главную болезнь — «сделай красиво» без границ. Чек-лист отдельной сущностью, а не текстом | `task_templates` (name, description_md, checklist jsonb, estimate_minutes, default_priority); применение — создание задачи с копией чек-листа | P0 |
| 2 | Шаблон проекта / пакета услуг с относительными сроками | Повторяемые услуги (аудит, лендинг, поддержка) не должны пересобираться руками | `project_templates` + `project_template_tasks` (`offset_days` от старта); инстанцирование одной транзакцией | P0 |
| 3 | Обязательные поля шаблона (required-fields) | Не даёт создать задачу без срока, результата и критериев | Флаг `required` у полей шаблона; валидация в FormRequest + подсветка в UI | P0 |
| 4 | Журнал действий по задаче/счёту/оплате | Без него нельзя ни разбирать споры, ни пускать агента писать | Таблица `activity_log` (subject, actor, action, diff jsonb, ip); запись из Actions | P0 |
| 5 | Webhooks наружу (status changed, invoice paid) | Интеграции и уведомления без polling | Подписки + подпись HMAC-SHA256 + retry с backoff + журнал доставок | P1 |
| 6 | AI-черновик описания задачи из шаблона | Ускоряет постановку, но не заменяет человека | Кнопка «черновик» → LLM с контекстом шаблона и клиента → правка в редакторе → сохранение только после подтверждения | P1 |
| 7 | Проверка заполненности агентом (lint задачи) | Ловит «воду» до отправки клиенту | Правила + LLM-проверка: есть ли результат, срок, критерий; предупреждение, не блок | P1 |
| 8 | Rich text с чек-листами и вложениями в задаче и комментарии | Клиенту нужно видеть прогресс, а не переписку | Редактор на TipTap, санитайз HTML, вложения через существующий storage | P1 |
| 9 | MCP-сервер CRM (read-first) | Агент/IDE получают доступ к задачам и счетам без ручных экспортов | Read-only инструменты (`list_tasks`, `get_invoice`, `search_clients`), OAuth/PAT, отдельный scope | P2 |
| 10 | AI follow-up по просроченным задачам с очередью подтверждений | Возвращает деньги, которые теряются в «потом напомню» | Планировщик → черновик письма → очередь approvals → отправка | P2 |
| 11 | Версионирование шаблонов | Правка шаблона не должна менять уже созданные задачи | `template_versions`, `template_id` + `version` у задачи | P2 |
| 12 | Внутренняя галерея шаблонов (без маркетплейса) | 10–20 своих шаблонов экономят больше, чем чужие 500 | Список + теги + «использовать»; без публикации наружу | P2 |

## Sources

**Asana**
- [Asana OpenAPI spec (GitHub)](https://raw.githubusercontent.com/Asana/openapi/master/defs/asana_oas.yaml) — шаблоны, агенты, AI Studio, audit log, пагинация, auth.
- [Asana templates gallery](https://asana.com/templates)
- [Asana AI](https://asana.com/product/ai)
- [Asana AI workflow automation](https://asana.com/uses/ai-workflow-automation)
- [Using Asana's MCP Server](https://developers.asana.com/docs/using-asanas-mcp-server) ⚠️

**ClickUp**
- [Use List templates](https://help.clickup.com/hc/en-us/articles/6308883440407-Use-List-templates) ⚠️
- [Find a template](https://help.clickup.com/hc/en-us/articles/6326080034199-Find-a-template) ⚠️
- [Webhook signature](https://developer.clickup.com/docs/webhooksignature) ⚠️
- [ClickUp: авторизация, пагинация, лимиты, вебхуки](https://docs.automate.ax/reference/integrations/clickup/authorization-and-webhooks)
- [ClickUp Super Agents](https://clickup.com/ja/blog/547422/super-agents) ⚠️

**monday.com**
- [monday MCP (официальный репозиторий)](https://github.com/mondaycom/mcp)
- [Rate limits — monday API](https://developer.monday.com/api-reference/docs/rate-limits) ⚠️
- [monday: agentic era](https://monday.com/blog/product/welcome-to-the-agentic-era-at-monday-com/) ⚠️
- [monday templates](https://support.monday.com/hc/en-us/articles/360001362625-monday-com-templates) ⚠️

**Notion**
- [Database templates](https://www.notion.com/help/database-templates)
- [Notion 3.0: Agents](https://www.notion.com/releases/2025-09-18)
- [Notion API: intro](https://developers.notion.com/reference/intro)
- [Notion API: request limits](https://developers.notion.com/reference/request-limits)
- [Notion API: versioning](https://developers.notion.com/reference/versioning)
- [Notion MCP: overview](https://developers.notion.com/guides/mcp/overview)
- [Notion MCP: supported tools](https://developers.notion.com/guides/mcp/mcp-supported-tools)

**Linear**
- [Project templates](https://linear.app/docs/project-templates)
- [Issue templates](https://linear.app/docs/issue-templates)
- [Linear Agent](https://linear.app/docs/linear-agent)
- [MCP server](https://linear.app/docs/mcp)
- [Rate limiting](https://linear.app/developers/rate-limiting)
- [OAuth 2.0](https://linear.app/developers/oauth-2-0-authentication)
- [Webhooks](https://linear.app/developers/webhooks)

**Jira / Atlassian**
- [Jira REST API v3: intro](https://developer.atlassian.com/cloud/jira/platform/rest/v3/intro/)
- [Jira rate limiting](https://developer.atlassian.com/cloud/jira/platform/rate-limiting/)
- [Atlassian Document Format](https://developer.atlassian.com/cloud/jira/platform/apis/document/structure/)
- [Rovo Studio](https://support.atlassian.com/rovo/docs/studio/)
- [Atlassian MCP: getting started](https://support.atlassian.com/atlassian-rovo-mcp-server/docs/getting-started-with-the-atlassian-remote-mcp-server/)
- [Atlassian MCP: supported tools](https://support.atlassian.com/atlassian-rovo-mcp-server/docs/supported-tools/)
- [Jira: создание шаблона из проекта (KB)](https://support.atlassian.com/jira/kb/copy-jira-project-as-templates/)

**Basecamp**
- [Basecamp API reference (GitHub)](https://github.com/basecamp/bc3-api) — auth, пагинация, лимиты, agent-аккаунты.
- [Basecamp templates API](https://github.com/basecamp/bc3-api/blob/master/sections/templates.md)
- [Basecamp Skills](https://github.com/basecamp/skills)

**Airtable**
- [Airtable: лимиты API](https://support.airtable.com/articles/7735693959-managing-api-call-limits-in-airtable)
- [Airtable: record templates](https://support.airtable.com/docs/using-record-templates-in-airtable)
- [Airtable: Omni AI](https://support.airtable.com/docs/using-omni-ai-in-airtable)
- [Airtable: MCP server](https://support.airtable.com/docs/using-the-airtable-mcp-server)
- [Airtable Fall 2026 release](https://www.airtable.com/articles/fall-release) ⚠️

**Smartsheet**
- [Smartsheet API: introduction](https://developers.smartsheet.com/api/smartsheet/introduction)
- [Smartsheet API: authentication](https://developers.smartsheet.com/api/smartsheet/guides/basics/authentication)
- [Smartsheet API: pagination](https://developers.smartsheet.com/api/smartsheet/guides/basics/pagination)
- [Smartsheet MCP: install](https://developers.smartsheet.com/ai-mcp/smartsheet/install-the-smartsheet-mcp-server)

**Wrike / Height**
- [Wrike MCP (help-статья)](https://help.wrike.com/hc/en-us/articles/34605829225746) ⚠️
- [Wrike AI Agents, пресс-релиз](https://www.businesswire.com/news/home/20260205150291/en/) ⚠️
- [Wrike API v4](https://developers.wrike.com/) ⚠️
- [Height](https://height.app/) ⚠️
