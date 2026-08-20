<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Génère un token CSRF à usage unique et l'ajoute à la liste des tokens valides.
function csrfToken(): string
{
    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][] = $token;

    // Pool de 30 tokens maximum pour éviter le gonflement de session
    if (count($_SESSION['csrf_tokens']) > 30) {
        $_SESSION['csrf_tokens'] = array_slice($_SESSION['csrf_tokens'], -30);
    }

    return $token;
}

// Vérifie ET consomme le token (usage unique).
function verifyCsrf(string $token): bool
{
    if (empty($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        return false;
    }

    $key = array_search($token, $_SESSION['csrf_tokens'], true);
    if ($key === false) {
        return false;
    }

    // Suppression immédiate après vérification — le token ne peut plus être réutilisé
    unset($_SESSION['csrf_tokens'][$key]);
    $_SESSION['csrf_tokens'] = array_values($_SESSION['csrf_tokens']);

    return true;
}

function requireCsrf(): void
{
    if (!verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Requête refusée. Veuillez actualiser la page et réessayer.');
    }
}