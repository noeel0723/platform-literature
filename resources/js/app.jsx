import './app.js';

import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

const inertiaRoot = document.getElementById('app');
const inertiaPage = document.querySelector('script[data-page="app"]');

if (inertiaRoot && (inertiaRoot.dataset.page || inertiaPage)) {
    void createInertiaApp({
        resolve: async (name) => {
            const pages = import.meta.glob('./Pages/**/*.jsx');
            const resolvePage = pages[`./Pages/${name}.jsx`];

            if (!resolvePage) {
                throw new Error(`Unknown Inertia page: ${name}`);
            }

            return resolvePage();
        },
        setup({ el, App, props }) {
            createRoot(el).render(
                <StrictMode>
                    <App {...props} />
                </StrictMode>,
            );
        },
        progress: {
            color: '#ff6b58',
        },
    });
}
