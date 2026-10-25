import { createInertiaApp, router } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import AppHeader from './Components/AppHeader';
import ActionToast from './Components/ActionToast';
import { actionFeedback, ACTION_RESTORED_EVENT } from './Support/actionFeedback';
import { installReviewDraftPrivacy, reviewDrafts } from './Support/reviewDraft';

const headerProps = document.querySelector('[data-react-header-props]');
const initialHeader = headerProps ? JSON.parse(headerProps.textContent) : null;
installReviewDraftPrivacy({ repository: reviewDrafts, browserWindow: window, browserDocument: document, router, userId: initialHeader?.user?.id, logoutUrl: initialHeader?.user?.logout_url });

const feedbackRoot = document.createElement('div');
feedbackRoot.dataset.actionFeedbackRoot = '';
document.body.append(feedbackRoot);
createRoot(feedbackRoot).render(<StrictMode><ActionToast /></StrictMode>);
actionFeedback.setRefresh((scope) => new Promise((resolve, reject) => {
    window.dispatchEvent(new CustomEvent(ACTION_RESTORED_EVENT, { detail: { scope } }));
    if (!document.getElementById('app')) { window.location.reload(); resolve(); return; }
    router.reload({ onSuccess: resolve, onError: reject, onNetworkError: () => { reject(); return false; }, onHttpException: () => { reject(); return false; } });
}));
router.on('success', (event) => actionFeedback.publish(event.detail.page.props.actionFeedback));

const inertiaRoot = document.getElementById('app');
const inertiaPage = document.querySelector('script[data-page="app"]');

if (inertiaRoot && (inertiaRoot.dataset.page || inertiaPage)) {
    void createInertiaApp({
        resolve: async (name) => {
            const pages = import.meta.glob('./Pages/**/*.jsx');
            const resolvePage = pages[`./Pages/${name}.jsx`];

            if (!resolvePage) {
                throw new Error(`Unknown Inertia page: ${name}`);
            }

            return resolvePage();
        },
        setup({ el, App, props }) {
            actionFeedback.publish(props.initialPage.props.actionFeedback);
            createRoot(el).render(
                <StrictMode>
                    <App {...props} />
                </StrictMode>,
            );
        },
        progress: {
            color: '#ff6b58',
        },
    });
}

const headerRoot = document.querySelector('[data-react-header]');

if (headerRoot && headerProps) {
    createRoot(headerRoot).render(
        <StrictMode>
            <AppHeader {...initialHeader} />
        </StrictMode>,
    );
}
