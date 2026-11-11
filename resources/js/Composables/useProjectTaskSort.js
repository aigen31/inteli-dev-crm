import { computed, ref } from 'vue';

const STORAGE_KEY = 'crm.project-task-sort.v1';
const STATUS_ORDER = {
    pending_review: 1,
    queued: 2,
    in_progress: 3,
    awaiting_review: 4,
    rejected_by_client: 5,
    completed: 6,
    rejected_by_admin: 7,
};

function initialSort() {
    if (typeof window === 'undefined') return 'newest';

    const fromUrl = new URLSearchParams(window.location.search).get('sort');
    if (['newest', 'status'].includes(fromUrl)) return fromUrl;

    const stored = window.localStorage.getItem(STORAGE_KEY);
    return ['newest', 'status'].includes(stored) ? stored : 'newest';
}

export function useProjectTaskSort(tasks) {
    const sort = ref(initialSort());

    const sortedTasks = computed(() => {
        const list = [...(tasks() || [])];

        if (sort.value !== 'status') return list;

        return list.sort((a, b) => {
            const left = STATUS_ORDER[a.status] ?? 99;
            const right = STATUS_ORDER[b.status] ?? 99;
            return left - right;
        });
    });

    function setSort(value) {
        if (!['newest', 'status'].includes(value) || value === sort.value) return;

        sort.value = value;
        window.localStorage.setItem(STORAGE_KEY, value);

        const url = new URL(window.location.href);
        if (value === 'newest') {
            url.searchParams.delete('sort');
        } else {
            url.searchParams.set('sort', value);
        }
        window.history.replaceState({}, '', url);
    }

    return { sort, sortedTasks, setSort };
}
