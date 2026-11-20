import '../css/app.css';
import './echo';

import { Sonner, toast } from '@/components/ui/sonner';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import 'vue-sonner/style.css';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'KasamaHR';

interface FlashedPage {
    component: string;
    props: { flash?: { success?: string | null; error?: string | null } };
}

/** Pages that already show the flashed message inline. */
const pagesWithInlineFlash = ['Careers/Show', 'Visitor/Register'];

let lastFlash: unknown = null;

/**
 * Show the server's flashed success/error message as a toast. A partial
 * reload keeps the previous flash object, so the same object is shown once.
 */
function showFlash(page: FlashedPage): void {
    const flash = page.props.flash;

    if (
        !flash ||
        flash === lastFlash ||
        pagesWithInlineFlash.includes(page.component)
    ) {
        return;
    }

    lastFlash = flash;

    if (flash.success) {
        toast.success(flash.success);
    }

    if (flash.error) {
        toast.error(flash.error, { duration: 10000 });
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => [h(App, props), h(Sonner)] })
            .use(plugin)
            .mount(el);

        setTimeout(() => showFlash(props.initialPage as FlashedPage));
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('success', (event) => showFlash(event.detail.page as FlashedPage));

// This will set light / dark mode on page load...
initializeTheme();
