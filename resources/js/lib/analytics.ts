import { router } from '@inertiajs/react';

const measurementId = import.meta.env.VITE_GA_MEASUREMENT_ID?.trim();

export const analyticsConsentStorageKey = 'purplelist.analytics-consent.v1';
export const analyticsConsentChangeEvent = 'purplelist:analytics-consent-change';
export const isAnalyticsConfigured = import.meta.env.PROD && /^G-[A-Z0-9]+$/i.test(measurementId ?? '');

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

export function enableAnalytics(): () => void {
    if (!isAnalyticsConfigured || !measurementId) {
        return () => undefined;
    }

    window.gtag = gtag;
    if (!initialized) {
        window.gtag('consent', 'default', {
            ad_personalization: 'denied',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            analytics_storage: 'denied',
        });
    }
    window.gtag('consent', 'update', {
        analytics_storage: 'granted',
    });
    if (!initialized) {
        initialized = true;
        window.gtag('js', new Date());
        window.gtag('config', measurementId, {
            send_page_view: false,
        });

        const script = document.createElement('script');
        script.async = true;
        script.dataset.purplelistAnalytics = 'true';
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
        document.head.appendChild(script);
    }

    trackPageView();

    let active = true;
    const removeNavigateListener = router.on('navigate', () => {
        window.requestAnimationFrame(() => {
            if (active) {
                trackPageView();
            }
        });
    });

    return () => {
        active = false;
        removeNavigateListener();
    };
}

export function denyAnalytics(): void {
    lastTrackedLocation = null;
    window.gtag?.('consent', 'update', {
        analytics_storage: 'denied',
    });
}

export function reopenAnalyticsConsent(): void {
    try {
        localStorage.removeItem(analyticsConsentStorageKey);
    } catch {
        // The banner remains usable when storage is blocked by the browser.
    }
    window.dispatchEvent(new Event(analyticsConsentChangeEvent));
}
