(() => {
    const configNode = document.getElementById('adminHelpConfig');
    const layer = document.getElementById('adminHelpLayer');

    if (!configNode || !layer) {
        return;
    }

    let config;

    try {
        config = JSON.parse(configNode.textContent || '{}');
    } catch (error) {
        console.error('Mangasan Help: configuration invalide.', error);
        return;
    }

    const rawSteps = Array.isArray(config.steps) ? config.steps : [];
    const openButtons = Array.from(document.querySelectorAll('[data-admin-help-open]'));
    const spotlight = document.getElementById('adminHelpSpotlight');
    const popover = document.getElementById('adminHelpPopover');
    const guideName = document.getElementById('adminHelpGuideName');
    const counter = document.getElementById('adminHelpCounter');
    const title = document.getElementById('adminHelpTitle');
    const text = document.getElementById('adminHelpText');
    const tip = document.getElementById('adminHelpTip');
    const progressBar = document.getElementById('adminHelpProgressBar');
    const previousButton = document.getElementById('adminHelpPrevious');
    const nextButton = document.getElementById('adminHelpNext');
    const closeButton = document.getElementById('adminHelpClose');

    if (
        !spotlight || !popover || !guideName || !counter || !title || !text ||
        !tip || !progressBar || !previousButton || !nextButton || !closeButton
    ) {
        return;
    }

    let steps = [];
    let currentIndex = 0;
    let isOpen = false;
    let previousActiveElement = null;
    let repositionFrame = 0;

    const resolveSteps = () => rawSteps
        .map((step) => {
            if (!step || typeof step.target !== 'string') {
                return null;
            }

            let element = null;

            try {
                element = document.querySelector(step.target);
            } catch (error) {
                console.warn(`Mangasan Help: sélecteur invalide ${step.target}`, error);
                return null;
            }

            return element ? { ...step, element } : null;
        })
        .filter(Boolean);

    const isRectComfortablyVisible = (rect) => {
        const margin = 72;
        return (
            rect.top >= margin &&
            rect.left >= 8 &&
            rect.bottom <= window.innerHeight - margin &&
            rect.right <= window.innerWidth - 8
        );
    };

    const positionSpotlight = (element) => {
        const rect = element.getBoundingClientRect();
        const padding = 9;

        spotlight.style.top = `${Math.max(6, rect.top - padding)}px`;
        spotlight.style.left = `${Math.max(6, rect.left - padding)}px`;
        spotlight.style.width = `${Math.max(24, Math.min(window.innerWidth - 12, rect.width + padding * 2))}px`;
        spotlight.style.height = `${Math.max(24, Math.min(window.innerHeight - 12, rect.height + padding * 2))}px`;

        return rect;
    };

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const activateStepTab = (step) => {
        const tabName = String(step?.tab || '').trim();

        if (tabName === '') {
            return;
        }

        const tabButton = Array.from(document.querySelectorAll('[data-admin-tab]'))
            .find((button) => button.dataset.adminTab === tabName);

        if (!tabButton) {
            console.warn(`Mangasan Help: onglet introuvable ${tabName}`);
            return;
        }

        if (tabButton.getAttribute('aria-selected') !== 'true') {
            tabButton.click();
        }
    };

    const positionPopover = (targetRect) => {
        if (window.matchMedia('(max-width: 760px)').matches) {
            popover.style.removeProperty('left');
            popover.style.removeProperty('right');
            popover.style.removeProperty('top');
            popover.style.removeProperty('bottom');
            return;
        }

        const gap = 18;
        const viewportPadding = 16;
        const popoverRect = popover.getBoundingClientRect();
        const popoverWidth = popoverRect.width || 410;
        const popoverHeight = popoverRect.height || 280;

        const roomRight = window.innerWidth - targetRect.right;
        const roomLeft = targetRect.left;
        const roomBelow = window.innerHeight - targetRect.bottom;
        const roomAbove = targetRect.top;

        let left;
        let top;

        if (roomRight >= popoverWidth + gap + viewportPadding) {
            left = targetRect.right + gap;
            top = targetRect.top + (targetRect.height - popoverHeight) / 2;
        } else if (roomLeft >= popoverWidth + gap + viewportPadding) {
            left = targetRect.left - popoverWidth - gap;
            top = targetRect.top + (targetRect.height - popoverHeight) / 2;
        } else if (roomBelow >= popoverHeight + gap + viewportPadding) {
            left = targetRect.left + (targetRect.width - popoverWidth) / 2;
            top = targetRect.bottom + gap;
        } else if (roomAbove >= popoverHeight + gap + viewportPadding) {
            left = targetRect.left + (targetRect.width - popoverWidth) / 2;
            top = targetRect.top - popoverHeight - gap;
        } else {
            left = window.innerWidth - popoverWidth - viewportPadding;
            top = viewportPadding;
        }

        left = clamp(left, viewportPadding, window.innerWidth - popoverWidth - viewportPadding);
        top = clamp(top, viewportPadding, window.innerHeight - popoverHeight - viewportPadding);

        popover.style.left = `${left}px`;
        popover.style.top = `${top}px`;
        popover.style.removeProperty('right');
        popover.style.removeProperty('bottom');
    };

    const refreshPositions = () => {
        if (!isOpen || steps.length === 0) {
            return;
        }

        cancelAnimationFrame(repositionFrame);
        repositionFrame = requestAnimationFrame(() => {
            const step = steps[currentIndex];
            if (!step?.element?.isConnected) {
                return;
            }

            const rect = positionSpotlight(step.element);
            positionPopover(rect);
        });
    };

    const renderStep = (index, shouldScroll = true) => {
        if (!isOpen || steps.length === 0) {
            return;
        }

        currentIndex = clamp(index, 0, steps.length - 1);
        const step = steps[currentIndex];
        const stepNumber = currentIndex + 1;

        // Certaines pages utilisent des onglets. L'étape peut demander au moteur
        // d'activer automatiquement l'onglet qui contient son élément cible.
        activateStepTab(step);

        guideName.textContent = String(config.title || 'Guide de la page');
        counter.textContent = `Étape ${stepNumber} / ${steps.length}`;
        title.textContent = String(step.title || 'Aide');
        text.textContent = String(step.text || '');

        const tipText = String(step.tip || '').trim();
        tip.textContent = tipText;
        tip.classList.toggle('is-hidden', tipText === '');

        progressBar.style.width = `${(stepNumber / steps.length) * 100}%`;
        previousButton.disabled = currentIndex === 0;
        nextButton.textContent = currentIndex === steps.length - 1 ? 'Terminer' : 'Suivant';

        const rect = step.element.getBoundingClientRect();

        if (shouldScroll && !isRectComfortablyVisible(rect)) {
            step.element.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'center',
                inline: 'nearest'
            });

            window.setTimeout(refreshPositions, 330);
        } else {
            refreshPositions();
        }
    };

    const openGuide = () => {
        steps = resolveSteps();

        if (steps.length === 0) {
            window.alert('Aucune étape d’aide n’est disponible sur cette page.');
            return;
        }

        previousActiveElement = document.activeElement;
        isOpen = true;
        currentIndex = 0;
        layer.hidden = false;
        layer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-help-is-open');

        renderStep(0, true);
        window.setTimeout(() => popover.focus({ preventScroll: true }), 30);
    };

    const closeGuide = () => {
        if (!isOpen) {
            return;
        }

        isOpen = false;
        layer.hidden = true;
        layer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-help-is-open');
        cancelAnimationFrame(repositionFrame);

        if (previousActiveElement instanceof HTMLElement && previousActiveElement.isConnected) {
            previousActiveElement.focus({ preventScroll: true });
        }
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', openGuide);
    });

    closeButton.addEventListener('click', closeGuide);

    previousButton.addEventListener('click', () => {
        renderStep(currentIndex - 1, true);
    });

    nextButton.addEventListener('click', () => {
        if (currentIndex >= steps.length - 1) {
            closeGuide();
            return;
        }

        renderStep(currentIndex + 1, true);
    });

    document.addEventListener('keydown', (event) => {
        if (!isOpen) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeGuide();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            nextButton.click();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            previousButton.click();
        }
    });

    window.addEventListener('resize', refreshPositions);
    window.addEventListener('scroll', refreshPositions, { passive: true });
})();
