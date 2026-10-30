const DEFAULT_URL = 'http://127.0.0.1:8099/api/v1';

export class CrmApiError extends Error {
    constructor(status, message, body) {
        super(message);
        this.name = 'CrmApiError';
        this.status = status;
        this.body = body;
    }
}

export function getConfig() {
    return {
        baseUrl: (process.env.CRM_API_URL || DEFAULT_URL).replace(/\/+$/, ''),
        token: process.env.CRM_API_TOKEN || '',
    };
}

export function createApiClient() {
    async function request(method, path, { query, body } = {}) {
        const { baseUrl, token } = getConfig();

        if (!token) {
            throw new CrmApiError(401, 'Не задан CRM_API_TOKEN. Создайте токен в Настройках → API и MCP.');
        }

        const url = new URL(`${baseUrl}${path}`);

        for (const [key, value] of Object.entries(query || {})) {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.set(key, String(value));
            }
        }

        const response = await fetch(url, {
            method,
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
                ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });

        const text = await response.text();
        let data = null;

        try {
            data = text ? JSON.parse(text) : null;
        } catch {
            data = { raw: text };
        }

        if (!response.ok) {
            const message = data?.message || `CRM API вернул ${response.status}`;
            throw new CrmApiError(response.status, message, data);
        }

        return data;
    }

    return {
        getConfig,
        get: (path, query) => request('GET', path, { query }),
        post: (path, body) => request('POST', path, { body }),
        put: (path, body) => request('PUT', path, { body }),
        patch: (path, body) => request('PATCH', path, { body }),
        delete: (path) => request('DELETE', path),
    };
}
