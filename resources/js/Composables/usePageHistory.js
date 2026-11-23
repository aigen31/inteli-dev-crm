import { computed, reactive } from 'vue';

const STORAGE_KEY = 'crm:page-history';
const LIMIT = 25;

function load() {
    try {
        const raw = window.sessionStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed)
            ? parsed.filter((item) => item && typeof item.url === 'string' && typeof item.title === 'string').slice(-LIMIT)
            : [];
    } catch {
        return [];
    }
}

const state = reactive({ items: load() });

function persist() {
    try {
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state.items.slice(-LIMIT)));
    } catch {
        // Приватный режим: история останется только в памяти.
    }
}

function pageTitle() {
    return (document.title || '').replace(/\s*—\s*CRM\s*$/, '').trim();
}

export function usePageHistory() {
    function record(url, title = pageTitle()) {
        const path = url.split('?')[0];
        const label = (title || '').trim() || 'Назад';
        const items = state.items;

        if (items.length && items[items.length - 1].url === path) {
            items[items.length - 1].title = label;
            persist();

            return;
        }

        // Возврат назад: не дублируем страницу, снимаем верхнюю запись.
        if (items.length > 1 && items[items.length - 2].url === path) {
            items.pop();
            items[items.length - 1].title = label;
            persist();

            return;
        }

        items.push({ url: path, title: label });

        if (items.length > LIMIT) {
            items.splice(0, items.length - LIMIT);
        }

        persist();
    }

    const previous = computed(() => {
        const current = typeof window !== 'undefined' ? window.location.pathname : '';
        for (let index = state.items.length - 1; index >= 0; index -= 1) {
            if (state.items[index].url !== current) return state.items[index];
        }

        return null;
    });

    return { items: state.items, previous, record };
}
