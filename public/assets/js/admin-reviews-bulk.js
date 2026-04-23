document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('bulkSelectAll');
    const bulkForm = document.getElementById('bulkReadingSheetForm');
    const bulkAction = document.getElementById('bulk_action');
    const checkboxes = Array.from(document.querySelectorAll('.bulk-reading-sheet-checkbox'));

    if (!selectAll || !bulkForm || !bulkAction || checkboxes.length === 0) {
        return;
    }

    const refreshMasterState = () => {
        const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;

        if (checkedCount === 0) {
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

    selectAll.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });

        refreshMasterState();
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', refreshMasterState);
    });

    bulkForm.addEventListener('submit', (event) => {
        const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;

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
            const confirmed = window.confirm('Supprimer les fiches de lecture sélectionnées ? Cette action est irréversible.');

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    refreshMasterState();
});