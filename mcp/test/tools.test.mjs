import assert from 'node:assert/strict';
import test from 'node:test';
import { createApiClient } from '../src/client.mjs';
import { buildTools } from '../src/tools.mjs';
import { startMockCrm } from './helpers.mjs';

test('tools read overview and clients with bearer token', async () => {
    const mock = await startMockCrm();
    process.env.CRM_API_URL = `${mock.url}/api/v1`;
    process.env.CRM_API_TOKEN = 'test-token';

    try {
        const tools = buildTools(createApiClient());
        const overview = await tools.find((tool) => tool.name === 'crm_overview').handler({});
        assert.equal(overview.data.clients, 2);

        const clients = await tools.find((tool) => tool.name === 'list_clients').handler({ query: 'acme' });
        assert.equal(clients.data[0].name, 'Acme');
        assert.equal(mock.calls.at(-1).authorization, 'Bearer test-token');
    } finally {
        await mock.close();
    }
});

test('create_invoice sends task, amount and payment method', async () => {
    const mock = await startMockCrm();
    process.env.CRM_API_URL = `${mock.url}/api/v1`;
    process.env.CRM_API_TOKEN = 'test-token';

    try {
        const tools = buildTools(createApiClient());
        const invoice = await tools.find((tool) => tool.name === 'create_invoice').handler({
            task_id: 't1',
            amount: 15000,
            description: 'Landing',
            payment_method: 'transfer',
        });

        assert.equal(invoice.data.id, 'i1');
        assert.equal(mock.calls.at(-1).body.amount, 15000);
        assert.equal(mock.calls.at(-1).body.payment_method, 'transfer');
    } finally {
        await mock.close();
    }
});

test('set_custom_field_values wraps values into fields', async () => {
    const mock = await startMockCrm();
    process.env.CRM_API_URL = `${mock.url}/api/v1`;
    process.env.CRM_API_TOKEN = 'test-token';

    try {
        const tools = buildTools(createApiClient());
        const result = await tools.find((tool) => tool.name === 'set_custom_field_values').handler({
            entity_type: 'client',
            entity_id: 'c1',
            fields: { intake_status: 'open' },
        });

        assert.equal(result.data.entity_id, 'c1');
        assert.deepEqual(mock.calls.at(-1).body, { fields: { intake_status: 'open' } });
    } finally {
        await mock.close();
    }
});

test('missing token fails with actionable message', async () => {
    process.env.CRM_API_URL = 'http://127.0.0.1:1/api/v1';
    process.env.CRM_API_TOKEN = '';

    const tools = buildTools(createApiClient());

    await assert.rejects(
        () => tools.find((tool) => tool.name === 'crm_overview').handler({}),
        /CRM_API_TOKEN/,
    );
});
