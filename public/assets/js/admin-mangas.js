(() => {
    const form = document.getElementById('mangasBulkForm');
    if (!form) return;

    const selectAll = document.getElementById('selectAllMangas');
    const mangaCheckboxes = Array.from(form.querySelectorAll('.manga-selection-checkbox'));
    const actionSelect = document.getElementById('mangaBulkActionSelect');
    const applyButton = document.getElementById('mangaBulkApplyButton');
    const countLabel = document.getElementById('selectedMangasCount');
    const editionOptions = document.getElementById('mangaBulkEditionOptions');
    const editionSelect = document.getElementById('bulk_edition_id');
    const editionHint = document.getElementById('mangaBulkEditionHint');
    const deleteWarning = document.getElementById('mangaBulkDeleteWarning');
    const deleteConfirmation = document.getElementById('bulk_confirm_delete');

    const getSelected = () => mangaCheckboxes.filter((checkbox) => checkbox.checked);

    const updateSelectionState = () => {
        const selectedCount = getSelected().length;
        const action = actionSelect?.value ?? '';
        const needsEdition = action === 'attach_edition' || action === 'detach_edition';
        const editionReady = !needsEdition || (editionSelect && editionSelect.value !== '');
        const deleteReady = action !== 'delete' || (deleteConfirmation && deleteConfirmation.checked);

        if (countLabel) {
            countLabel.textContent = `${selectedCount} manga${selectedCount > 1 ? 's' : ''} sélectionné${selectedCount > 1 ? 's' : ''}`;
        }

        if (applyButton) {
            applyButton.disabled = selectedCount === 0 || action === '' || !editionReady || !deleteReady;
        }

        mangaCheckboxes.forEach((checkbox) => {
            checkbox.closest('tr')?.classList.toggle('is-selected', checkbox.checked);
        });

        if (selectAll) {
            selectAll.checked = mangaCheckboxes.length > 0 && selectedCount === mangaCheckboxes.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < mangaCheckboxes.length;
        }
    };

    const updateActionOptions = () => {
        const action = actionSelect?.value ?? '';
        const needsEdition = action === 'attach_edition' || action === 'detach_edition';

        editionOptions?.classList.toggle('is-hidden', !needsEdition);
        deleteWarning?.classList.toggle('is-hidden', action !== 'delete');

        if (editionSelect) {
            editionSelect.required = needsEdition;
        }

        if (deleteConfirmation) {
            deleteConfirmation.required = action === 'delete';
            if (action !== 'delete') {
                deleteConfirmation.checked = false;
            }
        }

        if (editionHint && needsEdition) {
            editionHint.textContent = action === 'attach_edition'
                ? 'Les mangas déjà présents dans cette édition seront simplement ignorés.'
                : 'Le rattachement sera retiré, mais les fiches de lecture déjà enregistrées seront conservées.';
        }

        updateSelectionState();
    };

    selectAll?.addEventListener('change', () => {
        mangaCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
        updateSelectionState();
    });

    mangaCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectionState);
    });

    actionSelect?.addEventListener('change', updateActionOptions);
    editionSelect?.addEventListener('change', updateSelectionState);
    deleteConfirmation?.addEventListener('change', updateSelectionState);

    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;

        // Les boutons Supprimer individuels utilisent le même formulaire avec formaction.
        // La validation des actions groupées ne doit pas s'appliquer à ces boutons.
        if (!submitter || submitter.dataset.bulkSubmit !== '1') {
            return;
        }

        const selectedCount = getSelected().length;
        const action = actionSelect?.value ?? '';

        if (selectedCount === 0) {
            event.preventDefault();
            window.alert('Sélectionne au moins un manga.');
            return;
        }

        if (!action) {
            event.preventDefault();
            window.alert('Choisis une action groupée.');
            return;
        }

        if ((action === 'attach_edition' || action === 'detach_edition') && !editionSelect?.value) {
            event.preventDefault();
            window.alert('Choisis une édition.');
            return;
        }

        if (action === 'delete') {
            if (!deleteConfirmation?.checked) {
                event.preventDefault();
                window.alert('Confirme que tu comprends les conséquences de la suppression.');
                return;
            }

            const confirmed = window.confirm(
                `Supprimer définitivement ${selectedCount} manga(s) ?\n\n` +
                'Tous leurs rattachements aux éditions et toutes leurs fiches de lecture seront également supprimés.\n' +
                'Cette opération est irréversible.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    updateActionOptions();
})();
