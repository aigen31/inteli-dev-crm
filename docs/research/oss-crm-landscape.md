# OSS CRM: кастомные поля, API, MCP и AI — что взять за основу

Обзор на октябрь 2026. Смотрел официальные доки и репозитории EspoCRM, Twenty, SuiteCRM, Odoo CRM, Krayin, Vtiger, CiviCRM и Atomic CRM. Задача — не выбрать «лучший CRM», а понять, как зрелые open-source проекты решают одну и ту же проблему: админ описывает поля и сущности, а система обязана хранить значения, фильтровать их, отдавать по API и не ломать права доступа.

## EspoCRM

Самый «платформенный» из списка. Пользовательские сущности и поля создаются через Entity Manager, а результат пишется в `custom/Espo/Custom` в виде JSON-метаданных: `entityDefs`, `scopes`, `clientDefs`, layouts. Имя кастомного поля получает префикс `c`, чтобы не конфликтовать с будущими версиями. После изменений нужен rebuild — он создаёт таблицы и колонки. Экспорт кастомизаций в extension — это и есть их версионирование; встроенных миграций «как в Laravel» нет.

Каталог типов большой: varchar, enum, text, date, datetime, currency, int, float, decimal (с 9.3), boolean, multi-enum, checklist, array, address, url, url-multiple, wysiwyg, file, image, attachment-multiple, number, auto-increment, barcode, foreign. Связи — отдельно: link, link-parent, link-multiple. Формул и rollup как типов полей нет; вычисления делаются Formula-скриптом до сохранения, поле помечается read-only, а для агрегатов используется `select`/`where` с join'ами или formula-функции.

Валидация задаётся параметрами поля: required, maxLength, pattern, min/max, After/Before для дат, набор валидаторов и Dynamic Logic (видимость, обязательность, read-only, зависимые опции). Область действия — конкретная сущность через `entityDefs.{Entity}.fields`. UI-размещение — отдельно от определения поля: detail/list layouts, Search Filters layout, `textFilterFields` для текстового поиска. Важная деталь: добавили поле — оно не появится в фильтрах, пока его не положат в Search Filters. Field-level ACL есть: в роли можно запретить чтение/редактирование конкретного поля.

Хранение — физическая колонка в таблице сущности. `notStorable` отключает колонку для вычисляемых полей, `dbType` переопределяет тип, `index` создаёт индекс. Это классический «wide table»: быстро читается и фильтруется, но каждое новое поле — DDL и rebuild, а очень широкая таблица упирается в лимиты Postgres и стоимость ALTER TABLE. EAV здесь сознательно не используют.

API: REST `api/v1/`, аутентификация по `X-Api-Key`, HMAC или Basic; пагинация `offset`/`maxSize`, фильтры через `where` (equals, in, contains, between, arrayAnyOf и т.д.), выбор полей через `select`. С 9.3 есть `/api/v1/OpenApi`, причём спецификация включает кастомные сущности и поля. Webhooks — с событиями `Entity.create/update/delete` и `Entity.fieldUpdate.Field`, подписью HMAC и ретраями. Документированного rate limit в ядре нет.

MCP — официальное расширение `ext-mcp`, работающее внутри самого EspoCRM. Инструменты: Find, Read, Create, Update, Delete, Record Stream, Create Post. Админ создаёт MCP-endpoint, выбирает, какие tools и поля выставлять, и каких пользователей пускать; ACL пользователя дополнительно фильтрует tools (нет права на создание Lead — нет `Create_Lead`). Аутентификация — API key, OAuth 2.0 заявлен с 10.1.

AI — платное расширение Intelligence: summary записи, Intelligent Paste (извлечение полей из текста), AI Email Composer и AI-функции формул (`classify`, `extract`, `summarize`, `prompt`). Доступ к фичам — через role scopes; контекст учитывает права: если у пользователя нет доступа к stream, история stream в промпт не попадает. Это модель, которую хочется повторить.

## Twenty CRM

Twenty строит data model как runtime-метаданные. Кастомные объекты и поля создаются в UI (Settings → Data Model), а API генерируется из схемы воркспейса: у каждого workspace свой PostgreSQL-schema, метаданные полей лежат в core-схеме (`fieldMetadata`), DDL workspace-схемы обновляется при изменении модели. По разбору репозитория (не официальная дока) значения хранятся в колонках таблицы объекта, а композитные поля вроде emails/phones/links — в JSONB. Официальная документация этого не фиксирует, поэтому считаю утверждение вероятным, но неподтверждённым.

