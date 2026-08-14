import { createInertiaApp } from '@inertiajs/react';
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
    strictMode: true,
});
