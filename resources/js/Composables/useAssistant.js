import { reactive } from 'vue';

const state = reactive({
    open: false,
    unread: false,
    project: null,
    size: 'compact',
});

export function useAssistant() {
    return {
        state,
        open(project = null) {
            state.open = true;
            state.unread = false;
            state.project = project;
        },
        close() {
            state.open = false;
        },
        setSize(size) {
            if (size !== 'compact' && size !== 'fullscreen') return;
            state.size = size;
        },
        toggle() {
            state.open = !state.open;
            if (state.open) {
                state.unread = false;
            }
        },
    };
}
