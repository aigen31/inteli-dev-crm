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
