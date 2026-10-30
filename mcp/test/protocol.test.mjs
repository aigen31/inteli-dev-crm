import assert from 'node:assert/strict';
import test from 'node:test';
import { spawn } from 'node:child_process';
import readline from 'node:readline';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { startMockCrm } from './helpers.mjs';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));

function createClient(apiUrl) {
    const child = spawn(process.execPath, ['src/server.mjs'], {
        cwd: root,
        env: { ...process.env, CRM_API_URL: apiUrl, CRM_API_TOKEN: 'test-token' },
        stdio: ['pipe', 'pipe', 'pipe'],
    });

    const lines = [];
    const waiters = [];
    const rl = readline.createInterface({ input: child.stdout });

    rl.on('line', (line) => {
        const message = JSON.parse(line);
        const waiter = waiters.find((item) => item.id === message.id);

        if (waiter) {
            waiter.resolve(message);
        } else {
            lines.push(message);
        }
    });

    return {
        child,
        request(payload) {
            return new Promise((resolve, reject) => {
                const waiter = { id: payload.id, resolve, reject };
                waiters.push(waiter);
                child.stdin.write(`${JSON.stringify(payload)}\n`);
                setTimeout(() => reject(new Error(`Timeout for ${payload.method}`)), 5000);
            });
        },
        close() {
            child.kill('SIGTERM');
        },
    };
}

test('MCP handshake, tool list and tool call over stdio', async () => {
    const mock = await startMockCrm();

    try {
        const client = createClient(`${mock.url}/api/v1`);

        const init = await client.request({
            jsonrpc: '2.0',
            id: 1,
            method: 'initialize',
            params: {
                protocolVersion: '2025-06-18',
                capabilities: {},
                clientInfo: { name: 'test', version: '1.0.0' },
            },
        });

        assert.equal(init.result.serverInfo.name, 'inteli-dev-crm');
        assert.ok(init.result.capabilities.tools);

        const list = await client.request({ jsonrpc: '2.0', id: 2, method: 'tools/list', params: {} });
        assert.ok(list.result.tools.some((tool) => tool.name === 'crm_overview'));

        const call = await client.request({
            jsonrpc: '2.0',
            id: 3,
            method: 'tools/call',
            params: { name: 'crm_overview', arguments: {} },
        });

        assert.equal(call.result.isError, false);
        assert.match(call.result.content[0].text, /clients/);

        client.close();
    } finally {
        await mock.close();
    }
});
