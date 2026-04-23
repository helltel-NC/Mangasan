<?php

declare(strict_types=1);

function setFlashMessage(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];

    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}