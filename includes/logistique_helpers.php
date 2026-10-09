<?php
/**
 * Affichage du statut d'une livraison (fiche Logistique), commun à la liste, au tableau de
 * bord livreur, à la fiche et aux commandes B2B. Le statut « expediee » se précise selon les
 * confirmations reçues : la livraison n'est close qu'une fois confirmée par le livreur ET
 * par l'acheteur (une vente directe n'a que la confirmation du livreur).
 *
 * @param array $l ligne Logistique (Statut_Livraison, Id_Commande_B2B, Date_Confirmation_*)
 * @return array{label: string, badge: string, icon: string}
 */
function libelleStatutLivraison(array $l): array
{
    $statut = $l['Statut_Livraison'] ?? 'traitement';
    if ($statut === 'expediee' && !empty($l['Id_Commande_B2B'])) {
        $livreur = !empty($l['Date_Confirmation_Livreur']);
        $acheteur = !empty($l['Date_Confirmation_Acheteur']);
        if ($livreur && !$acheteur) {
            return ['label' => "Remise, attente de l'acheteur", 'badge' => 'info', 'icon' => 'fa-hourglass-half'];
        }
        if ($acheteur && !$livreur) {
            return ['label' => 'Reçue, attente du livreur', 'badge' => 'info', 'icon' => 'fa-hourglass-half'];
        }
    }

    return match ($statut) {
        'expediee' => ['label' => 'En route', 'badge' => 'primary', 'icon' => 'fa-truck'],
        'livree'   => ['label' => 'Livrée', 'badge' => 'success', 'icon' => 'fa-check-circle'],
        'annulee'  => ['label' => 'Annulée', 'badge' => 'danger', 'icon' => 'fa-ban'],
        default    => ['label' => 'À planifier', 'badge' => 'warning', 'icon' => 'fa-clipboard-list'],
    };
}
