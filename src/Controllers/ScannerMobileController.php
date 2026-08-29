<?php

namespace App\Controllers;

/**
 * Contrôleur de pages/scanner_mobile.php — interface minimale ouverte sur le
 * téléphone après scan du QR Code. Pas de login : l'accès est protégé
 * uniquement par le token (aléatoire, temporaire, à usage limité) validé
 * côté serveur dans api/scan_session.php. Aucune donnée sensible dans l'URL.
 */
class ScannerMobileController extends Controller
{
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: camera=(self), microphone=(), geolocation=()");
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://unpkg.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; " .
            "img-src 'self' data:; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none'"
        );
        header("Cache-Control: no-cache, no-store, must-revalidate");

        $token = trim($_GET['token'] ?? '');

        $this->renderStandalone('scanner_mobile/index', [
            'token' => $token,
        ]);
    }
}
