import { useEffect, useState } from 'react';
import {
    analyticsConsentChangeEvent,
    analyticsConsentStorageKey,
    denyAnalytics,
    enableAnalytics,
    isAnalyticsConfigured,
} from '@/lib/analytics';

type AnalyticsConsentChoice = 'denied' | 'granted' | null;

function storedConsent(): AnalyticsConsentChoice {
    try {
        const choice = localStorage.getItem(analyticsConsentStorageKey);

        return choice === 'granted' || choice === 'denied' ? choice : null;
    } catch {
        return null;
    }
}

export function AnalyticsConsent() {
    const [choice, setChoice] = useState<AnalyticsConsentChoice>(() => (
        isAnalyticsConfigured ? storedConsent() : 'denied'
    ));

    useEffect(() => {
        const reopenConsent = () => {
            denyAnalytics();
            setChoice(null);
        };

        window.addEventListener(analyticsConsentChangeEvent, reopenConsent);

        return () => window.removeEventListener(analyticsConsentChangeEvent, reopenConsent);
    }, []);

    useEffect(() => {
        if (choice === 'granted') {
            return enableAnalytics();
        }

        return undefined;
    }, [choice]);

    if (!isAnalyticsConfigured || choice !== null) {
        return null;
    }

    const choose = (nextChoice: Exclude<AnalyticsConsentChoice, null>) => {
        try {
            localStorage.setItem(analyticsConsentStorageKey, nextChoice);
        } catch {
            // Apply the choice for this page even when persistence is unavailable.
        }
        setChoice(nextChoice);
    };

    return (
        <aside aria-labelledby="analytics-consent-title" className="analytics-consent">
            <div>
                <h2 id="analytics-consent-title">Help improve Purplelist?</h2>
                <p>
                    With your permission, we use Google Analytics to understand which pages are used. The tag
                    stays off unless you accept.
                </p>
            </div>
            <div className="analytics-consent__actions">
                <button onClick={() => choose('denied')} type="button">No thanks</button>
                <button className="analytics-consent__accept" onClick={() => choose('granted')} type="button">
                    Allow analytics
                </button>
            </div>
        </aside>
    );
}
