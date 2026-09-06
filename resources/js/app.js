const mobileMenuButton = document.querySelector('[data-mobile-menu-button]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

mobileMenuButton?.addEventListener('click', () => {
    const isExpanded = mobileMenuButton.getAttribute('aria-expanded') === 'true';

    mobileMenuButton.setAttribute('aria-expanded', String(!isExpanded));
    mobileMenu?.classList.toggle('hidden');
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

document.querySelectorAll('[data-spoiler-reveal]').forEach((button) => {
    button.addEventListener('click', () => {
        const spoiler = document.getElementById(button.getAttribute('aria-controls'));

        spoiler?.removeAttribute('hidden');
        button.remove();
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

    if (dialog.dataset.autoOpen === 'true') {
        dialog.showModal();
    }
});

document.querySelectorAll('[data-star-rating]').forEach((ratingGroup) => {
    const input = document.getElementById(ratingGroup.dataset.ratingInput);
    const output = ratingGroup.parentElement?.querySelector('[data-rating-output]');
    const ratingButtons = Array.from(ratingGroup.querySelectorAll('[data-rating-value]'));

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    let selectedRating = Number(input.value) || 0;

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

            selectedRating = rating;
            input.value = rating.toFixed(1);
            renderRating(rating);
        });
    });

    ratingGroup.addEventListener('pointerleave', () => renderRating(selectedRating));
    ratingGroup.addEventListener('focusout', (event) => {
        if (!ratingGroup.contains(event.relatedTarget)) {
            renderRating(selectedRating);
        }
    });

    renderRating(selectedRating);
});
