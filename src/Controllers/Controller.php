<?php

namespace App\Controllers;

/**
 * Classe de base des contrôleurs FactuPro.
 *
 * Un contrôleur reçoit la requête (déjà authentifiée par includes/auth.php),
 * orchestre les services/repositories (couche Modèle), puis délègue le rendu
 * HTML à une vue dans views/. Les URLs restent inchangées : chaque
 * pages/xxx.php reste le point d'entrée réel, mais se limite au bootstrap
 * (autoload, auth, connexion PDO) et à l'appel d'une action de contrôleur.
 */
abstract class Controller
{
    public function __construct(protected \PDO $pdo)
    {
    }

    /**
     * Inclut includes/header.php (qui ouvre <html>/<body> et le lit via
     * $page_title) puis la vue demandée, avec les variables de $data
     * extraites dans sa portée locale. À appeler uniquement une fois tout
     * traitement POST/redirection terminé, comme le faisaient les pages
     * avant la migration MVC (certaines commandes déplaçaient déjà
     * l'inclusion du header après leur traitement POST pour éviter
     * "headers already sent" — ce contrôle reste dans le contrôleur).
     */
    protected function render(string $view, array $data = [], string $pageTitle = 'FactuPro'): void
    {
        $page_title = $pageTitle;
        require __DIR__ . '/../../includes/header.php';

        extract($data, EXTR_SKIP);
        require __DIR__ . '/../../views/' . $view . '.php';
    }

    /** Vue autonome (pages de connexion, reçu imprimable...) qui n'inclut pas includes/header.php. */
    protected function renderStandalone(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../../views/' . $view . '.php';
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * URL racine de l'appli pour les liens envoyés par email (reset mot de passe, invitation...).
     *
     * Reprend l'adresse par laquelle la requête est arrivée (ngrok, Docker localhost:8080,
     * vhost Laragon...) pour que le lien fonctionne là où l'utilisateur l'ouvre — mais
     * seulement si cet hôte est de confiance : l'en-tête Host est contrôlé par le client, et
     * l'utiliser tel quel permettrait d'envoyer à une victime un lien de reset pointant vers
     * un site tiers qui capterait son token (« password reset poisoning »). Hôtes de confiance :
     * celui d'APP_URL, ceux listés dans APP_TRUSTED_HOSTS (séparés par des virgules), et le
     * loopback. Sinon, repli sur APP_URL.
     */
    protected function appBaseUrl(): string
    {
        $fallback = rtrim($_ENV['APP_URL'] ?? 'http://localhost/facturation', '/');
        $host     = strtolower($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return $fallback;
        }

        $trusted = array_filter(array_map(
            fn($h) => strtolower(trim($h)),
            explode(',', $_ENV['APP_TRUSTED_HOSTS'] ?? '')
        ));
        $trusted[] = strtolower((string) parse_url($fallback, PHP_URL_HOST));

        $hostWithoutPort = preg_replace('/:\d+$/', '', $host);
        $isLoopback = in_array($hostWithoutPort, ['localhost', '127.0.0.1', '[::1]'], true);
        if (!$isLoopback && !in_array($hostWithoutPort, $trusted, true)) {
            return $fallback;
        }

        // Derrière ngrok, Apache ne voit que du HTTP : le schéma d'origine est dans X-Forwarded-Proto.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $appRoot = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/pages/x.php'))), '/');

        return ($isHttps ? 'https' : 'http') . '://' . $host . $appRoot;
    }

    /**
     * Trace un évènement sensible dans Audit_Log (table déjà en place, cf. database/facturation.sql).
     * Id_Utilisateur/Id_Entreprise sont NOT NULL en base : n'appeler qu'avec un utilisateur
     * réellement résolu (pas d'entrée loggée pour une tentative sur un identifiant inconnu).
     */
    protected function audit(
        int $userId,
        int $entrepriseId,
        string $action,
        ?string $tableCible = null,
        ?int $idCible = null,
        ?string $details = null
    ): void {
        $this->pdo->prepare(
            'INSERT INTO Audit_Log (Id_Utilisateur, Id_Entreprise, Action, Table_Cible, Id_Cible, Details, IP_Address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $entrepriseId, $action, $tableCible, $idCible, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