Типы полей: Address, Array, Boolean, Currency, Date, Date & Time, Domain, Email, JSON, Links, Long Text, Multi-Select, Number, Phone, Rating, Relation, Select, Text. Формул и rollup в списке нет — вычисления живут в workflows. Валидация скромнее, чем у Espo: unique, обязательность, default для select, ограничения по именам; деактивация поля скрывает его из UI, но данные и API-доступ остаются.

UI: поле добавляется в объект, затем в нужные views; список и фильтры — часть сгенерированного интерфейса. Метаданные можно менять программно через Metadata API (`/rest/metadata/`, `/metadata/`), а не только руками.

API: REST `/rest/` и GraphQL `/graphql/`, отдельно Metadata API. Аутентификация — Bearer API key или OAuth; ключ можно привязать к роли. REST принимает `fields`, чтобы не тянуть все поля; по умолчанию при >200 читаемых полях возвращается 200 стандартных, кастомные — только по явному запросу. Batch — до 60 записей, rate limit — 100 запросов/мин. Webhooks — на create/update/delete всех объектов, включая кастомные, с HMAC-подписью. Статической OpenAPI-спеки нет: документация генерируется под воркспейс в интерактивном playground.

MCP — официальный, но alpha и не на всех воркспейсах. Endpoint `/mcp`, OAuth (рекомендуется) или API key. Вместо десятков узких tools — три мета-инструмента: `get_tool_catalog`, `learn_tools`, `execute_tool`. Права наследуются от пользователя (OAuth) или роли API-ключа.

AI: чат-бот по данным и агенты, которые выполняют многошаговые задачи (обогащение, письма, разбор пайплайна). Официально заявлено, что AI уважает permission model: агент видит только объекты и поля, разрешённые пользователю/роли. Проверять всё равно нужно.

## SuiteCRM

Классическая SugarCRM-школа. Поля добавляются через Studio (GUI) или вручную через vardefs в `custom/Extension/modules/<Module>/Ext/Vardefs/`. Кастомное поле получает суффикс `_c`, а значения хранятся не в основной таблице, а в `<module>_cstm`, связанной по `id_c`. Это позволяет обновлять ядро, не трогая основные таблицы, но каждая выборка требует JOIN, а индексы на кастомные поля официально «пока нельзя» — важное ограничение для фильтрации и сортировки.

Типы: varchar, text, int, decimal, bool, date, datetime, currency, relate, enum, multienum, file, url и т.д. Валидация — через vardefs (required, len, options, default) и логику; UI — Studio layouts, search defs, subpanels. Кастомные поля надо явно добавлять в layout и search defs, иначе их не видно.

API v8 — OAuth2 (client credentials, password, authorization code), JSON:API-подобный формат. Есть list/get/create/update/delete, relationships, meta endpoints (`/Api/V8/meta/modules`, `/meta/fields/{module}`, `/search-defs`, `/listview/columns`) и Swagger (`/Api/V8/meta/swagger.json`). Пагинация — `page[number]`/`page[size]`, сортировка, фильтры `filter[field][eq]` с `and/or`. Rate limit в ядре не документирован. Webhooks в open-source ядре не документированы — на Marketplace есть коммерческие расширения; это не «из коробки».

MCP — только community-проекты (например, suitecrm-mcp с OAuth2/OIDC и 24 tools), официального нет. AI — нативного нет; есть партнёрские GenAI-надстройки. AI придётся строить сбоку.

## Odoo CRM

Odoo не «CRM с кастомными полями», а ORM-платформа. Поля модели объявляются в Python; Studio создаёт поля с префиксом `x_studio_`, которые сохраняются как обычные колонки таблицы. Технически это 15 типов, в Studio — 20 (Char, Text, Integer, Float, Monetary, Html, Date, Datetime, Boolean, Selection, Binary/File/Image/Sign, Many2one, One2many, Many2many, Tags, Related, Lines и т.д.). Метаданные — `ir.model.fields`; через RPC можно создавать manual-поля (`state=manual`), но вычисляемые поля через `ir.model.fields` создать нельзя, а defaults/onchange ограничены.

Отдельно есть property fields: они не колонки, а «псевдополя», общие для записей с одним родителем (например, задачи одного проекта). Это интересный гибрид: настраиваемые поля без DDL, но с привязкой к parent record и без обычных колонок/индексов. Для CRM-лида property-поля дают дешёвую кастомизацию, но фильтрация и API-контракт сложнее, чем у обычных полей.

