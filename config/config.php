<?php
// Charger les variables d'environnement
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key)) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Configuration de la base de données (depuis .env)
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'facturation');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Configuration SMTP (Production)
// Pour Gmail, utilisez : smtp.gmail.com
// Pour Mailtrap, utilisez : sandbox.smtp.mailtrap.io
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_USER', 'votre_user_id'); // À remplacer
define('SMTP_PASS', 'votre_password'); // À remplacer
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'noreply@factupro.com');
define('SMTP_FROM_NAME', 'FactuPro Service');

// Flags d'environnement (depuis .env)
define('IS_PRODUCTION', $_ENV['IS_PRODUCTION'] === 'true' ? true : false);

// Configuration de sécurité (depuis .env)
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 3600)); // 1 heure par défaut
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);

// Limites d'utilisation
define('MAX_INVOICE_ITEMS', 50);
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Configuration des logs
define('LOG_ERRORS', true);
define('LOG_ACCESS', true);
define('LOG_ACTIONS', true);

// Clés de sécurité (à générer aléatoirement en production)
define('ENCRYPTION_KEY', 'votre_cle_secrete_unique'); // À remplacer par une clé générée
define('APP_SECRET', 'votre_secret_application'); // À remplacer par un secret généré
// Configuration Authentification Développeur
define('DEV_PASSWORD_HASH', password_hash('dev123456', PASSWORD_BCRYPT)); // Hash du mot de passe dev
define('DEV_API_KEY', 'dev_secret_key_12345_change_in_production'); // Clé API dev
define('DEV_SESSION_NAME', 'factupro_dev_session'); // Nom de la session dev
define('DEV_SESSION_LIFETIME', 7200); // 2 heures pour session dev