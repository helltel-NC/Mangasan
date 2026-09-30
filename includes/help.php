<?php

declare(strict_types=1);

/**
 * Affiche le moteur d'aide contextuelle pour une page d'administration.
 *
 * Configuration attendue :
 * [
 *   'id' => 'admin-users',
 *   'title' => 'Guide — Gestion des utilisateurs',
 *   'steps' => [
 *      ['target' => '#selector', 'title' => '...', 'text' => '...'],
 *   ],
 * ]
 */
function renderAdminHelpGuide(array $guide): void
{
    $guideId = trim((string) ($guide['id'] ?? 'admin-help'));
    $guideTitle = trim((string) ($guide['title'] ?? 'Guide de la page'));
    $rawSteps = is_array($guide['steps'] ?? null) ? $guide['steps'] : [];

    $steps = [];

    foreach ($rawSteps as $step) {
        if (!is_array($step)) {
            continue;
        }

        $target = trim((string) ($step['target'] ?? ''));
        $title = trim((string) ($step['title'] ?? ''));
        $text = trim((string) ($step['text'] ?? ''));
        $tip = trim((string) ($step['tip'] ?? ''));
        $tab = trim((string) ($step['tab'] ?? ''));

        if ($target === '' || $title === '' || $text === '') {
            continue;
        }

        $steps[] = [
            'target' => $target,
            'title' => $title,
            'text' => $text,
            'tip' => $tip,
            'tab' => $tab,
        ];
    }

    if ($steps === []) {
        return;
    }

    $config = [
        'id' => $guideId,
        'title' => $guideTitle,
        'steps' => $steps,
    ];

    $json = json_encode(
        $config,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    if (!is_string($json)) {
        return;
    }
    ?>
    <div class="admin-help-layer" id="adminHelpLayer" aria-hidden="true" hidden>
        <div class="admin-help-blocker" aria-hidden="true"></div>
        <div class="admin-help-spotlight" id="adminHelpSpotlight" aria-hidden="true"></div>

        <aside
            class="admin-help-popover"
            id="adminHelpPopover"
            role="dialog"
            aria-modal="true"
            aria-labelledby="adminHelpTitle"
            aria-describedby="adminHelpText"
            tabindex="-1"
        >
            <div class="admin-help-popover-head">
                <div>
                    <span class="admin-help-guide-name" id="adminHelpGuideName"></span>
                    <span class="admin-help-counter" id="adminHelpCounter"></span>
                </div>

                <button
                    type="button"
                    class="admin-help-close"
                    id="adminHelpClose"
                    aria-label="Fermer le guide"
                    title="Fermer le guide"
                >×</button>
            </div>

            <div class="admin-help-progress" aria-hidden="true">
                <span id="adminHelpProgressBar"></span>
            </div>

            <div class="admin-help-popover-body">
                <h2 id="adminHelpTitle"></h2>
                <p id="adminHelpText"></p>
                <p class="admin-help-tip is-hidden" id="adminHelpTip"></p>
            </div>

            <div class="admin-help-popover-actions">
                <button type="button" class="btn btn-secondary" id="adminHelpPrevious">Précédent</button>
                <button type="button" class="btn btn-primary" id="adminHelpNext">Suivant</button>
            </div>
        </aside>
    </div>

    <script type="application/json" id="adminHelpConfig"><?php echo $json; ?></script>
    <?php
}
