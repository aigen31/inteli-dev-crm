import http from 'node:http';
import readline from 'node:readline';
import { randomUUID } from 'node:crypto';
import { createApiClient } from './client.mjs';
import { buildTools, toolByName } from './tools.mjs';

const SERVER_INFO = { name: 'inteli-dev-crm', version: '1.0.0' };
const PROTOCOL_VERSION = '2025-06-18';

const api = createApiClient();
const tools = buildTools(api);

const RESOURCES = [
    { uri: 'crm://overview', name: 'Сводка CRM', description: 'Клиенты, задачи, счета и доход', mimeType: 'application/json' },
    { uri: 'crm://clients', name: 'Клиенты', description: 'До 50 клиентов с пользовательскими полями', mimeType: 'application/json' },
    { uri: 'crm://tasks', name: 'Задачи', description: 'До 50 последних задач', mimeType: 'application/json' },
    { uri: 'crm://task-templates', name: 'Шаблоны задач', description: 'Активные шаблоны', mimeType: 'application/json' },
    { uri: 'crm://invoices', name: 'Счета', description: 'До 50 последних счетов', mimeType: 'application/json' },
    { uri: 'crm://custom-fields', name: 'Пользовательские поля', description: 'Определения полей', mimeType: 'application/json' },
];

const PROMPTS = [
    {
        name: 'daily_brief',
        description: 'Утренняя сводка: фокус, просрочки, счета, следующий шаг.',
        arguments: [],
    },
    {
        name: 'client_brief',
        description: 'Краткая карточка клиента: задачи, счета, поля, риски.',
        arguments: [{ name: 'client_id', description: 'UUID клиента', required: true }],
    },
];

function jsonRpcError(id, code, message, data) {
    return { jsonrpc: '2.0', id: id ?? null, error: { code, message, ...(data ? { data } : {}) } };
}

function jsonRpcResult(id, result) {
    return { jsonrpc: '2.0', id, result };
}

function textResult(data, isError = false) {
    const text = typeof data === 'string' ? data : JSON.stringify(data, null, 2);

    return {
        content: [{ type: 'text', text }],
        ...(isError ? {} : { structuredContent: data }),
        isError,
    };
}

async function readResource(uri) {
    switch (uri) {
        case 'crm://overview':
            return api.get('/meta/overview');
        case 'crm://clients':
            return api.get('/clients', { per_page: 50 });
        case 'crm://tasks':
            return api.get('/tasks', { per_page: 50 });
        case 'crm://task-templates':
            return api.get('/task-templates', { per_page: 50 });
        case 'crm://invoices':
            return api.get('/invoices', { per_page: 50 });
        case 'crm://custom-fields':
            return api.get('/custom-fields');
        default:
            throw new Error(`Неизвестный ресурс: ${uri}`);
    }
}