Валидация — на уровне поля (`required`, `size`, `domain`) и Python-констрейнтов; UI — views, groups, Studio drag-and-drop. Хранение — физические колонки; `x_studio_` не EAV. Плюс: нативные индексы, домены, SQL-производительность. Минус: ALTER TABLE на больших таблицах, блокировки, аккуратность с миграциями.

API — XML-RPC (и JSON-RPC) `/xmlrpc/2/...`, API key вместо пароля, `fields_get` для метаданных, `search_read` с domain, `limit`/`offset`. OpenAPI нет. Важно: для Odoo Online внешний API доступен только на плане Custom; для self-hosted ограничения нет. Webhooks — через Automated Actions (действие Webhook), отдельного subscription API нет; в 19 не проверял.

MCP — только community-модули в Odoo Apps Store (muk_mcp, mcp_server_oer и др.), официального ядра нет. AI — Odoo 19: Ask AI (читает, открывает views, но не меняет БД) и AI agents с Topics/Tools/Sources, которые уже могут создавать лиды. Права агента должны ограничиваться правами пользователя, но это стоит проверять отдельно: документация делает акцент на tools, а не на ACL-модели.

## Krayin CRM

Laravel-проект, у которого кастомные поля — не надстройка, а основа. Модель attribute-driven: таблица `attributes` описывает поле (code, type, entity type, required, unique, default), `attribute_values` хранит значение для конкретной записи. Это чистый EAV. Типы: text, textarea, price, boolean, date, datetime, email, phone, select, multiselect, lookup, image, file, address, subheading (layout-only). Есть Attribute Groups, Quick Add, валидация required/unique/numeric.

Плюс: админ добавляет поле без миграций, набор сущностей расширяется пакетами. Минус: значения не типизированы на уровне БД, фильтрация и сортировка требуют JOIN'ов и часто — нескольких, а сложные отчёты по кастомным полям упираются в производительность.

API — отдельный пакет `krayin/rest-api`: Sanctum bearer-токены, CRUD по Leads/Persons/Organizations/Quotes/Products, пагинация `page`/`per_page`, Swagger UI `/api/admin/documentation`. Отдельного metadata/attribute API в документации не видно; как именно REST отдаёт custom attributes, не подтверждено — скорее всего, потребуется собственный слой. MCP и нативного AI нет.

## Vtiger CRM

Наследник SugarCRM, поэтому схема похожая: `vtiger_field` хранит описание поля (fieldname, fieldlabel, uitype, columnname), а кастомные значения лежат в `vtiger_<module>cf` (например, `vtiger_accountscf`) с колонками `cf_<id>`. Это подтверждается старым обсуждением в официальной рассылке; архитектура с тех пор принципиально не менялась, но источник не свежий — помечаю как «исторически подтверждено, не проверено по текущей схеме».

Поля добавляются через Field Editor/Layout Editor: тип, picklist, mandatory, defaultValue, uitype; размещение — layouts, related lists, filters. Типы — varchar, text, int, decimal, bool, date, datetime, currency, picklist, multipicklist, phone, email, url, file, reference. Валидация — на уровне uitype и настроек поля, без формул.

API — webservice (`webservice.php`): challenge → md5(challenge + accessKey) → login → session; операции query/retrieve/create/update/delete/describe, пагинация limit/offset. REST-документация есть для облачной редакции. OpenAPI и rate limit не документированы. MCP — community-обёртки (npm `vtiger-mcp`, партнёрские proxy), официального нет. AI — Calculus AI, коммерческий продукт Vtiger, не часть open-source ядра.

## CiviCRM

CiviCRM — не sales-CRM, а CRM для некоммерческих организаций, и это влияет на модель данных. Кастомные поля всегда живут в «custom field set» (`civicrm_custom_group`), у сета есть scope: контакты, участники, взносы, события, отношения и т.д. Внутри — поля (`civicrm_custom_field`), значения — в `civicrm_custom_value` по строке на поле/запись. Это EAV, и APIv4 честно предупреждает: каждый custom group добавляет JOIN, а `custom.*` на большом числе групп можно упереться в лимит MySQL в 61 JOIN.

Типы: Text, Select, Radio, Yes/No, Checkbox, Multi-select, Advanced Multi-select, Autocomplete, Note (plain/rich), Integer, Number, Money, Date, State/Province, Country, File, Link, Contact Reference. Валидация — required, max length, даты, опции; UI — наборы полей, inline/tab, multi-record sets, профили, SearchKit. Поле можно деактивировать, не удаляя данные; изменить scope сета после появления данных нельзя.

