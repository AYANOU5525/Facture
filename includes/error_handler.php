<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    error_log('[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $is_debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($is_ajax) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode($is_debug ? [
            'success' => false,
            'error'   => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ] : [
            'success' => false,
            'error'   => 'Une erreur serveur est survenue.',
        ]);
        exit;
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (!$is_debug) {
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<title>Erreur serveur</title>'
           . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0f172a;color:#f8fafc;'
           . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;text-align:center;}'
           . '.box{max-width:480px;}h1{color:#ef4444;}a{color:#3b82f6;}</style></head><body>'
           . '<div class="box"><h1>Une erreur est survenue</h1>'
           . '<p>Veuillez réessayer ou revenir à la connexion.</p>'
           . '<p><a href="login.php">Page de connexion</a> · <a href="dashboard.php">Tableau de bord</a></p>'
           . '</div></body></html>';
        exit;
    }

    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Erreur - ' . htmlspecialchars(get_class($e)) . '</title>';
    echo '<style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 24px; }
        .error-container { max-width: 1000px; margin: 0 auto; background: #1e293b; border: 1px solid #ef4444; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .error-header { background: #b91c1c; color: white; padding: 18px 24px; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; justify-content: space-between; }
        .error-body { padding: 24px; }
        .error-message { background: #450a0a; border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 6px; font-size: 1.05rem; font-weight: 600; color: #fecaca; margin-bottom: 20px; word-break: break-word; }
        .error-meta { display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; margin-bottom: 20px; font-size: 0.92rem; background: #0f172a; padding: 14px 18px; border-radius: 8px; border: 1px solid #334155; }
        .error-meta strong { color: #94a3b8; }
        .error-meta span { color: #e2e8f0; font-family: Consolas, Monaco, monospace; word-break: break-all; }
        .trace-title { font-size: 1rem; font-weight: 700; margin-bottom: 8px; color: #cbd5e1; }
        .trace-box { background: #020617; border: 1px solid #1e293b; border-radius: 8px; padding: 16px; overflow-x: auto; font-family: Consolas, Monaco, monospace; font-size: 0.85rem; line-height: 1.6; color: #cbd5e1; white-space: pre-wrap; word-break: break-all; max-height: 400px; overflow-y: auto; }
        .actions { margin-top: 24px; display: flex; gap: 12px; }
        .btn { padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-block; cursor: pointer; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
    </style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="error-container">';
    echo '  <div class="error-header">';
    echo '    <span>Exception : ' . htmlspecialchars(get_class($e)) . '</span>';
    echo '  </div>';
    echo '  <div class="error-body">';
    echo '    <div class="error-message">' . nl2br(htmlspecialchars($e->getMessage())) . '</div>';
    echo '    <div class="error-meta">';
    echo '      <strong>Fichier :</strong><span>' . htmlspecialchars($e->getFile()) . '</span>';
    echo '      <strong>Ligne :</strong><span>' . (int)$e->getLine() . '</span>';
    echo '      <strong>Code :</strong><span>' . htmlspecialchars((string)$e->getCode()) . '</span>';
    echo '    </div>';
    echo '    <div class="trace-title">Pile d\'exécution (Stack Trace) :</div>';
    echo '    <pre class="trace-box">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '    <div class="actions">';
    echo '      <a href="login.php" class="btn btn-primary">Page de connexion</a>';
    echo '      <a href="dashboard.php" class="btn btn-secondary">Recharger le tableau de bord</a>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
    echo '</body></html>';
    exit;
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("[PHP ERROR $errno] $errstr in $errfile:$errline");
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    return false;
});