async function handleRequest(request) {
    if (!request || request.jsonrpc !== '2.0' || typeof request.method !== 'string') {
        return jsonRpcError(request?.id, -32600, 'Invalid Request');
    }

    const { id, method, params = {} } = request;

    try {
        switch (method) {
            case 'initialize':
                return jsonRpcResult(id, {
                    protocolVersion: PROTOCOL_VERSION,
                    capabilities: {
                        tools: { listChanged: false },
                        resources: { listChanged: false },
                        prompts: { listChanged: false },
                    },
                    serverInfo: SERVER_INFO,
                    instructions:
                        'CRM для самозанятого. Начните с crm_overview. Для денег используйте create_invoice и mark_invoice_paid осознанно: это реальные операции.',
                });

            case 'notifications/initialized':
            case 'notifications/cancelled':
                return null;

            case 'ping':
                return jsonRpcResult(id, {});

            case 'tools/list':
                return jsonRpcResult(id, {
                    tools: tools.map(({ name, description, inputSchema, annotations }) => ({
                        name,
                        description,
                        inputSchema,
                        annotations,
                    })),
                });

            case 'tools/call': {
                const tool = toolByName(tools, params.name);

                if (!tool) {
                    return jsonRpcError(id, -32602, `Неизвестный инструмент: ${params.name}`);
                }

                try {
                    const result = await tool.handler(params.arguments || {});
                    return jsonRpcResult(id, textResult(result));
                } catch (error) {
                    return jsonRpcResult(id, textResult({ error: error.message, status: error.status ?? null }, true));
                }
            }

            case 'resources/list':
                return jsonRpcResult(id, { resources: RESOURCES });

            case 'resources/read': {
                try {
                    const data = await readResource(params.uri);
                    return jsonRpcResult(id, {
                        contents: [{ uri: params.uri, mimeType: 'application/json', text: JSON.stringify(data, null, 2) }],
                    });
                } catch (error) {
                    return jsonRpcError(id, -32602, error.message);
                }
            }

            case 'prompts/list':
                return jsonRpcResult(id, { prompts: PROMPTS });

            case 'prompts/get': {
                const name = params.name;
                const args = params.arguments || {};

                if (name === 'daily_brief') {
                    return jsonRpcResult(id, {
                        description: 'Утренняя сводка',
                        messages: [
                            {
                                role: 'user',
                                content: {
                                    type: 'text',
                                    text: 'Сделай короткую утреннюю сводку по CRM: что в работе, что просрочено, какие счета ждут оплаты, что сделать первым. Используй crm_overview и list_tasks.',
                                },
                            },
                        ],
                    });
                }

                if (name === 'client_brief') {
                    if (!args.client_id) {
                        return jsonRpcError(id, -32602, 'client_id обязателен');
                    }

                    return jsonRpcResult(id, {
                        description: 'Карточка клиента',
                        messages: [
                            {
                                role: 'user',
                                content: {
                                    type: 'text',
                                    text: `Собери краткую карточку клиента ${args.client_id}: контакты, активные задачи, счета, пользовательские поля и риски. Используй get_client.`,
                                },
                            },
                        ],
                    });
                }

                return jsonRpcError(id, -32602, `Неизвестный промпт: ${name}`);
            }

            case 'logging/setLevel':
                return jsonRpcResult(id, {});

            default:
                return jsonRpcError(id, -32601, `Method not found: ${method}`);
        }
    } catch (error) {
        console.error('[mcp]', method, error);
        return jsonRpcError(id, -32603, error.message || 'Internal error');
    }
}

async function handlePayload(payload) {
    if (Array.isArray(payload)) {
        const responses = await Promise.all(payload.map(handleRequest));
        return responses.filter(Boolean);
    }

    return handleRequest(payload);
}

function startStdio() {
    const rl = readline.createInterface({ input: process.stdin, terminal: false });

    rl.on('line', async (line) => {
        if (!line.trim()) return;

        let request;

        try {
            request = JSON.parse(line);
        } catch {
            process.stdout.write(`${JSON.stringify(jsonRpcError(null, -32700, 'Parse error'))}\n`);
            return;
        }

        const response = await handlePayload(request);

        if (response !== null && response !== undefined) {
            process.stdout.write(`${JSON.stringify(response)}\n`);
        }
    });
}

function startHttp(port) {
    const server = http.createServer(async (req, res) => {
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Mcp-Session-Id');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');

        if (req.method === 'OPTIONS') {
            res.writeHead(204).end();
            return;
        }

        if (req.method === 'GET' && req.url === '/health') {
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ status: 'ok', server: SERVER_INFO }));
            return;
        }

        if (req.method !== 'POST' || !['/mcp', '/'].includes(req.url)) {
            res.writeHead(404, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Not found' }));
            return;
        }

        let body = '';

        for await (const chunk of req) {
            body += chunk;
        }

        let payload;

        try {
            payload = JSON.parse(body);
        } catch {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify(jsonRpcError(null, -32700, 'Parse error')));
            return;
        }

        if (payload?.method === 'initialize') {
            res.setHeader('Mcp-Session-Id', randomUUID());
        }

        const response = await handlePayload(payload);
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(response));
    });

    server.listen(port, '127.0.0.1', () => {
        console.error(`[mcp] inteli-dev CRM MCP server: http://127.0.0.1:${port}/mcp`);
    });
}

const args = process.argv.slice(2);
const httpIndex = args.indexOf('--http');

if (args.includes('--help')) {
    console.log('Usage: node src/server.mjs [--http 7331]');
    console.log('Env: CRM_API_URL, CRM_API_TOKEN');
} else if (httpIndex !== -1) {
    startHttp(Number(args[httpIndex + 1] || 7331));
} else {
    startStdio();
}
