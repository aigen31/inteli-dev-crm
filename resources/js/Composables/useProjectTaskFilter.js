import { computed, ref } from 'vue';

const STORAGE_KEY = 'crm.project-task-filter.v1';

function initialStatus(allowed) {
    if (typeof window === 'undefined') return '';

    const fromUrl = new URLSearchParams(window.location.search).get('status');
    if (fromUrl && allowed.includes(fromUrl)) return fromUrl;

    const stored = window.localStorage.getItem(STORAGE_KEY);
    return stored && allowed.includes(stored) ? stored : '';
}

export function useProjectTaskFilter(tasks, allowedStatuses) {
    const status = ref(initialStatus(allowedStatuses));

    const filteredTasks = computed(() => {
        const list = tasks() || [];
        return status.value ? list.filter((task) => task.status === status.value) : list;
    });

    function setStatus(value) {
        if (value !== '' && !allowedStatuses.includes(value)) return;
        if (value === status.value) return;

        status.value = value;
        if (value) {
            window.localStorage.setItem(STORAGE_KEY, value);
        } else {
            window.localStorage.removeItem(STORAGE_KEY);
        }

        const url = new URL(window.location.href);
        if (value) {
            url.searchParams.set('status', value);
        } else {
            url.searchParams.delete('status');
        }
        window.history.replaceState({}, '', url);
    }

    return { status, filteredTasks, setStatus };
}
