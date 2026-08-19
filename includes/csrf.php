<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void
{
    if (!verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Requête refusée. Veuillez actualiser la page et réessayer.');
    }
}