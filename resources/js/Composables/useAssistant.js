import { reactive } from 'vue';

const state = reactive({
    open: false,
    unread: false,
});

export function useAssistant() {
    return {
        state,
        open() {
            state.open = true;
            state.unread = false;
        },
        close() {
            state.open = false;
        },
        toggle() {
            state.open = !state.open;
            if (state.open) {
                state.unread = false;
            }
        },
    };
}
