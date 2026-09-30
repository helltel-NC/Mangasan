(() => {
    const form = document.getElementById('userEditForm');
    if (!form) return;

    const usernameInput = document.getElementById('username');
    const roleSelect = document.getElementById('role_id');
    const statusSelect = document.getElementById('status');
    const passwordFlagSelect = document.getElementById('must_change_password');
    const classField = document.getElementById('classNameField');
    const classInput = document.getElementById('class_name');

    const summaryUsername = document.getElementById('userSummaryUsername');
    const summaryRole = document.getElementById('userSummaryRole');
    const summaryStatus = document.getElementById('userSummaryStatus');
    const summaryClass = document.getElementById('userSummaryClass');
    const summaryPasswordState = document.getElementById('userSummaryPasswordState');

    const selectedRoleOption = () => roleSelect?.selectedOptions?.[0] || null;

    const updateRoleState = () => {
        const option = selectedRoleOption();
        const roleName = option?.dataset.roleName || '';
        const isAdmin = roleName === 'admin';

        if (classField) {
            classField.classList.toggle('admin-dynamic-hidden', isAdmin);
        }

        if (classInput) {
            classInput.required = !isAdmin;
        }

        if (summaryRole) {
            summaryRole.textContent = option?.textContent?.trim() || '—';
        }

        if (summaryClass) {
            summaryClass.textContent = isAdmin
                ? '—'
                : (classInput?.value.trim() || '—');
        }
    };

    const updateSummary = () => {
        if (summaryUsername) {
            summaryUsername.textContent = usernameInput?.value.trim() || 'Nouveau compte';
        }

        if (summaryStatus) {
            summaryStatus.textContent = statusSelect?.value === 'inactive' ? 'Inactif' : 'Actif';
        }

        if (summaryPasswordState) {
            summaryPasswordState.textContent = passwordFlagSelect?.value === '1'
                ? 'Changement demandé'
                : 'Personnel';
        }

        updateRoleState();
    };

    roleSelect?.addEventListener('change', updateRoleState);
    usernameInput?.addEventListener('input', updateSummary);
    statusSelect?.addEventListener('change', updateSummary);
    passwordFlagSelect?.addEventListener('change', updateSummary);
    classInput?.addEventListener('input', updateSummary);

    const resetPasswordForm = document.getElementById('userResetPasswordForm');
    resetPasswordForm?.addEventListener('submit', (event) => {
        const confirmed = window.confirm(
            'Réinitialiser le mot de passe de cet utilisateur ?\n\n' +
            'L’ancien mot de passe cessera immédiatement de fonctionner.'
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });

    updateSummary();
})();
