document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('bulkSelectAll');
    const bulkForm = document.getElementById('bulkReadingSheetForm');
    const bulkAction = document.getElementById('bulk_action');
    const applyButton = document.getElementById('reviewsBulkApply');
    const selectionCount = document.getElementById('reviewsBulkSelectionCount');
    const deleteWarning = document.getElementById('reviewsBulkDeleteWarning');
    const confirmDelete = document.getElementById('reviewsConfirmDelete');
    const checkboxes = Array.from(document.querySelectorAll('.bulk-reading-sheet-checkbox'));

    if (!bulkForm || !bulkAction) {
        return;
    }

    const checkedBoxes = () => checkboxes.filter((checkbox) => checkbox.checked);

    const refreshRowState = () => {
        checkboxes.forEach((checkbox) => {
            const row = checkbox.closest('.admin-review-row');
            if (row) {
                row.classList.toggle('is-selected', checkbox.checked);
            }
        });
    };

    const refreshMasterState = () => {
        if (!selectAll) {
            return;
        }

        const checkedCount = checkedBoxes().length;

        if (checkboxes.length === 0 || checkedCount === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }

        if (checkedCount === checkboxes.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
            return;
        }

        selectAll.checked = false;
        selectAll.indeterminate = true;
    };

    const refreshSelectionCount = () => {
        const count = checkedBoxes().length;

        if (selectionCount) {
            selectionCount.textContent = `${count} fiche${count > 1 ? 's' : ''} sélectionnée${count > 1 ? 's' : ''}`;
        }

        if (applyButton) {
            applyButton.disabled = count === 0 || bulkAction.value === '';
        }
    };

    const refreshDeleteWarning = () => {
        const isDelete = bulkAction.value === 'delete';

        if (deleteWarning) {
            deleteWarning.classList.toggle('is-hidden', !isDelete);
        }

        if (!isDelete && confirmDelete) {
            confirmDelete.checked = false;
        }
    };

    const refreshAll = () => {
        refreshMasterState();
        refreshSelectionCount();
        refreshRowState();
        refreshDeleteWarning();
    };

    if (selectAll) {
        selectAll.disabled = checkboxes.length === 0;
        selectAll.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            refreshAll();
        });
    }

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', refreshAll);
    });

    bulkAction.addEventListener('change', refreshAll);

    bulkForm.addEventListener('submit', (event) => {
        const checkedCount = checkedBoxes().length;

        if (checkedCount === 0) {
            event.preventDefault();
            window.alert('Sélectionne au moins une fiche de lecture.');
            return;
        }

        if (bulkAction.value === '') {
            event.preventDefault();
            window.alert('Choisis une action groupée.');
            return;
        }

        if (bulkAction.value === 'delete') {
            if (!confirmDelete || !confirmDelete.checked) {
                event.preventDefault();
                window.alert('Confirme la suppression définitive avant de continuer.');
                return;
            }

            const confirmed = window.confirm(`Supprimer définitivement ${checkedCount} fiche${checkedCount > 1 ? 's' : ''} de lecture ?`);

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    refreshAll();
});
