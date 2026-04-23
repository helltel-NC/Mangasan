<?php

declare(strict_types=1);

function getClientIpAddress(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    return is_string($ip) && $ip !== '' ? $ip : null;
}

function logAction(
    PDO $pdo,
    ?int $userId,
    string $actionType,
    ?string $targetType,
    ?int $targetId,
    string $message
): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO logs (user_id, action_type, target_type, target_id, message, ip_address)
             VALUES (:user_id, :action_type, :target_type, :target_id, :message, :ip_address)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'action_type' => $actionType,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'message' => $message,
            'ip_address' => getClientIpAddress()
        ]);
    } catch (Throwable $e) {
    }
}