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
