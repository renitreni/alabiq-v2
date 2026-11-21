import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

const appName = import.meta.env.VITE_APP_NAME || 'Alabiq';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // @ts-expect-error - Inertia types mismatch with Vite's glob return type
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    // @ts-expect-error - Inertia types mismatch with React 19
    setup({ el, App, props }) {
        return createRoot(el).render(
            <BrowserRouter>
                <App {...props} />
            </BrowserRouter>,
        );
    },
    progress: {
        color: '#E53E3E',
    },
});