APIv4: entity/action/params, мощные where/join/select, `Custom_<Group>` для multi-record, `Group.Field` для single-record. REST — `/civicrm/ajax/api4/{Entity}/{Action}`, auth через `X-Civi-Auth`/`Authorization`/`_authx`: API key, JWT или Basic (по умолчанию пароли выключены). Пагинация — limit/offset, общее число — `selectRowCount()`. OpenAPI нет, есть API Explorer. Webhooks в ядре не документированы. MCP — официального нет, есть community-обёртки; AI — нативного нет.

## Atomic CRM

Atomic CRM (marmelab) — React + Supabase, и он принципиально другой: кастомные поля не создаются админом в UI. Нужно добавить колонку в Supabase (Studio или SQL), обновить view, сгенерировать миграцию, поправить TypeScript-тип и форму. То есть «кастомное поле» здесь — это изменение кода и схемы, а не runtime-метаданные. Зато физические колонки, PostgREST, RLS и OpenAPI из коробки.

API — Supabase/PostgREST: `apikey` + JWT, `/rest/v1/<table>`, фильтры PostgREST (`status=eq.hot`), `select`, пагинация через Range/limit, OpenAPI по `/rest/v1/`. RLS — главный механизм прав, а не приложение. MCP — официальный, через Supabase Edge Functions и OAuth; умеет читать, создавать, обновлять записи и строить виджеты. AI-фич в самом CRM нет — только через MCP-клиента.

## Сравнение

| CRM | Кастомные поля | Хранение | API | MCP | AI |
|---|---|---|---|---|---|
| EspoCRM | UI + JSON-метаданные, ~25 типов, формулы, field ACL | Физ. колонки, `notStorable`, индексы | REST, key/HMAC, OpenAPI, webhooks | Официальный, tools + whitelist, ACL | Intelligence: summary/paste/composer, scopes |
| Twenty | UI + Metadata API, ~18 типов, без формул | Schema-per-workspace, колонки (+JSONB composite, не подтв.) | REST + GraphQL + Metadata API, 100 rpm | Официальный alpha, 3 мета-tools, OAuth | Чат и агенты, заявлено уважение прав |
| SuiteCRM | Studio/vardefs, `_c`, индексы на custom нельзя | Отдельная `_cstm`, JOIN | REST v8 OAuth2, Swagger | Community | Нет нативного |
| Odoo CRM | Studio `x_studio_`, 15/20 типов, properties | Физ. колонки; properties — не колонки | XML-RPC/JSON-RPC, API key, domains | Community-модули | Ask AI (read-only), agents |
| Krayin | UI attributes, 14 типов | EAV `attribute_values` | REST Sanctum, Swagger | Нет | Нет |
| Vtiger | Field/Layout Editor, uitype | `vtiger_field` + `_cf` | Webservice, session | Community/партнёрский | Calculus AI (коммерч.) |
| CiviCRM | Custom groups/fields, ~18 типов | EAV `civicrm_custom_value` | APIv4 + REST, key/JWT | Нет официального | Нет нативного |
| Atomic CRM | Только код + миграция | Физ. колонки Postgres | PostgREST, JWT, OpenAPI | Официальный, OAuth | Нет, только через MCP |

## Уроки для Laravel-CRM

**1. EAV и «всё в JSONB» — обе крайности плохи.** EAV (Krayin, CiviCRM) даёт бесконечную гибкость, но превращает любой список с фильтром в JOIN-конструктор, а типы и ограничения живут в приложении. Wide table (EspoCRM, Odoo) быстра и типизирована, но каждое поле — DDL, а админ-UI начинает управлять миграциями. Рабочий компромисс для одиночного Laravel-продукта: **гибрид**. Системные и «горячие» поля — обычные колонки; административные описательные поля — JSONB в `custom_values` (или уже существующий EAV); поля, по которым реально фильтруют и сортируют, — generated columns + индексы. PostgreSQL 16 это позволяет без EAV.

**2. «Intake status» — не обычное кастомное поле.** Это состояние процесса, а не атрибут. Если сделать его generic select, каждый проект будет заново придумывать переходы, цвета, запреты и поведение AI. Правильнее отдельная сущность: `intake_stages` (code, label, order, is_terminal, allowed_transitions) и `clients.intake_stage_id` как FK. Админ может менять названия, порядок и доступные переходы, но семантика «это стадия воронки» фиксирована. Кастомные поля остаются для «ИНН», «источник», «комментарий», «бюджет» — того, что не управляет процессом.

