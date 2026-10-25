const focalDefaults = {
    mobile: { x: 70, y: 40 },
    tablet: { x: 60, y: 40 },
    desktop: { x: 50, y: 40 },
};

export function guestHeroBackdrop(literature, failedUrl = null) {
    const url = typeof literature?.backdrop_url === 'string' ? literature.backdrop_url.trim() : '';
    return url && url !== failedUrl ? url : null;
}

export function guestHeroFocalStyle(position) {
    return Object.fromEntries(Object.entries(focalDefaults).map(([breakpoint, fallback]) => {
        const point = position?.[breakpoint];
        const coordinates = ['x', 'y'].map((axis) => {
            const value = point?.[axis];
            return typeof value === 'number' && Number.isFinite(value)
                ? Math.max(0, Math.min(100, value))
                : fallback[axis];
        });

        return [`--hero-focal-${breakpoint}`, `${coordinates[0]}% ${coordinates[1]}%`];
    }));
}
