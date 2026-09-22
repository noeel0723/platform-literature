export const OPEN_LOGIN_PANEL_EVENT = 'literahaven:open-login';

export function openLoginPanel() {
    window.dispatchEvent(new CustomEvent(OPEN_LOGIN_PANEL_EVENT));
}
