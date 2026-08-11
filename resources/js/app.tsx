import { createInertiaApp } from '@inertiajs/react';
import '../css/app.css';

createInertiaApp({
    pages: {
        path: './pages',
        extension: '.tsx',
        lazy: true,
    },
    progress: {
        color: '#f04438',
    },
    strictMode: true,
});
