import { router } from '@inertiajs/react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';

import { actionFeedback, feedbackVisitOptions } from './actionFeedback';

export default function useFeedbackAction() {
    const active = useRef(false);
    const mounted = useRef(true);
    const [pending, setPending] = useState(false);
    const { toast } = useSyncExternalStore(actionFeedback.subscribe, actionFeedback.getSnapshot, actionFeedback.getSnapshot);
    useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);

    const visit = (method, url, data = {}, options = {}) => {
        if (active.current) return false;
        const finish = actionFeedback.begin();
        if (!finish) return false;
        active.current = true;
        setPending(true);
        const done = () => {
            active.current = false;
            finish();
            if (mounted.current) setPending(false);
        };
        try {
            router.visit(url, feedbackVisitOptions({
                ...options, method, data,
                onFinish: (visitResult) => { done(); options.onFinish?.(visitResult); },
            }));
        } catch {
            done();
            actionFeedback.error('The change could not be sent. Please refresh and try again.');
        }
        return true;
    };

    return { processing: pending || Boolean(toast?.busy), visit };
}
