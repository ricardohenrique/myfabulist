import { router } from '@inertiajs/react';

const measurementId = import.meta.env.VITE_GA_MEASUREMENT_ID?.trim();

const isAnalyticsConfigured = import.meta.env.PROD && /^G-[A-Z0-9]+$/i.test(measurementId ?? '');

type GtagArguments = [command: string, ...parameters: unknown[]];

let initialized = false;
let lastTrackedLocation: string | null = null;

declare global {
    interface Window {
        dataLayer?: GtagArguments[];
        gtag?: (...arguments_: GtagArguments) => void;
    }
}

function gtag(...arguments_: GtagArguments): void {
    window.dataLayer ??= [];
    window.dataLayer.push(arguments_);
}

function trackPageView(): void {
    if (lastTrackedLocation === window.location.href) {
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
    if (!isAnalyticsConfigured || !measurementId || initialized) {
        return;
    }

    initialized = true;
    window.gtag = gtag;
    window.gtag('js', new Date());
    window.gtag('config', measurementId, {
        send_page_view: false,
    });

    const script = document.createElement('script');
    script.async = true;
    script.dataset.purplelistAnalytics = 'true';
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);

    trackPageView();

    router.on('navigate', () => {
        window.requestAnimationFrame(() => {
            trackPageView();
        });
    });
}
