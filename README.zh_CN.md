# inteli dev CRM

[English](README.md) · [Русский](README.ru.md) · [中文](README.zh_CN.md)

<img src="public/images/kiki/kiki-flight-avatar.webp" width="110" align="right" alt="Kiki — inteli dev 助手">

自由职业者的工作空间：客户、任务、项目、模板、账单、报告和助手 Kiki——尽在一处。

CRM 是封闭的：没有注册，账户由所有者创建。管理员管理业务，客户只能看到自己的任务、账单和报告。

## 功能

- **客户和任务**——状态、优先级、截止日期、检查清单、评论和变更历史。
- **项目**——形如 `KOR` 的键、客户、成员和角色、进度、网站、`AGENTS.md` 和网站研究。
- **任务模板和申请表单**——可重复的场景、必填字段和结构化申请。
- **账单和支付**——ЮKassa、直接转账、定期付款和提醒。
- **客户报告**——「说明 → 验证链接 → 截图」分组、收件人和未读状态。
- **资料库**——工作空间的材料和文档。
- **Kiki**——通过工具访问 CRM 的助手：查找客户和任务、准备计划、创建申请并协助处理界面。
- **帮助**——带有步骤和链接的 CRM 分区地图。
- **API 和 MCP**——REST API v1 和面向外部 AI 客户端的 MCP 服务器。
- **法律页面**——隐私政策和个人数据处理同意书。

## 技术栈

| 层 | 技术 |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Vue 3.5, Inertia 3, Vite 8, Tailwind CSS 4 |
| 数据库 | PostgreSQL 16, Redis 7 |
| 质量 | Pest 5, Pint |
| 基础设施 | Docker Compose, nginx, Traefik, Let's Encrypt |

## 快速开始

需要 Docker 和 Docker Compose。

```bash
git clone git@github.com:aigen31/inteli-dev-crm.git
cd inteli-dev-crm

cp .env.example .env
# 填写 APP_KEY、DB_PASSWORD 等必填值

docker compose build
docker compose up -d --wait
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

应用将通过 `http://localhost:${APP_PORT}` 打开。
Healthcheck：`GET /up`。

常用命令：

```bash
# Логи приложения
docker compose logs -f app

# Тесты
docker compose exec app php artisan test

# Стиль кода
docker compose exec app vendor/bin/pint --test

# Frontend-сборка
npm run build
```

## 环境变量

主要参数位于 `.env`：

- `APP_URL`、`APP_KEY`、`APP_ENV`——应用基础设置。
- `DB_*`——PostgreSQL 连接。
- `REDIS_*`——缓存、会话和队列。
- `KERNEL_URL`、`KERNEL_TOKEN`、`KERNEL_PROVIDER_TOKEN`——连接到 Kiki 中央内核。
- `YOOKASSA_*`——收款。
- `MCP_TOKEN`——访问 MCP 服务器。

机密信息不提交到仓库。生产环境请使用单独的 `.env`/`.env.production`。

## 架构

```text
app/
├── Actions/          # бизнес-операции: создание, смена статуса, оплата
├── Enums/            # роли, статусы, типы и способы оплаты
├── Http/
│   ├── Controllers/  # Admin, Client, Api/V1, Internal, Mcp
│   ├── Middleware/   # авторизация, API-токены, kernel-контракт
│   └── Requests/     # валидация входящих данных
├── Models/           # Eloquent-модели
├── Services/         # доменные сервисы и AI-интеграция
└── Policies/         # доступ к клиентам, задачам и проектам

resources/js/
├── Pages/            # Inertia-страницы Admin/Client/Assistant
├── Components/       # UI, формы, ассистент, Кики
├── Composables/      # состояние чата и общие сценарии
└── css/              # дизайн-токены и Tailwind

mcp/                  # MCP-сервер
docs/agents/          # документация для разработки
docker/               # Dockerfile, nginx, entrypoint
```

业务逻辑位于 `Actions`/`Services`，控制器负责接收请求、授权和委托。用户字段通过 `CustomFieldService` 处理，来自 WYSIWYG 和 AI 的 HTML 通过 `HtmlSanitizer` 处理。

## Kiki 和外部集成

Kiki 通过中央内核（Kiki assistant kernel）工作。CRM：

- 向内核提供系统提示词、工具列表和页面上下文；
- 通过内部契约 `/api/internal/assistant` 接收工具调用；
- 存储对话镜像、附件、文档和操作确认。

Kiki 的财务操作仅在用户确认后执行；权限在确认时重新校验。

REST API 位于 `/api/v1`，MCP 位于 `/api/mcp`。详情：[docs/agents/api.md](docs/agents/api.md) 和 [docs/agents/mcp.md](docs/agents/mcp.md)。

## Production

Production 栈使用 Traefik、外部网络 `traefik-public` 和单独的 nginx 容器。

```bash
# локальная production-конфигурация без dev-override
docker compose -f docker-compose.yml up -d --wait

# миграции
docker compose -f docker-compose.yml exec app php artisan migrate --force
```

远程部署提供了 `deploy.sh`：它同步项目、构建镜像并检查 `/up`。启动前请配置 SSH 访问和 `.env.production`。

## 文档

| 主题 | 文档 |
|---|---|
| 安装和环境 | [docs/agents/setup.md](docs/agents/setup.md) |
| 架构和领域 | [docs/agents/architecture.md](docs/agents/architecture.md) |
| 项目和任务 | [docs/agents/project-management.md](docs/agents/project-management.md) |
| 申请表单 | [docs/agents/form-templates.md](docs/agents/form-templates.md) |
| 用户字段 | [docs/agents/custom-fields.md](docs/agents/custom-fields.md) |
| Kiki 和 AI | [docs/agents/ai.md](docs/agents/ai.md) |
| REST API | [docs/agents/api.md](docs/agents/api.md) |
| MCP | [docs/agents/mcp.md](docs/agents/mcp.md) |
| Frontend 和设计 | [docs/agents/frontend.md](docs/agents/frontend.md) |
| 测试 | [docs/agents/testing.md](docs/agents/testing.md) |
| 部署 | [docs/agents/deployment.md](docs/agents/deployment.md) |

## 测试

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
cd mcp && npm test
```

测试使用 SQLite `:memory:`（见 `phpunit.xml`），不会影响生产数据库。

## 角色

- **管理员**——管理客户、任务、项目、账单、模板、报告和访问权限。
- **客户**——查看自己的任务、账单、报告和项目，创建申请并与 Kiki 交流。

---

由 [inteli dev](https://inteli-dev.ru) 制作。
