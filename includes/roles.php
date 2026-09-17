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

/**
 * Interrupteur central : active la gestion des livraisons (logistique). Toutes les
 * permissions et la navigation logistique en dépendent — c'est le seul endroit à changer.
 */
const FEATURE_LOGISTIQUE_ACTIVE = true;

/** Vérifie si le rôle de la session est parmi ceux passés. */
function aRole(string ...$roles): bool
{
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

/** Redirige vers le dashboard si le rôle n'est pas autorisé. */
function exigerRole(string ...$roles): void
{
    if (!aRole(...$roles)) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Redirige vers le dashboard si la permission n'est pas accordée.
 * À utiliser avec les fonctions can*()/is*() ci-dessous, ex : exigerPermission(peutVoirProduits()).
 * Centralise l'autorisation sur l'objectif métier plutôt que sur une liste de rôles
 * recopiée dans chaque page — ajouter un rôle ou une permission ne se fait qu'ici.
 */
function exigerPermission(bool $allowed): void
{
    if (!$allowed) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

/** Administrateur de la plateforme (pas d'action sur les entreprises). */
function estAdminPlateforme(): bool
{
    return aRole(ROLE_ADMIN);
}

/** proprio uniquement — gestion complète de l'entreprise. */
function estProprietaire(): bool
{
    return aRole(ROLE_PROPRIO);
}

/** Peut créer/modifier des ventes et factures. */
function peutVendre(): bool
{
    return aRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut voir la liste des produits (lecture). */
function peutVoirStock(): bool
{
    return aRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut modifier le stock (approvisionnement, ajout/suppression produits). */
function peutGererStock(): bool
{
    return aRole(ROLE_PROPRIO);
}

/** Peut accéder aux fonctions logistiques. */
function peutLivrer(): bool
{
    return FEATURE_LOGISTIQUE_ACTIVE && aRole(ROLE_PROPRIO, ROLE_LIVREUR);
}

/** Peut accéder au réseau B2B et aux commandes inter-entreprises. */
function peutAccederB2B(): bool
{
    return aRole(ROLE_PROPRIO);
}

/** Peut voir la liste des produits (lecture seule). */
function peutVoirProduits(): bool
{
    return aRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut modifier les produits et le stock (ajout, suppression, approvisionnement). */
function peutGererProduits(): bool
{
    return aRole(ROLE_PROPRIO);
}

/** Peut créer une vente. */
function peutCreerVente(): bool
{
    return aRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut consulter l'historique des ventes et factures. */
function peutVoirVentes(): bool
{
    return aRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut consulter les factures. */
function peutVoirFactures(): bool
{
    return aRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut consulter la liste des clients. */
function peutVoirClients(): bool
{
    return aRole(ROLE_PROPRIO, ROLE_VENDEUR);
}

/** Peut consulter les expéditions logistiques. */
function peutVoirExpeditions(): bool
{
    return FEATURE_LOGISTIQUE_ACTIVE && aRole(ROLE_PROPRIO, ROLE_LIVREUR);
}

/** Peut créer/supprimer des entrées logistiques. */
function peutGererExpeditions(): bool
{
    return FEATURE_LOGISTIQUE_ACTIVE && aRole(ROLE_PROPRIO);
}

/** Peut mettre à jour le statut d'une expédition. */
function peutModifierStatutExpedition(): bool
{
    return FEATURE_LOGISTIQUE_ACTIVE && aRole(ROLE_PROPRIO, ROLE_LIVREUR);
}

/** Peut accéder aux fonctionnalités B2B (commandes, réseau, chat). */
function peutGererB2B(): bool
{
    return aRole(ROLE_PROPRIO);
}

/** Peut gérer les paramètres de l'entreprise. */
function peutGererParametres(): bool
{
    return aRole(ROLE_PROPRIO);
}

/** Peut gérer l'équipe et les paramètres de l'entreprise. */
function peutGererEquipe(): bool
{
    return aRole(ROLE_PROPRIO);
}

/** Accès aux fonctionnalités de gestion de la plateforme (admin uniquement). */
function peutGererPlateforme(): bool
{
    return aRole(ROLE_ADMIN);
}

/** Libellé lisible du rôle. */
function nomRole(string $role = ''): string
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
function classeBadgeRole(string $role): string
{
    return match($role) {
        ROLE_ADMIN   => 'text-bg-danger',
        ROLE_PROPRIO => 'text-bg-primary',
        ROLE_VENDEUR => 'text-bg-success',
        ROLE_LIVREUR => 'text-bg-warning',
        default      => 'text-bg-secondary',
    };
}
