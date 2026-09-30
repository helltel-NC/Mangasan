(() => {
    'use strict';

    const config = window.MangasanLayoutEditor;
    const grid = document.querySelector('[data-home-layout-grid]');

    if (!config || !grid) {
        return;
    }

    const clone = (value) => JSON.parse(JSON.stringify(value));
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

    let state = clone(config.layout || {});
    const defaultState = clone(config.defaultLayout || state);
    let dirty = false;
    let interaction = null;
    let toastTimer = null;

    state.blocks = state.blocks || {};
    state.components = state.components || {};

    const statusEl = document.querySelector('[data-layout-status]');
    const saveButton = document.querySelector('[data-layout-save]');
    const publishButton = document.querySelector('[data-layout-publish]');
    const restoreButton = document.querySelector('[data-layout-restore]');
    const resetButton = document.querySelector('[data-layout-reset]');
    const cancelButton = document.querySelector('[data-layout-cancel]');
    const toastEl = document.querySelector('[data-layout-toast]');

    function showToast(message, isError = false) {
        if (!toastEl) {
            return;
        }

        toastEl.textContent = message;
        toastEl.classList.toggle('is-error', isError);
        toastEl.classList.add('is-visible');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toastEl.classList.remove('is-visible'), 3200);
    }

    function setDirty(value) {
        dirty = Boolean(value);

        if (statusEl) {
            statusEl.classList.toggle('is-dirty', dirty);
            statusEl.textContent = dirty ? 'Modifications non enregistrées' : 'Brouillon à jour';
        }
    }

    function blockElements() {
        return Array.from(grid.querySelectorAll(':scope > [data-layout-block]'));
    }

    function getBlockState(key) {
        if (!state.blocks[key]) {
            state.blocks[key] = { x: 1, w: 12, order: 999, minHeight: 0 };
        }

        return state.blocks[key];
    }

    function normalizeOrdersFromDom() {
        blockElements().forEach((block, index) => {
            const key = block.dataset.layoutBlock;
            getBlockState(key).order = (index + 1) * 10;
        });
    }

    function updateBlockBadge(block) {
        const key = block.dataset.layoutBlock;
        const blockState = getBlockState(key);
        const badge = block.querySelector('[data-layout-size-label]');

        if (badge) {
            const heightLabel = blockState.minHeight > 0 ? ` · min ${blockState.minHeight}px` : '';
            badge.textContent = `${blockState.w}/12${heightLabel}`;
        }
    }

    function applyBlock(block) {
        const key = block.dataset.layoutBlock;
        const blockState = getBlockState(key);
        const x = clamp(Number(blockState.x) || 1, 1, 12);
        let w = clamp(Number(blockState.w) || 12, 1, 12);

        if (x + w - 1 > 12) {
            w = 13 - x;
        }

        blockState.x = x;
        blockState.w = Math.max(1, w);
        blockState.order = Number(blockState.order) || 10;
        blockState.minHeight = clamp(Number(blockState.minHeight) || 0, 0, 1600);

        block.style.setProperty('--layout-x', String(blockState.x));
        block.style.setProperty('--layout-w', String(blockState.w));
        block.style.setProperty('--layout-order', String(blockState.order));
        block.style.setProperty('--layout-min-height', `${blockState.minHeight}px`);
        updateBlockBadge(block);
    }

    function sortBlocks() {
        const sorted = blockElements().sort((a, b) => {
            const aState = getBlockState(a.dataset.layoutBlock);
            const bState = getBlockState(b.dataset.layoutBlock);
            return (Number(aState.order) || 0) - (Number(bState.order) || 0);
        });

        sorted.forEach((block) => grid.appendChild(block));
    }

    function getLoginState() {
        if (!state.components.hero_login) {
            state.components.hero_login = { x: 9, y: 1, w: 4, h: 4 };
        }

        return state.components.hero_login;
    }

    function applyHeroLogin() {
        const heroContent = document.querySelector('[data-hero-layout-content]');
        const login = document.querySelector('[data-layout-component="hero_login"]');

        if (!heroContent || !login) {
            return;
        }

        const component = getLoginState();
        component.x = clamp(Number(component.x) || 9, 1, 12);
        component.w = clamp(Number(component.w) || 4, 2, 12);
        component.h = clamp(Number(component.h) || 4, 2, 8);
        component.y = clamp(Number(component.y) || 1, 1, 8);

        if (component.y + component.h - 1 > 8) {
            component.h = Math.max(2, 9 - component.y);
            if (component.y + component.h - 1 > 8) {
                component.y = Math.max(1, 9 - component.h);
            }
        }

        if (component.x + component.w - 1 > 12) {
            component.w = Math.max(2, 13 - component.x);
            if (component.x + component.w - 1 > 12) {
                component.x = Math.max(1, 13 - component.w);
            }
        }

        heroContent.style.setProperty('--hero-login-x', String(component.x));
        heroContent.style.setProperty('--hero-login-y', String(component.y));
        heroContent.style.setProperty('--hero-login-w', String(component.w));
        heroContent.style.setProperty('--hero-login-h', String(component.h));
        heroContent.classList.toggle('is-login-left', component.x <= 5);

        const badge = login.querySelector('[data-layout-component-size]');
        if (badge) {
            badge.textContent = `colonne ${component.x} · ${component.w}/12 · ligne ${component.y} · hauteur ${component.h}`;
        }
    }

    function applyState() {
        sortBlocks();
        blockElements().forEach(applyBlock);
        grid.style.setProperty('--home-layout-gap', `${clamp(Number(state.gap) || 18, 0, 48)}px`);
        applyHeroLogin();
    }

    function createBlockControls(block) {
        if (block.querySelector(':scope > .layout-editor-block-controls')) {
            return;
        }

        const controls = document.createElement('div');
        controls.className = 'layout-editor-block-controls';

        const drag = document.createElement('button');
        drag.type = 'button';
        drag.className = 'layout-editor-drag-handle';
        drag.setAttribute('aria-label', 'Déplacer ce bloc');
        drag.dataset.layoutDragHandle = 'block';
        drag.textContent = '⠿';

        const label = document.createElement('span');
        label.className = 'layout-editor-block-label';
        label.textContent = block.dataset.layoutLabel || block.dataset.layoutBlock || 'Bloc';

        const size = document.createElement('span');
        size.className = 'layout-editor-block-size';
        size.dataset.layoutSizeLabel = '';

        controls.append(drag, label, size);
        block.prepend(controls);

        ['w', 'e', 's'].forEach((direction) => {
            const handle = document.createElement('span');
            handle.className = 'layout-editor-resize-handle';
            handle.dataset.layoutResize = direction;
            handle.setAttribute('aria-hidden', 'true');
            block.appendChild(handle);
        });
    }

    function createLoginControls() {
        const login = document.querySelector('[data-layout-component="hero_login"]');
        if (!login || login.querySelector(':scope > .layout-editor-component-controls')) {
            return;
        }

        const controls = document.createElement('div');
        controls.className = 'layout-editor-component-controls';

        const drag = document.createElement('button');
        drag.type = 'button';
        drag.className = 'layout-editor-drag-handle';
        drag.dataset.layoutDragHandle = 'hero_login';
        drag.setAttribute('aria-label', 'Déplacer la zone de connexion');
        drag.textContent = '⠿';

        const label = document.createElement('span');
        label.className = 'layout-editor-block-label';
        label.textContent = 'Connexion / compte';

        const size = document.createElement('span');
        size.className = 'layout-editor-block-size';
        size.dataset.layoutComponentSize = '';

        controls.append(drag, label, size);
        login.prepend(controls);

        ['w', 'e', 's', 'se'].forEach((direction) => {
            const handle = document.createElement('span');
            handle.className = 'layout-editor-resize-handle';
            handle.dataset.layoutComponentResize = direction;
            handle.setAttribute('aria-hidden', 'true');
            login.appendChild(handle);
        });
    }

    function clearSelection() {
        document.querySelectorAll('.is-layout-selected').forEach((element) => element.classList.remove('is-layout-selected'));
    }

    function selectElement(element) {
        clearSelection();
        element?.classList.add('is-layout-selected');
    }

    function startBlockDrag(event, block) {
        const rect = block.getBoundingClientRect();
        const gridRect = grid.getBoundingClientRect();
        const colWidth = gridRect.width / 12;
        const blockState = getBlockState(block.dataset.layoutBlock);
        const pointerCol = Math.floor((event.clientX - gridRect.left) / colWidth) + 1;

        interaction = {
            type: 'block-drag',
            pointerId: event.pointerId,
            block,
            key: block.dataset.layoutBlock,
            colOffset: clamp(pointerCol - blockState.x, 0, Math.max(0, blockState.w - 1)),
        };

        event.target.setPointerCapture?.(event.pointerId);
        document.body.classList.add('is-layout-interacting');
        selectElement(block);
    }

    function startBlockResize(event, block, direction) {
        const blockState = getBlockState(block.dataset.layoutBlock);
        interaction = {
            type: 'block-resize',
            pointerId: event.pointerId,
            block,
            key: block.dataset.layoutBlock,
            direction,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startX: Number(blockState.x) || 1,
            startW: Number(blockState.w) || 12,
            startHeight: Number(blockState.minHeight) || Math.round(block.getBoundingClientRect().height),
        };

        event.target.setPointerCapture?.(event.pointerId);
        document.body.classList.add('is-layout-interacting');
        selectElement(block);
    }

    function startLoginDrag(event, login) {
        const heroContent = document.querySelector('[data-hero-layout-content]');
        if (!heroContent) return;

        const component = getLoginState();
        const rect = heroContent.getBoundingClientRect();
        const colWidth = rect.width / 12;
        const pointerCol = Math.floor((event.clientX - rect.left) / colWidth) + 1;

        interaction = {
            type: 'login-drag',
            pointerId: event.pointerId,
            element: login,
            colOffset: clamp(pointerCol - component.x, 0, Math.max(0, component.w - 1)),
        };

        event.target.setPointerCapture?.(event.pointerId);
        document.body.classList.add('is-layout-interacting');
        selectElement(login);
    }

    function startLoginResize(event, login, direction) {
        const component = getLoginState();
        interaction = {
            type: 'login-resize',
            pointerId: event.pointerId,
            element: login,
            direction,
            startClientX: event.clientX,
            startX: Number(component.x) || 9,
            startW: Number(component.w) || 4,
            startH: Number(component.h) || 4,
            startClientY: event.clientY,
        };

        event.target.setPointerCapture?.(event.pointerId);
        document.body.classList.add('is-layout-interacting');
        selectElement(login);
    }

    function reorderBlockByPointer(block, clientY) {
        const others = blockElements().filter((candidate) => candidate !== block);
        const target = others.find((candidate) => {
            const rect = candidate.getBoundingClientRect();
            return clientY < rect.top + (rect.height / 2);
        });

        if (target) {
            grid.insertBefore(block, target);
        } else {
            grid.appendChild(block);
        }

        normalizeOrdersFromDom();
    }

    function handlePointerMove(event) {
        if (!interaction || event.pointerId !== interaction.pointerId) {
            return;
        }

        event.preventDefault();

        if (interaction.type === 'block-drag') {
            const gridRect = grid.getBoundingClientRect();
            const colWidth = gridRect.width / 12;
            const blockState = getBlockState(interaction.key);
            const pointerCol = Math.floor((event.clientX - gridRect.left) / colWidth) + 1;
            const maxX = 13 - blockState.w;
            blockState.x = clamp(pointerCol - interaction.colOffset, 1, Math.max(1, maxX));
            reorderBlockByPointer(interaction.block, event.clientY);
            applyState();
            setDirty(true);
            return;
        }

        if (interaction.type === 'block-resize') {
            const gridRect = grid.getBoundingClientRect();
            const colWidth = gridRect.width / 12;
            const deltaCols = Math.round((event.clientX - interaction.startClientX) / colWidth);
            const blockState = getBlockState(interaction.key);

            if (interaction.direction === 'e') {
                blockState.w = clamp(interaction.startW + deltaCols, 1, 13 - interaction.startX);
            } else if (interaction.direction === 'w') {
                const maxMove = interaction.startW - 1;
                const move = clamp(deltaCols, 1 - interaction.startX, maxMove);
                blockState.x = interaction.startX + move;
                blockState.w = interaction.startW - move;
            } else if (interaction.direction === 's') {
                const deltaHeight = Math.round((event.clientY - interaction.startClientY) / 24) * 24;
                blockState.minHeight = clamp(interaction.startHeight + deltaHeight, 160, 1600);
            }

            applyBlock(interaction.block);
            setDirty(true);
            return;
        }

        const heroContent = document.querySelector('[data-hero-layout-content]');
        if (!heroContent) return;
        const heroRect = heroContent.getBoundingClientRect();
        const colWidth = heroRect.width / 12;
        const rowHeight = 58;
        const component = getLoginState();

        if (interaction.type === 'login-drag') {
            const pointerCol = Math.floor((event.clientX - heroRect.left) / colWidth) + 1;
            component.x = clamp(pointerCol - interaction.colOffset, 1, Math.max(1, 13 - component.w));
            component.y = clamp(Math.floor((event.clientY - heroRect.top) / rowHeight) + 1, 1, Math.max(1, 9 - component.h));
            applyHeroLogin();
            setDirty(true);
            return;
        }

        if (interaction.type === 'login-resize') {
            const deltaCols = Math.round((event.clientX - interaction.startClientX) / colWidth);

            const deltaRows = Math.round((event.clientY - interaction.startClientY) / rowHeight);

            if (interaction.direction === 'e') {
                component.w = clamp(interaction.startW + deltaCols, 2, 13 - interaction.startX);
            } else if (interaction.direction === 'w') {
                const maxMove = interaction.startW - 2;
                const move = clamp(deltaCols, 1 - interaction.startX, maxMove);
                component.x = interaction.startX + move;
                component.w = interaction.startW - move;
            } else if (interaction.direction === 's') {
                component.h = clamp(interaction.startH + deltaRows, 2, 9 - component.y);
            } else if (interaction.direction === 'se') {
                component.w = clamp(interaction.startW + deltaCols, 2, 13 - interaction.startX);
                component.h = clamp(interaction.startH + deltaRows, 2, 9 - component.y);
            }

            applyHeroLogin();
            setDirty(true);
        }
    }

    function endInteraction(event) {
        if (!interaction || event.pointerId !== interaction.pointerId) {
            return;
        }

        interaction = null;
        document.body.classList.remove('is-layout-interacting');
    }

    async function postLayout(url, layout = state) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ layout }),
        });

        const data = await response.json().catch(() => ({ ok: false, message: 'Réponse serveur invalide.' }));

        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'L’opération a échoué.');
        }

        return data;
    }

    document.addEventListener('pointerdown', (event) => {
        const dragHandle = event.target.closest('[data-layout-drag-handle]');
        if (dragHandle) {
            event.preventDefault();
            event.stopPropagation();

            if (dragHandle.dataset.layoutDragHandle === 'hero_login') {
                const login = dragHandle.closest('[data-layout-component="hero_login"]');
                if (login) startLoginDrag(event, login);
            } else {
                const block = dragHandle.closest('[data-layout-block]');
                if (block) startBlockDrag(event, block);
            }
            return;
        }

        const componentResize = event.target.closest('[data-layout-component-resize]');
        if (componentResize) {
            event.preventDefault();
            event.stopPropagation();
            const login = componentResize.closest('[data-layout-component="hero_login"]');
            if (login) startLoginResize(event, login, componentResize.dataset.layoutComponentResize);
            return;
        }

        const resize = event.target.closest('[data-layout-resize]');
        if (resize) {
            event.preventDefault();
            event.stopPropagation();
            const block = resize.closest('[data-layout-block]');
            if (block) startBlockResize(event, block, resize.dataset.layoutResize);
            return;
        }

        const selectable = event.target.closest('[data-layout-block], [data-layout-component="hero_login"]');
        if (selectable) {
            selectElement(selectable);
        }
    });

    document.addEventListener('pointermove', handlePointerMove, { passive: false });
    document.addEventListener('pointerup', endInteraction);
    document.addEventListener('pointercancel', endInteraction);

    // En mode éditeur, les contrôles du vrai site restent visibles mais ne déclenchent pas de navigation/action.
    document.addEventListener('click', (event) => {
        if (event.target.closest('.layout-editor-toolbar, .layout-editor-block-controls, .layout-editor-component-controls')) {
            return;
        }

        if (event.target.closest('a, button, form, input, select, textarea')) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    blockElements().forEach(createBlockControls);
    createLoginControls();
    applyState();

    saveButton?.addEventListener('click', async () => {
        if (!config.storageReady) return;
        saveButton.disabled = true;
        try {
            const data = await postLayout('/mangasan/actions/layout_save.php');
            state = clone(data.layout || state);
            applyState();
            setDirty(false);
            showToast(data.message || 'Brouillon enregistré.');
        } catch (error) {
            showToast(error.message, true);
        } finally {
            saveButton.disabled = false;
        }
    });

    publishButton?.addEventListener('click', async () => {
        if (!config.storageReady) return;
        if (!window.confirm('Publier cette disposition sur le site public ?')) return;

        publishButton.disabled = true;
        try {
            const data = await postLayout('/mangasan/actions/layout_publish.php');
            state = clone(data.layout || state);
            config.hasPrevious = Boolean(data.has_previous);
            if (restoreButton) restoreButton.disabled = !config.hasPrevious;
            applyState();
            setDirty(false);
            showToast(data.message || 'Disposition publiée.');
        } catch (error) {
            showToast(error.message, true);
        } finally {
            publishButton.disabled = false;
        }
    });

    restoreButton?.addEventListener('click', async () => {
        if (!config.storageReady || !config.hasPrevious) return;
        if (!window.confirm('Restaurer la disposition publiée précédente ? La disposition actuelle deviendra à son tour restaurable.')) return;

        restoreButton.disabled = true;
        try {
            const response = await fetch('/mangasan/actions/layout_restore.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Restauration impossible.');

            state = clone(data.layout || state);
            config.hasPrevious = Boolean(data.has_previous);
            applyState();
            setDirty(false);
            showToast(data.message || 'Disposition restaurée.');
        } catch (error) {
            showToast(error.message, true);
        } finally {
            restoreButton.disabled = !config.hasPrevious;
        }
    });

    resetButton?.addEventListener('click', () => {
        if (!window.confirm('Revenir à la disposition par défaut dans le brouillon ? Rien ne sera publié tant que vous ne cliquez pas sur Publier.')) return;
        state = clone(defaultState);
        applyState();
        setDirty(true);
        showToast('Disposition par défaut chargée dans le brouillon.');
    });

    cancelButton?.addEventListener('click', () => {
        if (dirty && !window.confirm('Abandonner les modifications non enregistrées ?')) return;
        window.location.reload();
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
