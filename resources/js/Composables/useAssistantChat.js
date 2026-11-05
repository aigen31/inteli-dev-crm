import { computed, ref } from 'vue';
import axios from 'axios';

const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;

/**
 * The project does not publish `lang/` files, so Laravel returns raw
 * `validation.*` keys for rules without an explicit message. Translate the
 * common ones here instead of showing a key inside the form.
 */
const VALIDATION_FALLBACKS = {
    'validation.required': 'Заполните поле',
    'validation.string': 'Ожидается текст',
    'validation.email': 'Некорректный email',
    'validation.url': 'Некорректная ссылка',
    'validation.date': 'Некорректная дата',
    'validation.numeric': 'Нужно число',
    'validation.boolean': 'Некорректное значение',
    'validation.accepted': 'Нужно подтвердить',
    'validation.in': 'Недопустимое значение',
    'validation.uuid': 'Некорректный файл',
    'validation.min.array': 'Добавьте хотя бы одну запись',
    'validation.max.array': 'Слишком много записей',
};

function normalizeErrors(errors) {
    const normalized = {};

    Object.entries(errors ?? {}).forEach(([key, value]) => {
        const message = Array.isArray(value) ? value[0] : value;

        if (typeof message !== 'string' || message === '') {
            return;
        }

        normalized[key] = VALIDATION_FALLBACKS[message]
            ?? (message.startsWith('validation.') ? 'Проверьте значение' : message);
    });

    return normalized;
}

/**
 * One assistant thread: messages, images, pending confirmations.
 *
 * Both the floating panel and the full page use this composable, so the two
 * entry points cannot drift in how they send, upload or confirm.
 */
