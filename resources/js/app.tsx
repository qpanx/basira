import { createInertiaApp } from '@inertiajs/react';

const appName = import.meta.env.VITE_APP_NAME || 'BASIRA';

void createInertiaApp({
    title: (title) => title || appName,
    progress: {
        color: '#4B5563',
    },
});
