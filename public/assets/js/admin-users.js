document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.getElementById('role_id');
    const classNameField = document.getElementById('classNameField');
    const classNameInput = document.getElementById('class_name');

    if (!roleSelect || !classNameField || !classNameInput) {
        return;
    }

    const updateRoleUI = () => {
        const selectedOption = roleSelect.options[roleSelect.selectedIndex];
        const roleName = selectedOption ? selectedOption.dataset.roleName || '' : '';

        if (roleName === 'member') {
            classNameField.style.display = '';
            classNameInput.required = true;
        } else {
            classNameField.style.display = '';
            classNameInput.required = false;
        }
    };

    roleSelect.addEventListener('change', updateRoleUI);
    updateRoleUI();
});