(() => {
    if (window.mangasanPageTransitionReady) {
        return;
    }

    window.mangasanPageTransitionReady = true;

    const transition = document.getElementById('mangaPageTransition');

    if (!transition) {
        return;
    }

    const logoImage = document.getElementById('mangaTransitionLogo');
    const door = document.getElementById('mangaTransitionDoor') || transition.querySelector('.manga-transition-door');
    const sourceLogo = document.querySelector('.site-logo-image');

    if (logoImage && door && sourceLogo && sourceLogo.getAttribute('src')) {
        logoImage.src = sourceLogo.getAttribute('src');
        logoImage.classList.add('is-active');
        door.classList.add('has-image');
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const resetTransition = () => {
        document.body.classList.remove('is-transitioning');
        transition.classList.remove('is-visible', 'is-rotating', 'is-opening');
        transition.setAttribute('aria-hidden', 'true');
    };

    resetTransition();

    if (sessionStorage.getItem('mangasanPageTransition') === '1') {
        sessionStorage.removeItem('mangasanPageTransition');
        document.body.classList.add('manga-page-enter');
        window.setTimeout(() => {
            document.body.classList.remove('manga-page-enter');
        }, 520);
    }

    window.addEventListener('pageshow', resetTransition);

    const shouldAnimateLink = (link, event) => {
        if (!link) {
            return false;
        }

        const href = link.getAttribute('href');

        if (
            !href ||
            href === '#' ||
            href.startsWith('#') ||
            link.target === '_blank' ||
            link.hasAttribute('download') ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey ||
            event.defaultPrevented
        ) {
            return false;
        }

        return link.classList.contains('js-manga-transition')
            || link.id === 'detailReviewLink'
            || link.dataset.transition === 'manga-sheet';
    };

    const openTransition = (href) => {
        if (prefersReducedMotion) {
            window.location.href = href;
            return;
        }

        document.body.classList.add('is-transitioning');
        transition.classList.remove('is-rotating', 'is-opening');
        transition.classList.add('is-visible');
        transition.setAttribute('aria-hidden', 'false');

        window.setTimeout(() => {
            transition.classList.add('is-rotating');
        }, 180);

        window.setTimeout(() => {
            transition.classList.add('is-opening');
        }, 560);

        window.setTimeout(() => {
            sessionStorage.setItem('mangasanPageTransition', '1');
            window.location.href = href;
        }, 1120);
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');

        if (!shouldAnimateLink(link, event)) {
            return;
        }

        event.preventDefault();
        openTransition(link.href);
    });
})();
