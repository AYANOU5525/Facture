<?php

set_exception_handler(function (Throwable $e) {
    error_log('[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (headers_sent()) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Une erreur inattendue est survenue. Veuillez réessayer.</div>';
        return;
    }

    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($is_ajax) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erreur interne du serveur.']);
        exit;
    }

    $dev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
    http_response_code(500);

    if ($dev) {
        echo '<pre style="background:#1e1e1e;color:#f8f8f2;padding:20px;margin:20px;border-radius:8px;overflow:auto;">';
        echo htmlspecialchars((string) $e);
        echo '</pre>';
    } else {
        header('Location: /pages/dashboard.php?error=server');
    }
    exit;
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("[PHP ERROR $errno] $errstr in $errfile:$errline");
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    return true;
});