**3. Метаданные должны быть контрактом, а не UI-состоянием.** Нужна таблица `field_definitions`: `entity`, `key`, `type`, `purpose` (profile/intake/billing/internal), `options` JSONB, `validation` JSONB, `required`, `visible`, `searchable`, `filterable`, `sort_order`, `version`, `deprecated_at`. Ключ поля — immutable; переименование только через label. Удаление — только soft-delete после миграции данных. Это даёт API-контракт, по которому можно генерировать формы, фильтры, OpenAPI и tool-схемы для AI.

**4. API должен отдавать custom-поля как первоклассные.** Не «JSON-строка внутри record», а объект `custom: { intake_status: "new", inn: "..." }` плюс метаданные отдельным endpoint `/api/v1/meta/entities/{entity}/fields`. Фильтры — `filter[custom.inn]=...`, `filter[intake_stage]=...`; сортировка — только по полям с `sortable=true`. OpenAPI генерировать из метаданных (Scramble или аналог), чтобы AI и фронт видели один и тот же контракт. Sanctum + abilities, rate limit, idempotency key на POST, webhook outbox — минимальный набор, который в этих CRM уже стал нормой.

**5. AI не должен ходить в БД напрямую.** У EspoCRM и Twenty видно общий паттерн: tools генерируются из метаданных и прав, а не пишутся руками под каждую сущность. Для Laravel это означает MCP-сервер (или tool-layer) с инструментами `list_entities`, `describe_entity`, `search_records`, `get_record`, `create_record`, `update_custom_field`, `transition_intake`. Каждый tool проверяет policy по полю и действию, а переход стадии требует подтверждения человека. Никакого «SQL от LLM» — только параметризованные запросы через слой приложения.

**6. Версионирование — это не «накатить миграцию».** У EspoCRM версия кастомизаций — это extension; у Odoo — модуль; у Twenty — metadata API и DDL. Для небольшой CRM достаточно: `field_definitions.version`, журнал изменений, additive-only миграции, backfill-джобы и запрет удалять поле, пока есть данные. Если поле меняет тип — новая колонка + dual-write + переключатель чтения, а не `ALTER TYPE` на живой таблице.

## Что внедряем

В этом репозитории уже есть `custom_fields` и `custom_field_values` с типизированными колонками — это EAV, а не JSONB. Переписывать его в JSONB не нужно: для админских атрибутов он работает, а `intake_stage_id` всё равно должен быть отдельной колонкой и FK. Гибрид — целевое состояние, а не требование всё переписать.

**P0 — фундамент (1–2 недели).**
1. Оставить текущий EAV для админских полей; добавить `purpose`, `version`, `deprecated_at`, `filterable`/`sortable`.
2. `intake_stages` + `clients.intake_stage_id`: код, label, порядок, terminal, allowed transitions. Не generic select.
3. Metadata endpoint `/api/v1/meta/entities/{entity}/fields` + dynamic validation на Laravel Validator (required/type/options/regex/min-max).
4. REST: `custom` как объект, фильтры по `custom.*` и `intake_stage`, пагинация, сортировка только по разрешённым полям. OpenAPI из метаданных.
5. Аудит изменений: кто, когда, старое/новое значение, для полей и стадий.

**P1 — удобство и безопасность (2–4 недели).**
6. Проекционные колонки для 2–3 горячих полей (например, сумма) + индексы по `value_number`/`value_date`; остальное не индексировать.
7. Админ-UI: конструктор полей (тип, options, required, visibility, searchable/filterable), drag-and-drop в форме, предпросмотр.
8. Field-level permissions: роль × поле × read/edit; AI и API используют ту же policy.
9. Webhooks + outbox, idempotency, rate limit; экспорт/импорт с валидацией по метаданным.

**P2 — AI и развитие (после стабилизации).**
10. MCP/tool-layer: `describe_entity`, `search_records`, `get_record`, `update_custom_field`, `transition_intake`; allowlist полей, аудит, подтверждение переходов.
11. Версионирование полей: deprecated_at, backfill, dual-write при смене типа; запрет удаления с данными.
12. Вычисляемые/rollup-поля — не в JSONB на лету, а через очередной job и материализованную колонку, иначе списки будут тормозить.

Главный вывод: не копировать EAV и не строить «универсальный конструктор всего». Победили те, кто разделил **данные** (кастомные атрибуты), **процесс** (intake stages) и **контракт** (метаданные + API + tools). Для маленькой single-tenant Laravel-CRM этого достаточно, чтобы получить гибкость EspoCRM без его DDL-боли и гибкость Krayin без EAV-джойнов.

