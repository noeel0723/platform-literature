export function previewPosition(anchor, viewport, measuredHeight, margin = 12) {
    const width = Math.max(0, Math.min(256, viewport.width - margin * 2));
    const maxHeight = Math.max(0, viewport.height - margin * 2);
    const height = Math.min(measuredHeight, maxHeight);
    const minLeft = viewport.left + margin;
    const minTop = viewport.top + margin;
    const maxLeft = viewport.left + viewport.width - width - margin;
    const maxTop = viewport.top + viewport.height - height - margin;
    const right = anchor.right + 8;
    const left = right + width <= viewport.left + viewport.width - margin
        ? right
        : anchor.left - width - 8;

    return {
        width,
        maxHeight,
        left: Math.max(minLeft, Math.min(left, maxLeft)),
        top: Math.max(minTop, Math.min(anchor.bottom - height, maxTop)),
    };
}

export function previewActionIndex(key, current, count) {
    if (!count) return null;
    if (key === 'Home') return 0;
    if (key === 'End') return count - 1;
    if (key === 'ArrowDown') return (current + 1) % count;
    if (key === 'ArrowUp') return current <= 0 ? count - 1 : current - 1;
    return null;
}

export function previewTabBoundary(current, count, shiftKey) {
    if (shiftKey && current <= 0) return 'trigger';
    if (!shiftKey && current === -1) return 'first';
    if (!shiftKey && current === count - 1) return 'next';
    return null;
}

export function focusablePreviewActions(root) {
    return Array.from(root.querySelectorAll('a[href], button, input, select, textarea, [tabindex]'))
        .filter((element) => element.tabIndex >= 0
            && !element.matches(':disabled')
            && !element.closest('[inert], [aria-hidden="true"]')
            && element.getClientRects().length > 0
            && element.ownerDocument.defaultView.getComputedStyle(element).visibility !== 'hidden');
}
