<?php

declare(strict_types=1);

namespace App\Application\B2B;

use App\Infrastructure\Persistence\DocumentNumberRepository;
use App\Infrastructure\Persistence\OrderRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class OrderService
{
    public function __construct(
        private PDO $pdo,
        private OrderRepository $repository
    ) {
    }

    public function transitionForSeller(
        int $orderId,
        int $enterpriseId,
        string $expectedStatus,
        string $newStatus,
        ?string $message = null
    ): array {
        $this->pdo->beginTransaction();

        try {
            $order = $this->repository->findForSeller($orderId, $enterpriseId, $expectedStatus);
            if (!$order) {
                throw new RuntimeException("Commande introuvable ou statut incorrect (attendu : {$expectedStatus}).");
            }

            $this->repository->transition($orderId, $newStatus, $message);
            $this->repository->recordHistory($orderId, $expectedStatus, $newStatus, $message, $enterpriseId);
            $this->pdo->commit();

            return $order;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function refuse(int $orderId, int $enterpriseId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Veuillez indiquer un motif de refus.');
        }

        $order = $this->transitionForSeller($orderId, $enterpriseId, 'en_attente', 'refusee', $reason);
        $this->repository->recalculateSellerReliability($enterpriseId);

        return $order;
    }

    /**
     * Refuse automatiquement les commandes urgentes dont le délai de réponse est dépassé
     * sans que le vendeur ait validé. Appelé au chargement des pages B2B (pas de tâche cron
     * dans le projet) : l'expiration est donc constatée au plus tard à la prochaine visite.
     *
     * @return array<int, array> commandes expirées, pour notifier acheteur et vendeur
     */
    public function expireOverdueUrgentOrders(): array
    {
        $this->pdo->beginTransaction();

        try {
            $expired = $this->repository->findExpiredUrgentForUpdate();
            foreach ($expired as $order) {
                $message = 'Délai de réponse dépassé : commande urgente refusée automatiquement.';
                $this->repository->transition((int) $order['Id_Commande_B2B'], 'refusee', $message);
                $this->repository->recordHistory((int) $order['Id_Commande_B2B'], 'en_attente', 'refusee', $message, (int) $order['Id_Entreprise_Vendeuse']);
            }
            foreach (array_unique(array_column($expired, 'Id_Entreprise_Vendeuse')) as $sellerId) {
                $this->repository->recalculateSellerReliability((int) $sellerId);
            }
            $this->pdo->commit();

            return $expired;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Livraison partielle — étape 1 (vendeur) : le vendeur propose, ligne par ligne, la
     * quantité qu'il peut fournir. Le stock correspondant est RÉSERVÉ tout de suite (déduit),
     * pour que l'acceptation de l'acheteur ne puisse pas échouer faute de stock ; il est rendu
     * si l'acheteur annule. La commande passe « a_confirmer ».
     *
     * @param array<int|string, mixed> $quantities Id_Ligne => quantité proposée (0 = article retiré)
     * @return array{order: array, summary: string}
     */
    public function proposePartial(int $orderId, int $sellerId, array $quantities, string $message = ''): array
    {
        $message = trim($message);
        $this->pdo->beginTransaction();

        try {
            $order = $this->repository->findForSeller($orderId, $sellerId, 'en_attente');
            if (!$order) {
                throw new RuntimeException('Commande introuvable ou déjà traitée.');
            }

            $lines = $this->repository->findLinesWithStockForUpdate($orderId);
            $proposed = [];
            $changes = [];
            $total = 0;
            foreach ($lines as $line) {
                $qty = filter_var($quantities[$line['Id_Ligne']] ?? null, FILTER_VALIDATE_INT);
                if ($qty === false || $qty < 0 || $qty > (int) $line['Quantite']) {
                    throw new InvalidArgumentException("Quantité invalide pour {$line['Nom_Produit']} (entre 0 et {$line['Quantite']}).");
                }
                if ($qty > (int) $line['Quantite_En_Stock']) {
                    throw new InvalidArgumentException("Stock insuffisant pour {$line['Nom_Produit']} : {$line['Quantite_En_Stock']} disponible(s).");
                }
                $proposed[(int) $line['Id_Ligne']] = $qty;
                $total += $qty;
                if ($qty !== (int) $line['Quantite']) {
                    $changes[] = "{$line['Nom_Produit']} : {$line['Quantite']} → {$qty}";
                }
            }

            if ($total === 0) {
                throw new InvalidArgumentException('Aucune quantité proposée : refusez plutôt la commande.');
            }
            if ($changes === []) {
                throw new InvalidArgumentException('Les quantités proposées sont celles de la commande : validez-la directement.');
            }

            foreach ($lines as $line) {
                $qty = $proposed[(int) $line['Id_Ligne']];
                $this->repository->setProposedQuantity((int) $line['Id_Ligne'], $qty);
                if ($qty > 0) {
                    $this->repository->adjustStock((int) $line['Id_Produit'], -$qty);
                }
            }

            $summary = implode(' ; ', $changes);
            $note = 'Proposition partielle : ' . $summary . ($message !== '' ? '. ' . $message : '');
            $this->repository->transition($orderId, 'a_confirmer', $message !== '' ? $message : null);
            $this->repository->recordHistory($orderId, 'en_attente', 'a_confirmer', $note, $sellerId);
            $this->pdo->commit();

            return ['order' => $order, 'summary' => $summary];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Livraison partielle — étape 2 (acheteur accepte) : les quantités proposées remplacent la
     * commande (total recalculé par trigger) et la commande est validée. Le stock a déjà été
     * réservé à la proposition.
     *
     * $completeLater : « je prends ce qu'il y a, complétez-moi le reste plus tard » — les
     * quantités manquantes deviennent une NOUVELLE commande en attente chez le même vendeur
     * (reliquat, au même prix unitaire, reliée par Id_Commande_Origine), qu'il validera quand
     * son stock le permettra.
     *
     * @return array{order: array, backorder: ?array{id:int, number:string, summary:string}}
     */
    public function acceptProposal(int $orderId, int $buyerId, bool $completeLater = false): array
    {
        $this->pdo->beginTransaction();

        try {
            $order = $this->repository->findForBuyer($orderId, $buyerId, ['a_confirmer']);
            if (!$order) {
                throw new RuntimeException('Aucune proposition en attente pour cette commande.');
            }

            // Manques à reprendre AVANT d'appliquer la proposition (qui écrase les quantités).
            $missing = [];
            foreach ($this->repository->findLinesWithStockForUpdate($orderId) as $line) {
                $rest = (int) $line['Quantite'] - (int) $line['Quantite_Proposee'];
                if ($rest > 0) {
                    $missing[] = $line + ['Reste' => $rest];
                }
            }

            $this->repository->applyProposal($orderId);
            $this->repository->markValidated($orderId);

            $backorder = null;
            if ($completeLater && $missing !== []) {
                $number = (new DocumentNumberRepository($this->pdo))->next('Commande_B2B', 'CMD');
                $backorderId = $this->repository->create([
                    'number' => $number, 'buyer_id' => $buyerId, 'seller_id' => (int) $order['Id_Entreprise_Vendeuse'],
                    'total' => 0, 'urgent' => 0, 'deadline_minutes' => 120, 'deadline' => null,
                    'mode' => $order['Mode_Retrait'] ?? 'livraison', 'pickup_address' => $order['Adresse_Retrait'] ?? null,
                    'delivery_address' => $order['Adresse_Livraison'] ?? null,
                    'delivery_lat' => $order['Latitude_Livraison'] ?? null, 'delivery_lng' => $order['Longitude_Livraison'] ?? null,
                    'origin_id' => $orderId,
                ]);
                $parts = [];
                foreach ($missing as $line) {
                    $this->repository->createLine($backorderId, [
                        'product_id' => (int) $line['Id_Produit'], 'name' => $line['Nom_Produit'],
                        'quantity' => $line['Reste'], 'unit_price' => $line['Prix_Unitaire'],
                        'subtotal' => $line['Reste'] * (float) $line['Prix_Unitaire'],
                    ]);
                    $parts[] = "{$line['Reste']} × {$line['Nom_Produit']}";
                }
                $summary = implode(', ', $parts);
                $this->repository->recordHistory($backorderId, 'en_attente', 'en_attente', "Reliquat de la commande {$order['Numero_Commande']} : $summary", $buyerId);
                $backorder = ['id' => $backorderId, 'number' => $number, 'summary' => $summary];
            }

            $note = "Proposition partielle acceptée par l'acheteur"
                . ($backorder ? ", reste à compléter : commande {$backorder['number']}" : '');
            $this->repository->recordHistory($orderId, 'a_confirmer', 'validee', $note, $buyerId);
            $this->pdo->commit();

            return ['order' => $order, 'backorder' => $backorder];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Annulation par l'acheteur, tant que rien n'est validé : commande en attente, ou
     * proposition partielle qu'il décline (le stock réservé est alors rendu au vendeur).
     * Statut « annulee » : ce n'est pas un refus du vendeur, son score n'est pas affecté.
     */
    public function cancelByBuyer(int $orderId, int $buyerId, string $reason = ''): array
    {
        $this->pdo->beginTransaction();

        try {
            $order = $this->repository->findForBuyer($orderId, $buyerId, ['en_attente', 'a_confirmer']);
            if (!$order) {
                throw new RuntimeException('Cette commande ne peut plus être annulée.');
            }

            if ($order['Statut'] === 'a_confirmer') {
                foreach ($this->repository->findLinesWithStockForUpdate($orderId) as $line) {
                    if ((int) $line['Quantite_Proposee'] > 0) {
                        $this->repository->adjustStock((int) $line['Id_Produit'], (int) $line['Quantite_Proposee']);
                    }
                    $this->repository->setProposedQuantity((int) $line['Id_Ligne'], null);
                }
            }

            $note = "Annulée par l'acheteur" . (trim($reason) !== '' ? ' : ' . trim($reason) : '');
            $this->repository->transition($orderId, 'annulee', $note);
            $this->repository->recordHistory($orderId, $order['Statut'], 'annulee', $note, $buyerId);
            $this->pdo->commit();

            return $order;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
