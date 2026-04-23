<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

logoutUser();

header('Location: /mangasan/public/index.php');
exit;