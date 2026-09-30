(() => {
    const form = document.getElementById('usersBulkForm');
    if (!form) return;

    const selectAll = document.getElementById('selectAllUsers');
    const userCheckboxes = Array.from(form.querySelectorAll('.user-selection-checkbox:not(:disabled)'));
    const actionSelect = document.getElementById('bulkActionSelect');
    const applyButton = document.getElementById('bulkApplyButton');
    const countLabel = document.getElementById('selectedUsersCount');
    const passwordOptions = document.getElementById('bulkPasswordOptions');
    const updateOptions = document.getElementById('bulkUpdateOptions');
    const deleteWarning = document.getElementById('bulkDeleteWarning');
    const passwordInput = document.getElementById('bulk_password');
    const updateClassCheckbox = document.getElementById('bulk_update_class');
    const classNameInput = document.getElementById('bulk_class_name');

    const getSelected = () => userCheckboxes.filter((checkbox) => checkbox.checked);

    const updateSelectionState = () => {
        const selected = getSelected();
        const count = selected.length;

        countLabel.textContent = `${count} utilisateur${count > 1 ? 's' : ''} sélectionné${count > 1 ? 's' : ''}`;
        applyButton.disabled = count === 0 || actionSelect.value === '';

        if (selectAll) {
            selectAll.checked = userCheckboxes.length > 0 && count === userCheckboxes.length;
            selectAll.indeterminate = count > 0 && count < userCheckboxes.length;
        }
    };

    const updateActionOptions = () => {
        const action = actionSelect.value;

        passwordOptions?.classList.toggle('is-hidden', action !== 'reset_password');
        updateOptions?.classList.toggle('is-hidden', action !== 'update');
        deleteWarning?.classList.toggle('is-hidden', action !== 'delete');

        if (passwordInput) {
            passwordInput.required = action === 'reset_password';
        }

        updateSelectionState();
    };

    selectAll?.addEventListener('change', () => {
        userCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
        updateSelectionState();
    });

    userCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectionState);
    });

    actionSelect?.addEventListener('change', updateActionOptions);

    updateClassCheckbox?.addEventListener('change', () => {
        if (!classNameInput) return;
        classNameInput.disabled = !updateClassCheckbox.checked;
        if (updateClassCheckbox.checked) {
            classNameInput.focus();
        }
    });

    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;

        // Les boutons Désactiver/Réactiver individuels utilisent le même formulaire
        // avec formaction : on ne leur applique pas la validation groupée.
        if (!submitter || submitter.dataset.bulkSubmit !== '1') {
            return;
        }

        const selectedCount = getSelected().length;
        const action = actionSelect.value;

        if (selectedCount === 0) {
            event.preventDefault();
            window.alert('Sélectionne au moins un utilisateur.');
            return;
        }

        if (!action) {
            event.preventDefault();
            window.alert('Choisis une action groupée.');
            return;
        }

        if (action === 'delete') {
            const confirmed = window.confirm(
                `Supprimer les ${selectedCount} compte(s) sélectionné(s) ?\n\n` +
                'Les comptes possédant déjà des fiches de lecture seront conservés automatiquement.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        }

        if (action === 'reset_password') {
            const confirmed = window.confirm(
                `Réinitialiser le mot de passe de ${selectedCount} compte(s) avec le mot de passe temporaire saisi ?`
            );

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    updateActionOptions();
})();
