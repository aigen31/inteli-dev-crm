const PREFIX = 'crm:filters:';

/**
 * Запоминает фильтры страницы на время вкладки, чтобы они переживали
 * переходы в другие разделы и возврат назад.
 */
export function useFilterMemory(key) {
    const storageKey = PREFIX + key;

    function load() {
        try {
            const raw = window.sessionStorage.getItem(storageKey);

            return raw ? JSON.parse(raw) : {};
        } catch {
            return {};
        }
    }

    function save(filters) {
        const clean = {};

        Object.entries(filters || {}).forEach(([name, value]) => {
            if (value !== null && value !== undefined && value !== '') clean[name] = value;
        });

        try {
            if (Object.keys(clean).length) window.sessionStorage.setItem(storageKey, JSON.stringify(clean));
            else window.sessionStorage.removeItem(storageKey);
        } catch {
            // Приватный режим — обходимся без запоминания.
        }
    }

    function clear() {
        try {
            window.sessionStorage.removeItem(storageKey);
        } catch {
            // ничего
        }
    }

    return { load, save, clear };
}
