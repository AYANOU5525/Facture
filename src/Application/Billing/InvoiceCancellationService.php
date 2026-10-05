<?php

declare(strict_types=1);

namespace App\Application\Billing;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Annulation d'une facture de vente au comptoir.
 *
 * L'obligation légale est de CONSERVER une facture 10 ans, pas d'interdire son annulation :
 * la facture reste en base (jamais supprimée), passe au statut « annulee » de façon
 * définitive, et les produits retournent en stock. Règles :
 *  - factures B2B exclues : elles suivent le cycle de la commande (validation, expédition...) ;
 *  - refusée si la marchandise est déjà expédiée ou livrée (elle n'est plus en magasin) ;
 *  - une facture annulée ne change plus de statut.
 */
final class InvoiceCancellationService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** SQL : la vente (alias $alias) n'a pas de facture annulée — à utiliser dans les calculs de CA. */
    public static function notCancelledSql(string $alias = 'Vente'): string
    {
        return "NOT EXISTS (SELECT 1 FROM Facture fa WHERE fa.Id_Vente = $alias.Id_Vente AND fa.Statut_Paiement = 'annulee')";
    }

    /** @return int nombre d'unités remises en stock */
    public function cancel(int $invoiceId, int $enterpriseId): int
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'SELECT Id_Facture, Id_Vente, Id_Commande_B2B, Statut_Paiement
                 FROM Facture WHERE Id_Facture = ? AND Id_Entreprise = ? FOR UPDATE'
            );
            $statement->execute([$invoiceId, $enterpriseId]);
            $invoice = $statement->fetch();

            if (!$invoice) {
                throw new RuntimeException('Facture introuvable.');
            }
            if ($invoice['Statut_Paiement'] === 'annulee') {
                throw new InvalidArgumentException('Cette facture est déjà annulée.');
            }
            if ($invoice['Id_Commande_B2B'] !== null) {
                throw new InvalidArgumentException('Une facture B2B ne peut pas être annulée : elle suit le cycle de la commande.');
            }

            $statement = $this->pdo->prepare(
                "SELECT Statut_Livraison FROM Logistique WHERE Id_Vente = ? AND Statut_Livraison IN ('expediee', 'livree') LIMIT 1"
            );
            $statement->execute([$invoice['Id_Vente']]);
            if ($statement->fetchColumn() !== false) {
                throw new InvalidArgumentException('La marchandise est déjà expédiée ou livrée : annulation impossible.');
            }

            // Retour en stock des quantités vendues (lignes relationnelles de la vente).
            $statement = $this->pdo->prepare(
                'SELECT Id_Produit, Quantite FROM Ligne_Vente WHERE Id_Vente = ? AND Id_Produit IS NOT NULL'
            );
            $statement->execute([$invoice['Id_Vente']]);
            $restock = $this->pdo->prepare(
                'UPDATE Produit SET Quantite_En_Stock = Quantite_En_Stock + ? WHERE Id_Produit = ? AND Id_Entreprise = ?'
            );
            $units = 0;
            foreach ($statement->fetchAll() as $line) {
                $restock->execute([(int) $line['Quantite'], (int) $line['Id_Produit'], $enterpriseId]);
                $units += (int) $line['Quantite'];
            }

            $this->pdo->prepare("UPDATE Facture SET Statut_Paiement = 'annulee' WHERE Id_Facture = ?")
                ->execute([$invoiceId]);
            $this->pdo->prepare("UPDATE Logistique SET Statut_Livraison = 'annulee' WHERE Id_Vente = ? AND Id_Entreprise = ?")
                ->execute([$invoice['Id_Vente'], $enterpriseId]);

            $this->pdo->commit();

            return $units;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