export function useAssistantChat(options = {}) {
    const { initialPayload = null, maxAttachments = 4, context = null } = options;

    const conversationId = ref(initialPayload?.conversation_id ?? null);
    const session = ref(initialPayload?.session ?? null);
    const messages = ref(initialPayload?.messages ?? []);
    const pendingActions = ref(initialPayload?.pending_actions ?? []);
    const suggestions = ref(initialPayload?.suggestions ?? []);
    const draft = ref('');
    const attachments = ref([]);
    const loading = ref(false);
    const uploading = ref(0);
    const error = ref(null);
    const fieldErrors = ref({});

    const canSend = computed(
        () => !loading.value && uploading.value === 0 && (draft.value.trim() !== '' || attachments.value.length > 0),
    );

    const hasMessages = computed(() => messages.value.length > 0);

    function pageContext() {
        if (typeof context === 'function') {
            return context();
        }

        return { url: window.location.pathname };
    }

    function applyPayload(data) {
        if (!data) return;

        conversationId.value = data.conversation_id ?? conversationId.value;
        session.value = data.session ?? session.value;
        messages.value = (data.messages ?? []).filter(
            (message) => message.role !== 'assistant' || String(message.content ?? '').trim() !== '',
        );
        pendingActions.value = data.pending_actions ?? [];

        if (Array.isArray(data.suggestions) && data.suggestions.length > 0) {
            suggestions.value = data.suggestions;
        }

        error.value = null;
        fieldErrors.value = {};
    }

    function reset() {
        conversationId.value = null;
        session.value = null;
        messages.value = [];
        pendingActions.value = [];
        draft.value = '';
        attachments.value = [];
        error.value = null;
        fieldErrors.value = {};
    }

    function handleError(exception, fallback = 'Кики не ответила. Проверьте соединение и попробуйте снова.') {
        const response = exception?.response;
        const data = response?.data;

        if (response?.status === 422 && data?.errors) {
            fieldErrors.value = normalizeErrors(data.errors);
            error.value = 'Проверьте поля формы.';
            return;
        }

        error.value = data?.message || fallback;
    }

    async function send(text = draft.value) {
        const message = String(text).trim();

        if (loading.value || (message === '' && attachments.value.length === 0)) {
            return null;
        }

        error.value = null;
        fieldErrors.value = {};

        const pending = attachments.value.slice();
        const optimistic = {
            id: `local-${Date.now()}`,
            role: 'user',
            content: message,
            attachments: pending.map((file) => ({
                id: file.id,
                name: file.name,
                mime: file.mime,
                url: file.url,
            })),
            created_at: new Date().toISOString(),
        };

        messages.value.push(optimistic);
        draft.value = '';
        attachments.value = [];
        loading.value = true;

        try {
            const { data } = await axios.post(route('assistant.chat'), {
                message,
                conversation_id: conversationId.value,
                attachments: pending.map((file) => file.id),
                context: pageContext(),
            });

            applyPayload(data);

            return data;
        } catch (exception) {
            messages.value = messages.value.filter((item) => item.id !== optimistic.id);
            draft.value = message;
            attachments.value = pending;
            handleError(exception);

            return null;
        } finally {
            loading.value = false;
        }
    }

    async function regenerate(conversation = conversationId.value) {
        if (!conversation || loading.value) return null;

        loading.value = true;
        error.value = null;

        try {
            const { data } = await axios.post(route('assistant.conversations.regenerate', conversation), {
                url: pageContext().url,
            });

            applyPayload(data);

            return data;
        } catch (exception) {
            handleError(exception, 'Не удалось повторить ответ.');
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function confirmAction(action, overrides = {}) {
        if (loading.value) return null;

        loading.value = true;
        error.value = null;
        fieldErrors.value = {};

        try {
            const { data } = await axios.post(route('assistant.confirm', action.id), { arguments: overrides });
            applyPayload(data);

            return data;
        } catch (exception) {
            handleError(exception, 'Не удалось выполнить действие.');
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function rejectAction(action) {
        if (loading.value) return null;

        loading.value = true;

        try {
            const { data } = await axios.post(route('assistant.reject', action.id));
            applyPayload(data);

            return data;
        } catch (exception) {
            handleError(exception, 'Не удалось отменить действие.');
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function uploadFiles(fileList) {
        const files = Array.from(fileList ?? []).filter((file) => String(file.type).startsWith('image/'));
        const room = Math.max(maxAttachments - attachments.value.length, 0);

        if (files.length === 0) {
            error.value = 'Прикрепить можно только изображения.';
            return;
        }

        if (room === 0) {
            error.value = `К сообщению можно прикрепить не больше ${maxAttachments} изображений.`;
            return;
        }

        error.value = null;

        for (const file of files.slice(0, room)) {
            if (file.size > MAX_UPLOAD_BYTES) {
                error.value = 'Изображение больше 20 МБ.';
                continue;
            }

            uploading.value += 1;
            const form = new FormData();
            form.append('file', file);

            try {
                const { data } = await axios.post(route('files.upload'), form);
                attachments.value.push({
                    id: data.id,
                    name: data.original_name,
                    mime: data.mime_type,
                    size: data.size,
                    url: data.preview_url,
                });
            } catch (exception) {
                error.value = exception?.response?.data?.message || 'Не удалось загрузить изображение.';
            } finally {
                uploading.value -= 1;
            }
        }
    }

    function removeAttachment(id) {
        attachments.value = attachments.value.filter((file) => file.id !== id);
    }

    async function loadSession(id) {
        if (!id) {
            reset();
            return null;
        }

        loading.value = true;
        error.value = null;

        try {
            const { data } = await axios.get(route('assistant.conversations.show', id));
            applyPayload(data);

            return data;
        } catch (exception) {
            handleError(exception, 'Не удалось открыть диалог.');
            return null;
        } finally {
            loading.value = false;
        }
    }

    return {
        conversationId,
        session,
        messages,
        pendingActions,
        suggestions,
        draft,
        attachments,
        loading,
        uploading,
        error,
        fieldErrors,
        canSend,
        hasMessages,
        applyPayload,
        loadSession,
        send,
        regenerate,
        confirmAction,
        rejectAction,
        uploadFiles,
        removeAttachment,
        reset,
    };
}
