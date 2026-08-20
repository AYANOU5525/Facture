<?php
/**
 * Système de rôles FactuPro
 *
 * admin   — Administrateur de la plateforme (gestion de l'app, pas des entreprises)
 * proprio — Propriétaire d'une entreprise (accès complet à son entreprise)
 * vendeur — Vendeur (ventes, clients, factures, produits en lecture)
 * livreur — Livreur (logistique uniquement)
 */

const ROLE_ADMIN   = 'admin';
const ROLE_PROPRIO = 'proprio';
const ROLE_VENDEUR = 'vendeur';
const ROLE_LIVREUR = 'livreur';

/** Vérifie si le rôle de la session est parmi ceux passés. */
function hasRole(string ...$roles): bool
{
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

/** Redirige vers le dashboard si le rôle n'est pas autorisé. */
function requireRole(string ...$roles): void
{
    if (!hasRole(...$roles)) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

/** Administrateur de la plateforme (pas d'action sur les entreprises). */
function isAppAdmin(): bool
{
    return hasRole(ROLE_ADMIN);
}

/** proprio uniquement — gestion complète de l'entreprise. */
function isManager(): bool
{
    return hasRole(ROLE_PROPRIO);
}

/** Peut créer/modifier des ventes et factures. */
function canSell(): bool
{
    return hasRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut voir la liste des produits (lecture). */
function canViewStock(): bool
{
    return hasRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut modifier le stock (approvisionnement, ajout/suppression produits). */
function canManageStock(): bool
{
    return hasRole(ROLE_PROPRIO);
}

/** Peut accéder aux fonctions logistiques. */
function canDeliver(): bool
{
    return hasRole(ROLE_PROPRIO, ROLE_LIVREUR);
}

/** Peut accéder au réseau B2B et aux commandes inter-entreprises. */
function canAccessB2B(): bool
{
    return hasRole(ROLE_PROPRIO);
}

/** Peut gérer l'équipe et les paramètres de l'entreprise. */
function canManageTeam(): bool
{
    return hasRole(ROLE_PROPRIO);
}

/** Libellé lisible du rôle. */
function roleName(string $role = ''): string
{
    return match($role ?: ($_SESSION['role'] ?? '')) {
        ROLE_ADMIN   => 'Administrateur',
        ROLE_PROPRIO => 'Propriétaire',
        ROLE_VENDEUR => 'Vendeur',
        ROLE_LIVREUR => 'Livreur',
        default      => 'Utilisateur',
    };
}

/** Couleur badge selon le rôle. */
function roleBadgeClass(string $role): string
{
    return match($role) {
        ROLE_ADMIN   => 'badge-danger',
        ROLE_PROPRIO => 'badge-primary',
        ROLE_VENDEUR => 'badge-success',
        ROLE_LIVREUR => 'badge-warning',
        default      => 'badge-secondary',
    };
}
