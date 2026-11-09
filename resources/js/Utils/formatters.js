import { localeTag } from '@/i18n';

export function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString(localeTag(), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export function formatDateTime(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleString(localeTag(), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatMoney(amount, currency = 'RUB') {
    if (amount == null) return '—';
    return new Intl.NumberFormat(localeTag(), {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount);
}

/**
 * Human "12 минут назад" for the navigation trail.
 * `style` follows Intl.RelativeTimeFormat: long for the resume card, short for rows.
 */
export function formatRelativeTime(dateString, style = 'long') {
    if (!dateString) return '';

    const value = new Date(dateString).getTime();
    if (Number.isNaN(value)) return '';

    const diff = value - Date.now();
    const abs = Math.abs(diff);
    const formatter = new Intl.RelativeTimeFormat(localeTag(), { numeric: 'auto', style });

    if (abs < 3_600_000) return formatter.format(Math.round(diff / 60_000), 'minute');
    if (abs < 86_400_000) return formatter.format(Math.round(diff / 3_600_000), 'hour');
    if (abs < 7 * 86_400_000) return formatter.format(Math.round(diff / 86_400_000), 'day');

    return formatDate(dateString);
}

