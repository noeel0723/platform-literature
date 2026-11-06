import { useEffect, useRef, useState } from 'react';

export default function useCatalogSearch() {
    const [state, setState] = useState({ pending: false, query: '', slow: false, error: '' });
    const active = useRef(null);
    const timer = useRef(null);

    const clearTimer = () => { window.clearTimeout(timer.current); timer.current = null; };

    useEffect(() => () => {
        clearTimer();
        active.current = null;
    }, []);

    const visitOptions = (query = '') => {
        const request = { cancel: null };
        const finish = () => {
            if (active.current !== request) return;
            clearTimer();
            active.current = null;
            setState((previous) => ({ ...previous, pending: false, slow: false }));
        };
        const fail = () => {
            if (active.current !== request) return false;
            setState((previous) => ({ ...previous, error: 'Catalog search could not finish. Check your connection and try again. Your previous results are still available below.' }));
            return false;
        };

        return {
            onBefore: () => active.current === null,
            onCancelToken: (token) => { request.cancel = token.cancel; },
            onStart: () => {
                active.current = request;
                setState({ pending: true, query: query.trim(), slow: false, error: '' });
                clearTimer();
                if (query.trim()) timer.current = window.setTimeout(() => {
                    if (active.current === request) setState((previous) => ({ ...previous, slow: true }));
                }, 10_000);
            },
            onError: fail,
            onNetworkError: fail,
            onHttpException: fail,
            onFinish: finish,
        };
    };

    return { ...state, visitOptions, cancel: () => active.current?.cancel?.() };
}
