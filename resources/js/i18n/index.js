import messages from './messages.json';

export const locales = [
    { code: 'en', label: 'English', short: 'EN' },
    { code: 'ru', label: 'Русский', short: 'RU' },
    { code: 'zh_CN', label: '中文', short: 'ZH' },
];

let current = 'en';

export function normalizeLocale(value) {
    return locales.some((item) => item.code === value) ? value : 'en';
}

export function getLocale() {
    return current;
}

export function localeTag() {
    return current === 'zh_CN' ? 'zh-CN' : current;
}

export function setLocale(value) {
    current = normalizeLocale(value);
    if (typeof document !== 'undefined') {
        document.documentElement.lang = current === 'zh_CN' ? 'zh-CN' : current;
    }
}

export function t(key) {
    if (!key || current === 'ru') return key;
    const column = current === 'zh_CN' ? 'zh' : current;
    const lower = key.toLocaleLowerCase('ru-RU');
    const title = lower.charAt(0).toLocaleUpperCase('ru-RU') + lower.slice(1);
    const row = messages[key] ?? messages[lower] ?? messages[title];

    return row?.[column] ?? key;
}

const TRANSLATABLE_ATTRIBUTES = ['placeholder', 'title', 'aria-label', 'alt'];

function isSkipped(element) {
    return !!element?.closest?.('[data-i18n-skip], .prose-kiki, script, style, noscript, textarea, [contenteditable="true"]');
}

function translateTextNode(node) {
    if (isSkipped(node.parentElement)) return;

    const raw = node.nodeValue ?? '';
    const key = raw.trim();
    if (key === '') return;

    const translated = t(key);
    if (translated === key) return;

    const start = raw.indexOf(key);
    if (start === -1) return;
    node.nodeValue = raw.slice(0, start) + translated + raw.slice(start + key.length);
}

function translateElementAttributes(element) {
    if (isSkipped(element)) return;

    for (const name of TRANSLATABLE_ATTRIBUTES) {
        const value = element.getAttribute(name);
        if (!value) continue;
        const translated = t(value.trim());
        if (translated !== value) element.setAttribute(name, translated);
    }
}

export function translateDocumentTitle() {
    if (typeof document === 'undefined' || current === 'ru') return;
    const [title, suffix] = document.title.split(' — ');
    const translated = t(title);
    if (translated !== title) {
        document.title = suffix ? `${translated} — ${suffix}` : translated;
    }
}

export function translateDom(root = document.body) {
    if (typeof document === 'undefined' || !root || current === 'ru') return;

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT);
    const nodes = [];

    while (walker.nextNode()) nodes.push(walker.currentNode);

    for (const node of nodes) {
        if (node.nodeType === Node.TEXT_NODE) {
            translateTextNode(node);
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            translateElementAttributes(node);
        }
    }

    translateDocumentTitle();
}

let observer = null;
let scheduled = false;

function scheduleTranslation() {
    if (scheduled || current === 'ru') return;
    scheduled = true;

    window.requestAnimationFrame(() => {
        scheduled = false;
        translateDom(document.body);
        translateDocumentTitle();
    });
}

export function startI18n(initialLocale = 'en') {
    setLocale(initialLocale);
    translateDom(document.body);
    window.setTimeout(translateDocumentTitle, 100);

    if (observer !== null || typeof MutationObserver === 'undefined') return;

    observer = new MutationObserver(scheduleTranslation);
    const options = {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true,
        attributeFilter: TRANSLATABLE_ATTRIBUTES,
    };
    observer.observe(document.body, options);
    if (document.head) observer.observe(document.head, options);
}
