import { router } from '@inertiajs/react';

type GtagArguments = [command: string, ...parameters: unknown[]];

let initialized = false;
let lastTrackedLocation: string | null = null;

declare global {
    interface Window {
        gtag?: (...arguments_: GtagArguments) => void;
    }
}

function trackPageView(): void {
    if (!window.gtag || lastTrackedLocation === window.location.href) {
        return;
    }

    lastTrackedLocation = window.location.href;
    window.gtag?.('event', 'page_view', {
        page_location: window.location.href,
        page_path: `${window.location.pathname}${window.location.search}`,
        page_title: document.title,
    });
}

export function initializeAnalytics(): void {
    if (initialized) {
        return;
    }

    initialized = true;
    router.on('navigate', () => {
        window.requestAnimationFrame(() => {
            trackPageView();
        });
    });
}
