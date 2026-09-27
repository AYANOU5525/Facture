<?php
/**
 * Sauvegarde de la base FactuPro (mysqldump horodaté) — aucune stratégie de sauvegarde
 * n'existait jusqu'ici (données perdables en cas de mauvaise manipulation, cf. incident
 * documenté sur database/facturation.sql qui réinitialise toute donnée existante).
 *
 * Usage : php database/backup.php
 * Résultat : database/backups/facturation_AAAA-MM-JJ_HH-mm-ss.sql
 *
 * Lit les identifiants depuis .env (DB_HOST/DB_NAME/DB_USER/DB_PASS) — donc valable pour
 * l'environnement dans lequel la commande est lancée (Laragon natif, ou `docker exec
 * facturation_app php database/backup.php` pour sauvegarder la base du conteneur).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$name = $_ENV['DB_NAME'] ?? 'facturation';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Impossible de créer le dossier $backupDir\n");
    exit(1);
}

$fichier = $backupDir . '/facturation_' . date('Y-m-d_H-i-s') . '.sql';

// Mot de passe passé via variable d'environnement (MYSQL_PWD), jamais en argument de commande
// visible dans la liste des processus (ps aux) ou l'historique shell. --ssl-mode=DISABLED :
// connexion locale/interne de confiance (Laragon natif ou réseau Docker interne), le certificat
// auto-généré par MySQL pour ses connexions chiffrées n'a pas besoin d'être validé ici.
$commande = sprintf(
    'mysqldump --no-tablespaces --default-character-set=utf8mb4 --skip-ssl -h %s -u %s %s > %s',
    escapeshellarg($host),
    escapeshellarg($user),
    escapeshellarg($name),
    escapeshellarg($fichier)
);

$env = ['PATH' => getenv('PATH') ?: '/usr/bin:/bin'];
if ($pass !== '') {
    $env['MYSQL_PWD'] = $pass;
}

$descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open($commande, $descriptorSpec, $pipes, null, $env);

if (!is_resource($process)) {
    fwrite(STDERR, "Impossible de lancer mysqldump.\n");
    exit(1);
}

fclose($pipes[1]);
$erreurs = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$code = proc_close($process);

if ($code !== 0 || !file_exists($fichier) || filesize($fichier) === 0) {
    fwrite(STDERR, "Échec de la sauvegarde (code $code).\n" . $erreurs . "\n");
    fwrite(STDERR, "Vérifiez que 'mysqldump' est dans le PATH (inclus avec Laragon/MySQL, ou installé dans le conteneur Docker).\n");
    if (file_exists($fichier)) {
        unlink($fichier);
    }
    exit(1);
}

echo "Sauvegarde créée : $fichier (" . round(filesize($fichier) / 1024, 1) . " Ko)\n";
