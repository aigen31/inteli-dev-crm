import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const PREFIX = 'crm.task-draft.v1';
const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;

/**
 * Lightweight local draft for long task composers.
 *
 * Best-practice rules:
 * - per-user key, versioned payload, seven-day expiry;
 * - debounce writes, flush on tab hide/unload;
 * - never persist consent or files;
 * - clear only after a successful server response.
 */
export function useTaskDraft({ key, snapshot, restore, isEmpty = () => false }) {
    const storageKey = `${PREFIX}:${key}`;
    const savedAt = ref(null);
    const restored = ref(false);
    const available = ref(false);
    let timer = null;
    let ready = false;
    let closed = false;
    const getSnapshot = snapshot;

    function read() {
        try {
            const raw = window.localStorage.getItem(storageKey);
            if (!raw) return;

            const payload = JSON.parse(raw);
            if (!payload || payload.version !== 1 || !payload.data) {
                window.localStorage.removeItem(storageKey);
                return;
            }

            if (!payload.savedAt || Date.now() - payload.savedAt > MAX_AGE_MS) {
                window.localStorage.removeItem(storageKey);
                return;
            }

            if (isEmpty(payload.data)) {
                window.localStorage.removeItem(storageKey);
                return;
            }

            restore(payload.data);
            savedAt.value = new Date(payload.savedAt);
            restored.value = true;
        } catch {
            window.localStorage.removeItem(storageKey);
        }
    }

    function write() {
        if (!ready || closed) return;

        try {
            const data = getSnapshot();
            if (isEmpty(data)) {
                window.localStorage.removeItem(storageKey);
                savedAt.value = null;
                return;
            }

            const timestamp = Date.now();
            window.localStorage.setItem(storageKey, JSON.stringify({ version: 1, savedAt: timestamp, data }));
            savedAt.value = new Date(timestamp);
        } catch {
            // Private mode or quota exhausted: the composer still works.
        }
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(write, 600);
    }

    function flush() {
        window.clearTimeout(timer);
        write();
    }

    function onVisibility() {
        if (document.visibilityState === 'hidden') flush();
    }

    function discard() {
        closed = true;
        try {
            window.localStorage.removeItem(storageKey);
        } catch {
            // Ignore unavailable storage.
        }
        savedAt.value = null;
        restored.value = false;
        window.clearTimeout(timer);
    }

    function clear() {
        discard();
    }

    onMounted(() => {
        available.value = true;
        read();
        ready = true;
        watch(getSnapshot, schedule, { deep: true });
        window.addEventListener('pagehide', flush);
        document.addEventListener('visibilitychange', onVisibility);
    });

    onBeforeUnmount(() => {
        flush();
        window.removeEventListener('pagehide', flush);
        document.removeEventListener('visibilitychange', onVisibility);
    });

    return { savedAt, restored, available, discard, clear, flush };
}
