import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { setLocale, startI18n, t, translateDom } from '@/i18n';

const appName = 'CRM';

function applyTheme(theme) {
    document.documentElement.dataset.theme = ['default', 'dark', 'xp'].includes(theme) ? theme : 'default';
}

createInertiaApp({
    title: (title) => title ? `${title} — ${appName}` : appName,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const initialLocale = props.initialPage.props.locale || 'en';
        setLocale(initialLocale);
        applyTheme(props.initialPage.props.theme);

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, props.initialPage.props.ziggy);

        window.route = app.config.globalProperties.route;
        app.config.globalProperties.$t = t;

        router.on('success', (event) => {
            const locale = event.detail.page.props.locale || 'en';
            setLocale(locale);
            applyTheme(event.detail.page.props.theme);
            window.requestAnimationFrame(() => translateDom(document.body));
        });

        const mounted = app.mount(el);
        startI18n(initialLocale);

        return mounted;
    },
    progress: {
        color: '#6366F1',
    },
});
