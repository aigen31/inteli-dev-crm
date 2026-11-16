/**
 * Клиентская логика брифов: видимость полей и прогресс.
 * Повторяет серверный BriefSchema, чтобы мастер не показывал лишнее
 * и прогресс менялся мгновенно, без запроса.
 */

export function isAnswered(value) {
    if (value === null || value === undefined) return false;
    if (typeof value === 'string') return value.trim() !== '';
    if (Array.isArray(value)) return value.some((item) => isAnswered(item));
    if (typeof value === 'object') return Object.values(value).some((item) => isAnswered(item));
    return true;
}

function scalar(value) {
    if (typeof value === 'boolean') return value ? '1' : '0';
    if (Array.isArray(value)) return value.map(scalar).join(',');
    return String(value ?? '');
}

function looseEquals(actual, expected) {
    if (typeof expected === 'boolean') return Boolean(actual) === expected;
    return scalar(actual) === scalar(expected);
}

export function isVisible(condition, answers) {
    if (!condition || !Object.keys(condition).length) return true;

    if (Array.isArray(condition.all)) {
        return condition.all.every((part) => isVisible(part, answers));
    }

    if (Array.isArray(condition.any)) {
        return condition.any.some((part) => isVisible(part, answers));
    }

    const field = condition.field;
    const op = condition.op || 'eq';
    const expected = condition.value;
    const actual = answers?.[field];

    switch (op) {
        case 'eq':
            return looseEquals(actual, expected);
        case 'neq':
            return !looseEquals(actual, expected);
        case 'in':
            return Array.isArray(expected) && expected.map(scalar).includes(scalar(actual));
        case 'not_in':
            return !(Array.isArray(expected) && expected.map(scalar).includes(scalar(actual)));
        case 'contains':
            return Array.isArray(actual) && actual.map(scalar).includes(scalar(expected));
        case 'answered':
            return isAnswered(actual);
        case 'not_answered':
            return !isAnswered(actual);
        case 'gt':
            return Number(actual) > Number(expected);
        case 'lt':
            return Number(actual) < Number(expected);
        default:
            return true;
    }
}

export function visibleFields(section, answers) {
    return (section.fields || []).filter((field) => isVisible(field.show_if, answers));
}

export function visibleSections(schema, answers) {
    return (schema.sections || [])
        .map((section) => ({ ...section, fields: visibleFields(section, answers) }))
        .filter((section) => section.fields.length > 0);
}

export function briefProgress(schema, answers) {
    let required = 0;
    let filled = 0;
    let visible = 0;
    let answered = 0;

    for (const section of schema.sections || []) {
        for (const field of visibleFields(section, answers)) {
            visible += 1;
            const has = isAnswered(answers[field.key]);

            if (has) answered += 1;

            if (field.required) {
                required += 1;
                if (has) filled += 1;
            }
        }
    }

    if (required > 0) return Math.round((filled / required) * 100);
    return visible > 0 ? Math.round((answered / visible) * 100) : 0;
}

/**
 * Поля контактного блока: короткие имена ('name') или объекты {key,label,type}.
 */
export function contactFields(field) {
    const raw = field?.meta?.fields;
    const defaults = { name: 'Имя', email: 'Email', phone: 'Телефон', telegram: 'Telegram' };

    if (!Array.isArray(raw) || raw.length === 0) {
        return Object.entries(defaults).map(([key, label]) => ({ key, label, type: key === 'email' ? 'email' : 'text' }));
    }

    return raw.map((item) => {
        if (typeof item === 'string') {
            return { key: item, label: defaults[item] || item, type: item === 'email' ? 'email' : item === 'phone' ? 'phone' : 'text' };
        }

        return { key: item.key, label: item.label || defaults[item.key] || item.key, type: item.type || 'text' };
    });
}

function optionLabel(field, value) {
    const option = (field.options || []).find((item) => String(item.value) === String(value));
    if (option) return option.label;
    return String(value) === 'other' ? 'Другое' : String(value);
}

/**
 * Человекочитаемое значение ответа (для отправленного брифа и превью).
 */
export function answerDisplay(field, value) {
    if (!isAnswered(value)) return null;

    switch (field.type) {
        case 'single_choice':
            return optionLabel(field, value);
        case 'multi_choice':
            return (value || []).map((item) => optionLabel(field, item)).join(', ');
        case 'contact_block': {
            const labels = Object.fromEntries(contactFields(field).map((item) => [item.key, item.label]));
            return Object.entries(value || {})
                .map(([key, item]) => `${labels[key] || key}: ${item}`)
                .join('\n');
        }
        case 'date': {
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('ru-RU');
        }
        case 'number':
        case 'scale': {
            const unit = field.validation?.unit ? ` ${field.validation.unit}` : '';
            return `${value}${unit}`;
        }
        case 'file':
            return 'Файл прикреплён';
        default:
            return String(value);
    }
}

/**
 * Строки секций для читаемого просмотра: [{key,title,description,rows:[{key,label,display,answered}]}]
 */
export function answerSections(schema, answers) {
    return (schema.sections || []).map((section) => {
        const fields = visibleFields(section, answers);
        return {
            key: section.key,
            title: section.title,
            description: section.description || '',
            rows: fields.map((field) => ({
                key: field.key,
                label: field.label,
                display: answerDisplay(field, answers[field.key]),
                answered: isAnswered(answers[field.key]),
            })),
        };
    });
}
