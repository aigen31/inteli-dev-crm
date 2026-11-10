import { t } from '@/i18n';

const FALLBACKS = {
    'validation.required': 'Заполните поле',
    'validation.string': 'Ожидается текст',
    'validation.email': 'Некорректный email',
    'validation.url': 'Некорректная ссылка',
    'validation.date': 'Некорректная дата',
    'validation.numeric': 'Нужно число',
    'validation.boolean': 'Некорректное значение',
    'validation.accepted': 'Нужно подтвердить',
    'validation.in': 'Недопустимое значение',
    'validation.uuid': 'Некорректный идентификатор',
    'validation.min.array': 'Добавьте хотя бы одну запись',
    'validation.max.array': 'Слишком много записей',
    'validation.file': 'Некорректный файл',
};

/**
 * Laravel returns raw validation keys when a locale message is absent.
 * Normalize the common ones at the presentation boundary.
 */
export function validationMessage(value) {
    const message = Array.isArray(value) ? value[0] : value;

    if (typeof message !== 'string' || message === '') {
        return null;
    }

    if (FALLBACKS[message]) {
        return t(FALLBACKS[message]);
    }

    if (message.startsWith('validation.')) {
        return t('Проверьте значение');
    }

    return message;
}
