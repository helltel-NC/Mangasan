<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
require_once __DIR__ . '/../includes/log.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mangasan/admin/editions.php');
    exit;
}

$editionId = filter_input(INPUT_POST, 'edition_id', FILTER_VALIDATE_INT);

if (!$editionId) {
    setFlashMessage('error', 'Édition invalide.');
    header('Location: /mangasan/admin/editions.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title, is_active, status FROM editions WHERE id = :id LIMIT 1");
    $stmt->execute([
        'id' => $editionId
    ]);

    $edition = $stmt->fetch();

    if (!$edition) {
        setFlashMessage('error', 'Édition introuvable.');
        header('Location: /mangasan/admin/editions.php');
        exit;
    }

    $pdo->beginTransaction();

    if ((int) $edition['is_active'] === 1) {
        $stmtDeactivate = $pdo->prepare(
            "UPDATE editions
             SET is_active = 0,
                 status = 'closed'
             WHERE id = :id"
        );
        $stmtDeactivate->execute([
            'id' => $editionId
        ]);

        logAction(
            $pdo,
            getCurrentUserId(),
            'edition_deactivate',
            'edition',
            (int) $editionId,
            'Désactivation de l’édition "' . (string) $edition['title'] . '".'
        );

        $pdo->commit();

        setFlashMessage('success', 'L’édition a été désactivée.');
        header('Location: /mangasan/admin/editions.php');
        exit;
    }

    $pdo->exec(
        "UPDATE editions
         SET is_active = 0,
             status = CASE
                 WHEN status = 'active' THEN 'closed'
                 ELSE status
             END"
    );

    $stmtActivate = $pdo->prepare(
        "UPDATE editions
         SET is_active = 1,
             status = 'active'
         WHERE id = :id"
    );
    $stmtActivate->execute([
        'id' => $editionId
    ]);

    logAction(
        $pdo,
        getCurrentUserId(),
        'edition_activate',
        'edition',
        (int) $editionId,
        'Activation de l’édition "' . (string) $edition['title'] . '".'
    );

    $pdo->commit();

    setFlashMessage('success', 'L’édition a été activée.');
    header('Location: /mangasan/admin/editions.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlashMessage('error', 'Impossible de modifier l’état actif de l’édition.');
    header('Location: /mangasan/admin/editions.php');
    exit;
}