import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { initializeAnalytics } from '@/lib/analytics';
import '../css/app.css';

createInertiaApp({
    pages: {
        path: './pages',
        extension: '.tsx',
        lazy: true,
    },
    progress: {
        color: '#8b6fd6',
    },
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        createRoot(el).render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );

        initializeAnalytics();
    },
});
