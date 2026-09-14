import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'; 

const appName = import.meta.env.VITE_APP_NAME || 'Apotek Kinanthi';

const queryClient = new QueryClient();

router.on('finish', () => {
    queryClient.invalidateQueries();
});

createInertiaApp({
    title: (title) => title ? `${title} | Apotek Kinanthi` : 'Apotek Kinanthi',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(VueQueryPlugin, { queryClient }) 
            .mount(el);
    },
    progress: {
        delay: 250,
        color: '#27272a', 
        includeCSS: true,
        showSpinner: false,
    },
});