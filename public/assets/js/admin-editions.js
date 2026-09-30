(() => {
    const form = document.getElementById('editionForm');

    if (!form) {
        return;
    }

    const reviewFormType = document.getElementById('review_form_type');
    const rankingMethod = document.getElementById('ranking_calculation_method');
    const scoreMaxField = document.getElementById('score_max_field');
    const rankingHelp = document.getElementById('ranking_method_help');
    const modeAdvice = document.getElementById('edition_mode_advice');
    const statusAdvice = document.getElementById('edition_status_advice');
    const statusField = document.getElementById('status');
    const titleField = document.getElementById('title');
    const startDateField = document.getElementById('start_date');
    const endDateField = document.getElementById('end_date');
    const rankingVisibility = document.getElementById('general_ranking_visibility');
    const rankingAccess = document.getElementById('general_ranking_access');

    if (!reviewFormType || !rankingMethod || !scoreMaxField || !modeAdvice || !statusField) {
        return;
    }

    const summaryTitle = document.getElementById('editionSummaryTitle');
    const summaryStatus = document.getElementById('editionSummaryStatus');
    const summaryPeriod = document.getElementById('editionSummaryPeriod');
    const summaryFormType = document.getElementById('editionSummaryFormType');
    const summaryRankingMethod = document.getElementById('editionSummaryRankingMethod');
    const summaryRankingPublication = document.getElementById('editionSummaryRankingPublication');

    const statusLabels = {
        draft: 'Brouillon',
        active: 'Active',
        closed: 'Clôturée',
        archived: 'Archivée',
    };

    const formTypeLabels = {
        classic_score: 'Fiche avec notes',
        mangasan_reading_sheet_v1: 'Fiche Mangasan / Myriam',
    };

    const rankingLabels = {
        average: 'Moyenne des notes',
        average_score: 'Moyenne des notes',
        rank_points: 'Points selon le rang',
    };

    const modeMessages = {
        classicAverage: '<strong>Mode classique :</strong> les élèves remplissent une fiche avec notes. Le barème est utilisé et le classement général repose sur la moyenne.',
        classicRank: '<strong>Mode mixte :</strong> la fiche reste l’ancienne fiche avec notes, mais le classement général utilise le rang personnel.',
        mangasanRank: '<strong>Mode Mangasan conseillé :</strong> les élèves utilisent la fiche Mangasan / Myriam et classent les mangas selon leur rang personnel.',
        mangasanAverage: '<strong>Attention :</strong> la fiche Mangasan / Myriam est associée à une moyenne de notes. Ce réglage est techniquement possible mais n’est normalement pas adapté à cette fiche.',
    };

    const statusMessages = {
        draft: '<strong>Brouillon :</strong> l’édition est préparée mais n’est pas l’édition active du site.',
        active: '<strong>Active :</strong> lors de l’enregistrement, cette édition devient l’édition principale. Toute autre édition active sera automatiquement clôturée.',
        closed: '<strong>Clôturée :</strong> l’édition est terminée mais reste disponible dans l’administration et pour consulter ses données.',
        archived: '<strong>Archivée :</strong> utilisez ce statut pour une édition ancienne que vous souhaitez conserver sans la présenter comme édition courante.',
    };

    const isAverageMethod = (value) => value === 'average' || value === 'average_score';

    const formatDate = (value) => {
        if (!value) {
            return 'Non définie';
        }

        const parts = value.split('-');
        if (parts.length !== 3) {
            return value;
        }

        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    };

    const refreshMode = () => {
        const formType = reviewFormType.value;
        const method = rankingMethod.value;
        const isMangasan = formType === 'mangasan_reading_sheet_v1';
        const isAverage = isAverageMethod(method);

        scoreMaxField.classList.toggle('admin-dynamic-hidden', !isAverage);

        if (rankingHelp) {
            rankingHelp.textContent = isMangasan
                ? 'Pour la fiche Mangasan / Myriam, le mode conseillé est : points selon le rang personnel.'
                : 'Pour l’ancienne fiche avec notes, le mode conseillé est : moyenne des notes.';
        }

        if (!isMangasan && isAverage) {
            modeAdvice.innerHTML = modeMessages.classicAverage;
        } else if (!isMangasan && !isAverage) {
            modeAdvice.innerHTML = modeMessages.classicRank;
        } else if (isMangasan && !isAverage) {
            modeAdvice.innerHTML = modeMessages.mangasanRank;
        } else {
            modeAdvice.innerHTML = modeMessages.mangasanAverage;
        }
    };

    const refreshStatus = () => {
        if (statusAdvice) {
            statusAdvice.innerHTML = statusMessages[statusField.value] || '';
        }
    };

    const refreshSummary = () => {
        if (summaryTitle && titleField) {
            summaryTitle.textContent = titleField.value.trim() || 'Nouvelle édition';
        }

        if (summaryStatus) {
            summaryStatus.textContent = statusLabels[statusField.value] || statusField.value;
        }

        if (summaryPeriod && startDateField && endDateField) {
            summaryPeriod.textContent = `${formatDate(startDateField.value)} → ${formatDate(endDateField.value)}`;
        }

        if (summaryFormType) {
            summaryFormType.textContent = formTypeLabels[reviewFormType.value] || reviewFormType.value;
        }

        if (summaryRankingMethod) {
            summaryRankingMethod.textContent = rankingLabels[rankingMethod.value] || rankingMethod.value;
        }

        if (summaryRankingPublication && rankingVisibility && rankingAccess) {
            const visibilityLabel = rankingVisibility.value === 'visible' ? 'Visible' : 'Masqué';
            const accessLabel = rankingAccess.value === 'public' ? 'Public' : 'Membres';
            summaryRankingPublication.textContent = `${visibilityLabel} · ${accessLabel}`;
        }
    };

    reviewFormType.addEventListener('change', () => {
        if (reviewFormType.value === 'mangasan_reading_sheet_v1') {
            rankingMethod.value = 'rank_points';
        } else if (reviewFormType.value === 'classic_score') {
            rankingMethod.value = 'average_score';
        }

        refreshMode();
        refreshSummary();
    });

    [rankingMethod, statusField, titleField, startDateField, endDateField, rankingVisibility, rankingAccess]
        .filter(Boolean)
        .forEach((field) => {
            field.addEventListener('change', () => {
                refreshMode();
                refreshStatus();
                refreshSummary();
            });
            field.addEventListener('input', refreshSummary);
        });

    form.addEventListener('submit', (event) => {
        const wasActive = form.dataset.isActive === '1';
        const becomesActive = statusField.value === 'active';

        if (!wasActive && becomesActive) {
            const confirmed = window.confirm(
                'Cette édition va devenir l’édition active de Mangasan. L’édition actuellement active, s’il y en a une, sera automatiquement désactivée. Continuer ?'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        }
    });

    refreshMode();
    refreshStatus();
    refreshSummary();
})();
