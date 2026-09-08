const mobileMenuButton = document.querySelector('[data-mobile-menu-button]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

mobileMenuButton?.addEventListener('click', () => {
    const isExpanded = mobileMenuButton.getAttribute('aria-expanded') === 'true';

    mobileMenuButton.setAttribute('aria-expanded', String(!isExpanded));
    mobileMenu?.classList.toggle('hidden');
});

document.querySelectorAll('[data-header-search]').forEach((searchForm) => {
    const input = searchForm.querySelector('input[name="q"]');
    const button = searchForm.querySelector('[data-header-search-button]');

    if (!(searchForm instanceof HTMLFormElement) || !(input instanceof HTMLInputElement) || !(button instanceof HTMLButtonElement)) {
        return;
    }

    const setExpanded = (isExpanded) => {
        searchForm.dataset.expanded = String(isExpanded);
        button.setAttribute('aria-expanded', String(isExpanded));
        button.setAttribute('aria-label', isExpanded ? 'Search' : 'Open search');
        input.tabIndex = isExpanded ? 0 : -1;
        input.setAttribute('aria-hidden', String(!isExpanded));

        if (isExpanded) {
            window.requestAnimationFrame(() => input.focus());
        }
    };

    searchForm.addEventListener('submit', (event) => {
        if (searchForm.dataset.expanded !== 'true') {
            event.preventDefault();
            setExpanded(true);

            return;
        }

        if (input.value.trim() === '') {
            event.preventDefault();
            input.focus();
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (searchForm.dataset.expanded === 'true' && !searchForm.contains(event.target)) {
            setExpanded(false);
        }
    });

    searchForm.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setExpanded(false);
            button.focus();
        }
    });
});

document.querySelectorAll('[data-account-menu]').forEach((accountMenu) => {
    const button = accountMenu.querySelector('[data-account-menu-button]');

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const setOpen = (isOpen) => {
        accountMenu.dataset.open = String(isOpen);
        button.setAttribute('aria-expanded', String(isOpen));
    };

    button.addEventListener('click', () => setOpen(accountMenu.dataset.open !== 'true'));

    document.addEventListener('pointerdown', (event) => {
        if (!accountMenu.contains(event.target)) {
            setOpen(false);
        }
    });

    accountMenu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
            button.focus();
        }
    });
});

const localDateTimeFormatter = new Intl.DateTimeFormat('en', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
const readerTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

document.querySelectorAll('[data-local-datetime]').forEach((element) => {
    const date = new Date(element.getAttribute('datetime'));

    if (Number.isNaN(date.getTime())) {
        return;
    }

    element.textContent = `${localDateTimeFormatter.format(date)} (${readerTimeZone})`;
    element.setAttribute('title', `Displayed in your local time zone: ${readerTimeZone}`);
});

const localDatePartFormatters = {
    month: new Intl.DateTimeFormat('en', { month: 'short' }),
    day: new Intl.DateTimeFormat('en', { day: '2-digit' }),
    year: new Intl.DateTimeFormat('en', { year: 'numeric' }),
};

document.querySelectorAll('[data-local-date-part]').forEach((element) => {
    const date = new Date(element.getAttribute('datetime'));
    const part = element.dataset.localDatePart;
    const formatter = localDatePartFormatters[part];

    if (Number.isNaN(date.getTime()) || !formatter) {
        return;
    }

    const value = formatter.format(date);
    element.textContent = part === 'month' ? value.toUpperCase() : value;
    element.setAttribute('title', `${localDateTimeFormatter.format(date)} (${readerTimeZone})`);
});

document.querySelectorAll('[data-spoiler-reveal]').forEach((button) => {
    button.addEventListener('click', () => {
        const spoiler = document.getElementById(button.getAttribute('aria-controls'));

        spoiler?.removeAttribute('hidden');
        button.remove();
    });
});

document.querySelectorAll('[data-readlist-suggestion-search]').forEach((input) => {
    const panel = input.closest('[data-readlist-add-panel]');

    if (!(input instanceof HTMLInputElement) || !panel) {
        return;
    }

    const suggestions = Array.from(panel.querySelectorAll('[data-readlist-suggestion]'));
    const emptyState = panel.querySelector('[data-readlist-no-results]');

    input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        suggestions.forEach((suggestion) => {
            const isVisible = (suggestion.dataset.searchText ?? '').includes(query);

            suggestion.classList.toggle('hidden', !isVisible);
            visibleCount += isVisible ? 1 : 0;
        });

        emptyState?.classList.toggle('hidden', visibleCount > 0 || suggestions.length === 0);
    });
});

document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.dialogOpen);

        if (dialog instanceof HTMLDialogElement && !dialog.open) {
            dialog.showModal();
        }
    });
});

document.querySelectorAll('[data-review-dialog]').forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    const reviewWasRequested = new URLSearchParams(window.location.search).get('review') === 'edit';

    if (dialog.dataset.autoOpen === 'true' || reviewWasRequested) {
        dialog.showModal();
    }
});

document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirmSubmit)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-star-rating]').forEach((ratingGroup) => {
    const input = document.getElementById(ratingGroup.dataset.ratingInput);
    const output = ratingGroup.parentElement?.querySelector('[data-rating-output]');
    const ratingButtons = Array.from(ratingGroup.querySelectorAll('[data-rating-value]'));

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const renderRating = (rating) => {
        ratingButtons.forEach((button) => {
            const isSelected = Number(button.dataset.ratingValue) <= rating;

            button.classList.toggle('text-brand-coral', isSelected);
            button.classList.toggle('text-ink-950/15', !isSelected);
            button.setAttribute('aria-checked', String(Number(button.dataset.ratingValue) === rating));
        });

        if (output) {
            output.textContent = rating > 0 ? `${rating.toFixed(1)} / 5` : 'Choose a rating';
        }
    };

    ratingButtons.forEach((button) => {
        const previewRating = () => renderRating(Number(button.dataset.ratingValue));

        button.addEventListener('pointerenter', previewRating);
        button.addEventListener('focus', previewRating);
        button.addEventListener('click', () => {
            const rating = Number(button.dataset.ratingValue);

            input.value = rating.toFixed(1);
            input.dispatchEvent(new Event('change', { bubbles: true }));

            const dialog = document.getElementById(ratingGroup.dataset.ratingDialog);

            if (dialog instanceof HTMLDialogElement && !dialog.open) {
                dialog.showModal();
            }
        });
    });

    input.addEventListener('change', () => renderRating(Number(input.value) || 0));
    ratingGroup.addEventListener('pointerleave', () => renderRating(Number(input.value) || 0));
    ratingGroup.addEventListener('focusout', (event) => {
        if (!ratingGroup.contains(event.relatedTarget)) {
            renderRating(Number(input.value) || 0);
        }
    });

    renderRating(Number(input.value) || 0);
});
