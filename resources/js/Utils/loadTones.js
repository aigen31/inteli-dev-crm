// Единый стандарт цветов нагрузки для всех календарей CRM:
// 0 — без заливки, 1 — спокойно (зелёный), 2 — умеренно (жёлтый),
// 3 — плотно (оранжевый), 4+ — пик (красный).
export const LOAD_TONES = {
    light: [
        { background: 'transparent', color: 'inherit' },
        { background: '#E8F3EC', color: '#2F8F62' },
        { background: '#FBF0D9', color: '#B7791F' },
        { background: '#F6D8C4', color: '#A96120' },
        { background: '#F2C7C1', color: '#A33A33' },
    ],
    dark: [
        { background: 'transparent', color: 'inherit' },
        { background: '#1d2a22', color: '#7fd2a7' },
        { background: '#2f2818', color: '#e5c07b' },
        { background: '#33231a', color: '#e8a87c' },
        { background: '#3a1e1e', color: '#f1998f' },
    ],
};

export function loadTone(load) {
    const dark = typeof document !== 'undefined' && document.documentElement.dataset.theme === 'dark';
    const palette = dark ? LOAD_TONES.dark : LOAD_TONES.light;

    return palette[Math.min(Math.max(Number(load) || 0, 0), 4)];
}
