(() => {
    function setTextContent(element, value, fallback = '') {
        if (!element) {
            return;
        }

        element.textContent = value && value.trim() !== '' ? value : fallback;
    }

    function toggleClass(element, className, shouldHaveClass) {
        if (!element) {
            return;
        }

        if (shouldHaveClass) {
            element.classList.add(className);
        } else {
            element.classList.remove(className);
        }
    }

    function initSectionPreview() {
        const preview = document.getElementById('sectionPreview');

        if (!preview) {
            return;
        }

        const titleInput = document.getElementById('title');
        const subtitleInput = document.getElementById('subtilte');
        const contentInput = document.getElementById('content');
        const mediaTypeInput = document.getElementById('media_type');
        const mediaValueInput = document.getElementById('media_value');
        const mediaFileInput = document.getElementById('media_file');
        const visibilityInput = document.getElementById('is_visible');

        const previewTitle = document.getElementById('sectionPreviewTitle');
        const previewSubtitle = document.getElementById('sectionPreviewSubtitle');
        const previewContent = document.getElementById('sectionPreviewContent');
        const previewVisibility = document.getElementById('sectionPreviewVisibility');
        const previewMedia = document.getElementById('sectionPreviewMedia');
        const previewImage = document.getElementById('sectionPreviewImage');
        const previewVideo = document.getElementById('sectionPreviewVideo');

        let currentObjectUrl = null;

        function clearObjectUrl() {
            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
                currentObjectUrl = null;
            }
        }

        function updatePreview() {
            const title = titleInput ? titleInput.value : '';
            const subtitle = subtitleInput ? subtitleInput.value : '';
            const content = contentInput ? contentInput.value : '';
            const mediaType = mediaTypeInput ? mediaTypeInput.value : 'none';
            const mediaValue = mediaValueInput ? mediaValueInput.value.trim() : '';
            const isVisible = visibilityInput ? visibilityInput.value === '1' : true;

            setTextContent(previewTitle, title, 'Titre de section');
            setTextContent(previewSubtitle, subtitle, '');
            setTextContent(previewContent, content, 'Aucun contenu saisi.');

            if (previewVisibility) {
                previewVisibility.textContent = isVisible ? 'Visible' : 'Masquée';
                toggleClass(previewVisibility, 'is-visible', isVisible);
                toggleClass(previewVisibility, 'is-hidden', !isVisible);
            }

            toggleClass(previewMedia, 'is-hidden', mediaType === 'none');
            toggleClass(previewImage, 'is-hidden', mediaType !== 'image');
            toggleClass(previewVideo, 'is-hidden', mediaType !== 'video');

            if (mediaType === 'image' && previewImage) {
                clearObjectUrl();

                const file = mediaFileInput && mediaFileInput.files && mediaFileInput.files[0]
                    ? mediaFileInput.files[0]
                    : null;

                if (file) {
                    currentObjectUrl = URL.createObjectURL(file);
                    previewImage.src = currentObjectUrl;
                } else {
                    previewImage.src = mediaValue;
                }
            }

            if (mediaType === 'video' && previewVideo) {
                previewVideo.textContent = mediaValue !== '' ? mediaValue : 'Aucune vidéo définie.';
            }
        }

        [
            titleInput,
            subtitleInput,
            contentInput,
            mediaTypeInput,
            mediaValueInput,
            mediaFileInput,
            visibilityInput
        ].forEach((element) => {
            if (!element) {
                return;
            }

            element.addEventListener('input', updatePreview);
            element.addEventListener('change', updatePreview);
        });

        updatePreview();
    }

    function initSettingsPreview() {
        const preview = document.getElementById('settingsPreview');

        if (!preview) {
            return;
        }

        const siteTitleInput = document.getElementById('site_title');
        const siteTitleTypeInput = document.getElementById('site_title_type');
        const logoPathInput = document.getElementById('logo_path');
        const logoFileInput = document.getElementById('logo_file');
        const primaryColorInput = document.getElementById('primary_color');
        const secondaryColorInput = document.getElementById('secondary_color');
        const backgroundColorInput = document.getElementById('background_color');
        const textColorInput = document.getElementById('text_color');
        const accentColorInput = document.getElementById('accent_color');
        const heroTextColorInput = document.getElementById('hero_text_color');
        const heroBackgroundTypeInput = document.getElementById('hero_background_type');
        const heroBackgroundValueInput = document.getElementById('hero_background_value');
        const heroBackgroundFileInput = document.getElementById('hero_background_file');
        const homepageIntroInput = document.getElementById('homepage_intro');
        const hideHeroTextInput = document.getElementById('hide_hero_text');
        const visualEffectTypeInput = document.getElementById('visual_effect_type');
        const heroLoginPositionInput = document.getElementById('hero_login_position');

        const previewRoot = document.getElementById('sitePreviewRoot');
        const previewHeader = document.getElementById('previewSiteHeader');
        const previewHero = document.getElementById('previewHero');
        const previewHeroInner = document.getElementById('previewHeroInner');
        const previewHeroCopy = document.getElementById('previewHeroCopy');
        const previewTitleText = document.getElementById('previewSiteTitleText');
        const previewLogoImage = document.getElementById('previewSiteLogoImage');
        const previewHeroTitle = document.getElementById('previewHeroTitle');
        const previewHeroIntro = document.getElementById('previewHeroIntro');
        const previewHeroMediaLabel = document.getElementById('previewHeroMediaLabel');
        const previewLoginBox = document.getElementById('previewLoginBox');
        const previewEffectLayer = document.getElementById('previewEffectLayer');
        const previewEffectLabel = document.getElementById('previewEffectLabel');

        let logoObjectUrl = null;
        let heroObjectUrl = null;

        const effectLabels = {
            none: 'aucun',
            sparkles: 'paillettes',
            aurora: 'faisceaux / aurore',
            speed_lines: 'lignes manga',
            manga_dots: 'trame manga',
            music_notes: 'notes de musique'
        };

        function clearUrl(type) {
            if (type === 'logo' && logoObjectUrl) {
                URL.revokeObjectURL(logoObjectUrl);
                logoObjectUrl = null;
            }

            if (type === 'hero' && heroObjectUrl) {
                URL.revokeObjectURL(heroObjectUrl);
                heroObjectUrl = null;
            }
        }

        function updateTitleDisplay(siteTitleType, siteTitle, logoSource, textColor) {
            if (previewTitleText) {
                previewTitleText.style.color = textColor;
                previewTitleText.textContent = siteTitle !== '' ? siteTitle : 'Mangasan';
            }

            if (previewLogoImage) {
                previewLogoImage.src = logoSource !== '' ? logoSource : '';
            }

            toggleClass(previewTitleText, 'is-hidden', !['text', 'text_image'].includes(siteTitleType));
            toggleClass(previewLogoImage, 'is-hidden', !['image', 'text_image'].includes(siteTitleType) || logoSource === '');
        }

        function updateEffectPreview(effectType) {
            if (previewEffectLabel) {
                previewEffectLabel.textContent = `Effet : ${effectLabels[effectType] || effectType}`;
            }

            if (!previewEffectLayer) {
                return;
            }

            previewEffectLayer.className = 'admin-site-preview-effect-layer';

            if (effectType !== 'none') {
                previewEffectLayer.classList.add(`is-${effectType}`);
            }
        }

        function updatePreview() {
            const siteTitle = siteTitleInput ? siteTitleInput.value.trim() : 'Mangasan';
            const siteTitleType = siteTitleTypeInput ? siteTitleTypeInput.value : 'text';
            const primaryColor = primaryColorInput ? primaryColorInput.value : '#e50914';
            const secondaryColor = secondaryColorInput ? secondaryColorInput.value : '#333333';
            const backgroundColor = backgroundColorInput ? backgroundColorInput.value : '#0f0f0f';
            const textColor = textColorInput ? textColorInput.value : '#ffffff';
            const accentColor = accentColorInput ? accentColorInput.value : '#c1121f';
            const heroTextColor = heroTextColorInput ? heroTextColorInput.value : '#ffffff';
            const heroBackgroundType = heroBackgroundTypeInput ? heroBackgroundTypeInput.value : 'color';
            const heroBackgroundValue = heroBackgroundValueInput ? heroBackgroundValueInput.value.trim() : '';
            const homepageIntro = homepageIntroInput ? homepageIntroInput.value : '';
            const hideHeroText = hideHeroTextInput ? hideHeroTextInput.checked : false;
            const visualEffectType = visualEffectTypeInput ? visualEffectTypeInput.value : 'none';
            const heroLoginPosition = heroLoginPositionInput ? heroLoginPositionInput.value : 'right';

            if (previewRoot) {
                previewRoot.style.backgroundColor = backgroundColor;
                previewRoot.style.color = textColor;
                previewRoot.style.setProperty('--preview-primary', primaryColor);
                previewRoot.style.setProperty('--preview-accent', accentColor);
            }

            if (previewHeader) {
                previewHeader.style.backgroundColor = primaryColor;
                previewHeader.style.color = textColor;
                previewHeader.style.borderBottomColor = accentColor;
            }

            if (previewLoginBox) {
                previewLoginBox.style.backgroundColor = secondaryColor;
                previewLoginBox.style.color = textColor;
            }

            if (previewHeroTitle) {
                previewHeroTitle.style.color = heroTextColor;
                previewHeroTitle.textContent = siteTitle !== '' ? siteTitle : 'Mangasan';
            }

            if (previewHeroIntro) {
                previewHeroIntro.style.color = heroTextColor;
                previewHeroIntro.textContent = homepageIntro !== '' ? homepageIntro : 'Texte d’introduction';
            }

            toggleClass(previewHeroCopy, 'is-hidden', hideHeroText);

            if (previewHeroInner) {
                toggleClass(previewHeroInner, 'is-login-left', heroLoginPosition === 'left');
            }

            const logoFile = logoFileInput && logoFileInput.files && logoFileInput.files[0]
                ? logoFileInput.files[0]
                : null;

            let logoSource = logoPathInput ? logoPathInput.value.trim() : '';

            if (logoFile) {
                clearUrl('logo');
                logoObjectUrl = URL.createObjectURL(logoFile);
                logoSource = logoObjectUrl;
            }

            updateTitleDisplay(siteTitleType, siteTitle, logoSource, textColor);
            updateEffectPreview(visualEffectType);

            if (previewHero) {
                previewHero.style.background = backgroundColor;
                previewHero.style.backgroundImage = 'none';
                previewHero.style.backgroundSize = 'cover';
                previewHero.style.backgroundPosition = 'center';
                previewHero.style.backgroundRepeat = 'no-repeat';
            }

            if (previewHeroMediaLabel) {
                previewHeroMediaLabel.textContent = '';
            }

            const heroFile = heroBackgroundFileInput && heroBackgroundFileInput.files && heroBackgroundFileInput.files[0]
                ? heroBackgroundFileInput.files[0]
                : null;

            if (heroBackgroundType === 'color' && previewHero) {
                previewHero.style.background = `linear-gradient(135deg, ${secondaryColor}, ${backgroundColor})`;
            }

            if (heroBackgroundType === 'image' && previewHero) {
                let heroSource = heroBackgroundValue;

                if (heroFile) {
                    clearUrl('hero');
                    heroObjectUrl = URL.createObjectURL(heroFile);
                    heroSource = heroObjectUrl;
                }

                if (heroSource !== '') {
                    previewHero.style.backgroundImage = `url("${heroSource}")`;
                    previewHero.style.backgroundSize = 'cover';
                    previewHero.style.backgroundPosition = 'center';
                    previewHero.style.backgroundRepeat = 'no-repeat';

                    if (previewHeroMediaLabel) {
                        previewHeroMediaLabel.textContent = 'Image du bandeau';
                    }
                } else {
                    previewHero.style.background = `linear-gradient(135deg, ${secondaryColor}, ${backgroundColor})`;
                }
            }

            if (heroBackgroundType === 'video' && previewHero) {
                previewHero.style.background = `linear-gradient(135deg, ${secondaryColor}, ${backgroundColor})`;

                if (previewHeroMediaLabel) {
                    previewHeroMediaLabel.textContent = heroBackgroundValue !== '' ? `Vidéo : ${heroBackgroundValue}` : 'Vidéo du bandeau';
                }
            }
        }

        [
            siteTitleInput,
            siteTitleTypeInput,
            logoPathInput,
            logoFileInput,
            primaryColorInput,
            secondaryColorInput,
            backgroundColorInput,
            textColorInput,
            accentColorInput,
            heroTextColorInput,
            heroBackgroundTypeInput,
            heroBackgroundValueInput,
            heroBackgroundFileInput,
            homepageIntroInput,
            hideHeroTextInput,
            visualEffectTypeInput,
            heroLoginPositionInput
        ].forEach((element) => {
            if (!element) {
                return;
            }

            element.addEventListener('input', updatePreview);
            element.addEventListener('change', updatePreview);
        });

        updatePreview();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSectionPreview();
        initSettingsPreview();
    });
})();
