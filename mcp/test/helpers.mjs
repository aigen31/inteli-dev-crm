import http from 'node:http';

export async function startMockCrm() {
    const calls = [];

    const server = http.createServer(async (req, res) => {
        let body = '';
        for await (const chunk of req) body += chunk;

        calls.push({
            method: req.method,
            url: req.url,
            authorization: req.headers.authorization,
            body: body ? JSON.parse(body) : null,
        });

        const send = (status, payload) => {
            res.writeHead(status, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify(payload));
        };

        if (req.headers.authorization !== 'Bearer test-token') {
            return send(401, { message: 'Unauthorized' });
        }

        const url = new URL(req.url, 'http://localhost');

        if (url.pathname === '/api/v1/meta/overview') {
            return send(200, { data: { clients: 2, tasks_active: 3 } });
        }

        if (url.pathname === '/api/v1/clients' && req.method === 'GET') {
            return send(200, { data: [{ id: 'c1', name: 'Acme' }], meta: { total: 1 } });
        }

        if (url.pathname === '/api/v1/clients' && req.method === 'POST') {
            return send(201, { data: { id: 'c2', ...JSON.parse(body) } });
        }

        if (url.pathname === '/api/v1/tasks') {
            return send(200, { data: [{ id: 't1', title: 'Landing' }] });
        }

        if (url.pathname === '/api/v1/invoices' && req.method === 'POST') {
            return send(201, { data: { id: 'i1', ...JSON.parse(body) } });
        }

        if (url.pathname === '/api/v1/custom-fields/client/c1/values') {
            return send(200, { data: { entity_type: 'client', entity_id: 'c1', fields: [{ key: 'intake_status', value: 'open' }] } });
        }

        return send(404, { message: `No mock for ${req.method} ${url.pathname}` });
    });

    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const { port } = server.address();

    return {
        url: `http://127.0.0.1:${port}`,
        calls,
        close: () => new Promise((resolve) => server.close(resolve)),
    };
}
