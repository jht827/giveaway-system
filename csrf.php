<?php
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require(): void
{
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die("ERR: CSRF token invalid.");
    }
}
?>