## Sources

- EspoCRM. Fields — https://docs.espocrm.com/administration/fields/
- EspoCRM. entityDefs — https://docs.espocrm.com/development/metadata/entity-defs/
- EspoCRM. Entity Manager — https://docs.espocrm.com/administration/entity-manager/
- EspoCRM. Custom Entity Type — https://github.com/espocrm/documentation/blob/master/docs/development/custom-entity-type.md
- EspoCRM. API Overview — https://docs.espocrm.com/development/api/
- EspoCRM. API Search Params — https://docs.espocrm.com/development/api-search-params/
- EspoCRM. Webhooks — https://docs.espocrm.com/administration/webhooks/
- EspoCRM. Roles / Field Level Security — https://docs.espocrm.com/administration/roles-management/
- EspoCRM. Intelligence — https://docs.espocrm.com/extensions/intelligence/
- EspoCRM. MCP extension — https://github.com/espocrm/ext-mcp
- Twenty. Fields — https://docs.twenty.com/user-guide/data-model/capabilities/fields
- Twenty. APIs — https://docs.twenty.com/developers/extend/api
- Twenty. Webhooks — https://docs.twenty.com/developers/extend/webhooks
- Twenty. MCP Server — https://docs.twenty.com/user-guide/ai/capabilities/mcp
- Twenty. AI — https://docs.twenty.com/getting-started/core-concepts/ai
- Twenty. Data Model (developers) — https://docs.twenty.com/developers/extend/apps/data/overview
- Twenty architecture review (third-party) — https://www.codeline.co/thoughts/repo-review/2024/twenty-open-source-crm
- SuiteCRM. Custom Fields — https://docs.suitecrm.com/developer/core-framework/custom-fields/
- SuiteCRM. Vardefs — https://docs.suitecrm.com/developer/core-framework/vardefs/
- SuiteCRM. Database Schema (`_cstm`) — https://docs.suitecrm.com/developer/core-framework/database-schema/
- SuiteCRM. API v8 Endpoints — https://docs.suitecrm.com/developer/integration-api/v8/endpoints/
- SuiteCRM. API v8 Authentication — https://docs.suitecrm.com/developer/integration-api/v8/authentication/
- SuiteCRM MCP (community) — https://github.com/anirudhx7/suitecrm-mcp
- Odoo. ORM API — https://www.odoo.com/documentation/18.0/developer/reference/backend/orm.html
- Odoo. External API — https://www.odoo.com/documentation/18.0/developer/reference/external_api.html
- Odoo. Studio Fields — https://www.odoo.com/documentation/18.0/applications/studio/fields.html
- Odoo. Property Fields — https://www.odoo.com/documentation/18.0/applications/essentials/property_fields.html
- Odoo. AI — https://www.odoo.com/documentation/19.0/applications/productivity/ai.html
- Odoo. AI Agents — https://www.odoo.com/documentation/19.0/applications/productivity/ai/agents.html
- Odoo community MCP module — https://apps.odoo.com/apps/modules/19.0/muk_mcp
- Krayin. Custom Attributes — https://github.com/krayin/krayin-docs/blob/ceb8116eaa5eca76d6eeeedf58497ebce5ba7d47/docs/2.2/custom-attributes/README.md
- Krayin. REST API — https://github.com/krayin/krayin-docs/blob/ceb8116eaa5eca76d6eeeedf58497ebce5ba7d47/docs/2.2/api/getting-started-with-the-api.md
- Vtiger. REST API docs — https://www.vtiger.com/docs/rest-api-for-vtiger
- Vtiger. Custom fields in `_cf` (mailing list) — http://lists.vtigercrm.com/pipermail/vtigercrm-developers/2006-December/018797.html
- CiviCRM. APIv4 and Custom Data — https://docs.civicrm.org/dev/en/latest/api/v4/custom-data/
- CiviCRM. Creating Custom Fields — https://docs.civicrm.org/user/en/latest/organising-your-data/creating-custom-fields/
- CiviCRM. APIv4 REST — https://docs.civicrm.org/dev/en/latest/api/v4/rest/
- Atomic CRM. Custom Fields — https://marmelab.com/atomic-crm/doc/developers/custom-fields/
- Atomic CRM. REST API — https://marmelab.com/atomic-crm/doc/developers/atomic-crm-api/
- Atomic CRM. MCP Server — https://marmelab.com/atomic-crm/doc/users/mcp-server/
