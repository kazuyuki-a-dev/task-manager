import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';

// ページの探し方と画面への組みこみは、vite.config.js の inertia() が受けもつ
createInertiaApp({
    pages: {
        path: './pages',
        extension: '.tsx',
    },
});
