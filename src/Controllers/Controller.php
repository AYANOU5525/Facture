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
}
