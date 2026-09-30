(() => {
    const mediaType = document.getElementById('media_type');
    const mediaFields = document.getElementById('sectionMediaFields');
    const mediaValueField = document.getElementById('sectionMediaValueField');
    const mediaUploadField = document.getElementById('sectionMediaUploadField');
    const mediaValueLabel = document.getElementById('sectionMediaValueLabel');

    if (!mediaType || !mediaFields) {
        return;
    }

    function updateMediaFields() {
        const type = mediaType.value;
        const hasMedia = type !== 'none';

        mediaFields.hidden = !hasMedia;

        if (mediaValueField) {
            mediaValueField.hidden = !hasMedia;
        }

        if (mediaUploadField) {
            mediaUploadField.hidden = type !== 'image';
        }

        if (mediaValueLabel) {
            mediaValueLabel.textContent = type === 'video'
                ? 'Chemin / URL de la vidéo'
                : 'Chemin de l’image';
        }
    }

    mediaType.addEventListener('change', updateMediaFields);
    updateMediaFields();
})();
