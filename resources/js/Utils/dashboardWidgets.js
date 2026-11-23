export const defaultWidgetLayout = [
    { id: 'clients', w: 1, h: 1 },
    { id: 'active_tasks', w: 1, h: 1 },
    { id: 'revenue', w: 1, h: 1 },
    { id: 'focus', w: 2, h: 2 },
    { id: 'activity', w: 2, h: 2 },
    { id: 'deadlines', w: 2, h: 1 },
    { id: 'quick_links', w: 2, h: 1 },
    { id: 'activity_calendar', w: 2, h: 3 },
    { id: 'user_workload', w: 2, h: 3 },
];

// Виджеты из каталога, которых нет в раскладке по умолчанию.
const optionalWidgetIds = ['navigation_trail'];
const allowedIds = new Set([...defaultWidgetLayout.map((item) => item.id), ...optionalWidgetIds]);

// Календарь с сеткой месяца не влезает в две строки — держим минимум три.
const minHeight = { activity_calendar: 3, user_workload: 3, navigation_trail: 2 };

export function normalizeWidgetLayout(value) {
    if (!Array.isArray(value)) return defaultWidgetLayout;
    const seen = new Set();
    const result = [];
    value.forEach((row) => {
        if (!row || !allowedIds.has(row.id) || seen.has(row.id)) return;
        seen.add(row.id);
        result.push({
            id: row.id,
            w: Math.min(4, Math.max(1, Number(row.w) || 1)),
            h: Math.min(4, Math.max(minHeight[row.id] ?? 1, Number(row.h) || 1)),
        });
    });
    return result;
}
