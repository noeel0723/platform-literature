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

    if (dialog.dataset.autoOpen === 'true' || (dialog.id === 'review-dialog' && reviewWasRequested)) {
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

            const dialogId = ratingGroup.dataset.ratingDialog;
            const dialog = dialogId ? document.getElementById(dialogId) : null;

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

const quickLogSearchDialog = document.querySelector('[data-quick-log-search-dialog]');
const quickLogReviewDialog = document.querySelector('[data-quick-log-review-dialog]');

if (quickLogSearchDialog instanceof HTMLDialogElement && quickLogReviewDialog instanceof HTMLDialogElement) {
    const searchInput = quickLogSearchDialog.querySelector('[data-quick-log-input]');
    const results = quickLogSearchDialog.querySelector('[data-quick-log-results]');
    const reviewForm = quickLogReviewDialog.querySelector('[data-quick-log-review-form]');
    const ratingInput = quickLogReviewDialog.querySelector('#quick-log-rating');
    const ratingError = quickLogReviewDialog.querySelector('[data-quick-log-rating-error]');
    const reviewBody = quickLogReviewDialog.querySelector('[data-quick-log-body]');
    const spoilerInput = quickLogReviewDialog.querySelector('[data-quick-log-spoiler]');
    const cover = quickLogReviewDialog.querySelector('[data-quick-log-cover]');
    const coverFallback = quickLogReviewDialog.querySelector('[data-quick-log-cover-fallback]');
    const title = quickLogReviewDialog.querySelector('[data-quick-log-title]');
    const meta = quickLogReviewDialog.querySelector('[data-quick-log-meta]');
    const type = quickLogReviewDialog.querySelector('[data-quick-log-type]');
    const submitButton = quickLogReviewDialog.querySelector('[data-quick-log-submit]');
    let searchTimer;
    let searchRequest;

    const showSearchState = (message) => {
        if (!results) {
            return;
        }

        results.replaceChildren();
        const state = document.createElement('p');
        state.className = 'px-4 py-8 text-center text-sm text-ink-950/50';
        state.textContent = message;
        results.append(state);
    };

    const openReview = (literature) => {
        if (!(reviewForm instanceof HTMLFormElement) || !(ratingInput instanceof HTMLInputElement) || !(reviewBody instanceof HTMLTextAreaElement) || !(spoilerInput instanceof HTMLInputElement)) {
            return;
        }

        const review = literature.review ?? {};
        const initials = literature.title
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word.charAt(0).toLocaleUpperCase())
            .join('');

        reviewForm.action = literature.review_url;
        ratingInput.value = review.rating ? Number(review.rating).toFixed(1) : '';
        ratingInput.dispatchEvent(new Event('change', { bubbles: true }));
        reviewBody.value = review.body ?? '';
        spoilerInput.checked = Boolean(review.contains_spoiler);
        ratingError?.classList.add('hidden');

        if (title) {
            title.textContent = literature.title;
        }

        if (meta) {
            const authorNames = literature.authors?.length ? literature.authors.join(' & ') : 'Author unavailable';
            meta.textContent = literature.year ? `${authorNames} · ${literature.year}` : authorNames;
        }

        if (type) {
            type.textContent = literature.type ?? 'Literature';
        }

        if (cover instanceof HTMLImageElement && coverFallback) {
            if (literature.cover_url) {
                cover.src = literature.cover_url;
                cover.alt = `Cover of ${literature.title}`;
                cover.classList.remove('hidden');
                coverFallback.classList.add('hidden');
            } else {
                cover.removeAttribute('src');
                cover.alt = '';
                cover.classList.add('hidden');
                coverFallback.textContent = initials || 'LH';
                coverFallback.classList.remove('hidden');
            }
        }

        if (submitButton) {
            submitButton.textContent = literature.review ? 'Update review' : 'Publish review';
        }

        quickLogSearchDialog.close();
        quickLogReviewDialog.showModal();
    };

    const renderResults = (literatures) => {
        if (!results) {
            return;
        }

        if (literatures.length === 0) {
            showSearchState('No literature matched your search.');

            return;
        }

        results.replaceChildren();

        literatures.forEach((literature) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'quick-log-result';

            const visual = document.createElement('span');
            visual.className = 'quick-log-result-cover';

            if (literature.cover_url) {
                const image = document.createElement('img');
                image.src = literature.cover_url;
                image.alt = '';
                image.loading = 'lazy';
                image.className = 'size-full object-cover';
                visual.append(image);
            } else {
                visual.textContent = literature.title.slice(0, 2).toLocaleUpperCase();
            }

            const copy = document.createElement('span');
            copy.className = 'min-w-0 flex-1 text-left';

            const heading = document.createElement('span');
            heading.className = 'block truncate font-serif text-base font-bold text-ink-950';
            heading.textContent = literature.title;

            const author = document.createElement('span');
            author.className = 'mt-0.5 block truncate text-xs text-ink-950/50';
            author.textContent = literature.authors?.length ? literature.authors.join(' & ') : 'Author unavailable';

            const details = document.createElement('span');
            details.className = 'mt-1 block text-[0.65rem] font-bold uppercase tracking-[0.12em] text-ink-950/40';
            details.textContent = [literature.type, literature.year].filter(Boolean).join(' · ');

            copy.append(heading, author, details);

            const action = document.createElement('span');
            action.className = 'shrink-0 text-xs font-bold uppercase tracking-wider text-brand-stem';
            action.textContent = literature.review ? 'Edit' : 'Log';

            button.append(visual, copy, action);
            button.addEventListener('click', () => openReview(literature));
            results.append(button);
        });
    };

    const loadLiteratures = async (query = '') => {
        searchRequest?.abort();
        searchRequest = new AbortController();
        showSearchState(query ? 'Searching literature...' : 'Loading recent literature...');

        try {
            const url = new URL(quickLogSearchDialog.dataset.searchUrl, window.location.origin);

            if (query) {
                url.searchParams.set('q', query);
            }

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: searchRequest.signal,
            });

            if (!response.ok) {
                throw new Error('Quick log search failed.');
            }

            const payload = await response.json();
            renderResults(Array.isArray(payload.data) ? payload.data : []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                showSearchState('Search is temporarily unavailable. Please try again.');
            }
        }
    };

    document.querySelectorAll('[data-quick-log-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
                window.requestAnimationFrame(() => searchInput.focus());
            }

            loadLiteratures();
        });
    });

    if (searchInput instanceof HTMLInputElement) {
        searchInput.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => loadLiteratures(searchInput.value.trim()), 250);
        });
    }

    quickLogReviewDialog.querySelector('[data-quick-log-back]')?.addEventListener('click', () => {
        quickLogReviewDialog.close();
        quickLogSearchDialog.showModal();
        window.requestAnimationFrame(() => searchInput?.focus());
    });

    reviewForm?.addEventListener('submit', (event) => {
        if (!(ratingInput instanceof HTMLInputElement) || Number(ratingInput.value) <= 0) {
            event.preventDefault();
            ratingError?.classList.remove('hidden');
            quickLogReviewDialog.querySelector('[data-rating-value]')?.focus();
        }
    });
}
