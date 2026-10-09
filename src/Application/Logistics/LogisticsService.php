<?php

declare(strict_types=1);

namespace App\Application\Logistics;

use App\Application\B2B\ShipmentService;
use App\Infrastructure\Persistence\DocumentNumberRepository;
use App\Infrastructure\Persistence\LogisticsRepository;
use App\Infrastructure\Persistence\OrderRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Cycle de vie d'une livraison (fiche Logistique) :
 *
 *   traitement (à planifier) ──ship()──► expediee (en route) ──2 confirmations──► livree
 *        └──cancel()──► annulee
 *
 * - prepareForOrder() : clic « Expédier » sur une commande B2B prête. Crée la fiche avec un
 *   N° de suivi attribué par le système ; rien n'est encore validé ni facturé.
 * - ship() : transporteur (livreur de l'équipe ou transporteur externe) et date prévue
 *   obligatoires. Pour une commande B2B, la facture est générée et la commande passe
 *   « expediee » dans la même transaction.
 * - confirmByCarrier() / confirmByBuyer() : dans n'importe quel ordre. La livraison (et la
 *   commande B2B) n'est « livree » qu'une fois les deux confirmations reçues. Une vente
 *   directe n'a pas d'acheteur connecté : la confirmation du livreur suffit.
 */
final class LogisticsService
{
    private OrderRepository $orders;
    private ShipmentService $shipments;

    public function __construct(
        private PDO $pdo,
        private LogisticsRepository $repository
    ) {
        $this->orders = new OrderRepository($pdo);
        $this->shipments = new ShipmentService($pdo, $this->orders);
    }

    public function find(int $logisticsId, int $enterpriseId): ?array
    {
        return $this->repository->findForEnterprise($logisticsId, $enterpriseId);
    }

    /** Membres de l'équipe proposés comme livreur (livreurs puis propriétaires). */
    public function carriers(int $enterpriseId): array
    {
        return $this->repository->findCarriersForEnterprise($enterpriseId);
    }

    /**
     * Ouvre (ou rouvre) la fiche de livraison d'une commande B2B prête à partir.
     *
     * @return int Id_Logistique
     */
    public function prepareForOrder(int $orderId, int $sellerId): int
    {
        return $this->transaction(function () use ($orderId, $sellerId): int {
            $order = $this->orders->findReadyForShipment($orderId, $sellerId);
            if (!$order) {
                throw new RuntimeException('Commande introuvable ou pas encore prête à expédier.');
            }
            if (($order['Mode_Retrait'] ?? 'livraison') === 'retrait_place') {
                throw new InvalidArgumentException('Commande en retrait sur place : aucune livraison à organiser.');
            }

            $existing = $this->repository->findActiveForOrder($orderId);
            if ($existing) {
                if (trim((string) $existing['Numero_Suivi']) === '') {
                    $this->repository->setTrackingNumber((int) $existing['Id_Logistique'], $this->nextTrackingNumber());
                }
                return (int) $existing['Id_Logistique'];
            }

            return $this->repository->createForOrder(
                $orderId,
                $sellerId,
                $this->nextTrackingNumber(),
                $order['Adresse_Livraison'] ?? null,
                isset($order['Latitude_Livraison']) ? (float) $order['Latitude_Livraison'] : null,
                isset($order['Longitude_Livraison']) ? (float) $order['Longitude_Livraison'] : null
            );
        });
    }

    /**
     * Valide l'expédition d'une fiche « à planifier ».
     *
     * @param array{carrier_id?: ?int, carrier_name?: string, date_prevue?: ?string, notes?: string,
     *              address?: ?string, latitude?: ?float, longitude?: ?float} $data
     * @return array{tracking: string, carrier: string, date_prevue: string, order: ?array, invoice_number: ?string}
     */
    public function ship(int $logisticsId, int $enterpriseId, array $data, string $userName = '', int $userId = 0): array
    {
        $datePrevue = $this->validatePlannedDate($data['date_prevue'] ?? null);
        [$carrierName, $carrierId] = $this->resolveCarrier($enterpriseId, $data);

        return $this->transaction(function () use ($logisticsId, $enterpriseId, $data, $userName, $userId, $datePrevue, $carrierName, $carrierId): array {
            $logistics = $this->repository->findForUpdate($logisticsId, $enterpriseId);
            if (!$logistics) {
                throw new RuntimeException('Livraison introuvable.');
            }
            if ($logistics['Statut_Livraison'] !== 'traitement') {
                throw new RuntimeException('Cette livraison a déjà été expédiée ou annulée.');
            }

            $tracking = trim((string) $logistics['Numero_Suivi']);
            if ($tracking === '') {
                $tracking = $this->nextTrackingNumber();
                $this->repository->setTrackingNumber($logisticsId, $tracking);
            }

            $address = trim((string) ($data['address'] ?? '')) ?: $logistics['Adresse_Livraison'];
            $latitude = $data['latitude'] ?? $logistics['Adresse_Livraison_Lat'];
            $longitude = $data['longitude'] ?? $logistics['Adresse_Livraison_Lng'];

            $order = null;
            $invoice = null;
            if ($logistics['Id_Commande_B2B']) {
                $order = $this->orders->findReadyForShipment((int) $logistics['Id_Commande_B2B'], $enterpriseId);
                if (!$order) {
                    throw new RuntimeException("La commande liée n'est plus prête à expédier.");
                }
                // L'adresse d'une commande B2B est celle choisie par l'acheteur : non modifiable ici.
                $address = $logistics['Adresse_Livraison'];
                $latitude = $logistics['Adresse_Livraison_Lat'];
                $longitude = $logistics['Adresse_Livraison_Lng'];
                $invoice = $this->shipments->invoiceAndMarkShipped(
                    $order,
                    $enterpriseId,
                    $userName,
                    $userId,
                    "Expédiée via {$carrierName} (N° de suivi : {$tracking})."
                );
            }

            $this->repository->markShipped($logisticsId, [
                'carrier' => $carrierName,
                'carrier_id' => $carrierId,
                'date_prevue' => $datePrevue,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'address' => $address,
                'latitude' => $latitude !== null && $latitude !== '' ? (float) $latitude : null,
                'longitude' => $longitude !== null && $longitude !== '' ? (float) $longitude : null,
                'sale_id' => $invoice['sale_id'] ?? null,
                'invoice_id' => $invoice['invoice_id'] ?? null,
            ]);

            return [
                'tracking' => $tracking,
                'carrier' => $carrierName,
                'date_prevue' => $datePrevue,
                'order' => $order,
                'invoice_number' => $invoice['number'] ?? null,
            ];
        });
    }

    /**
     * Le livreur (ou le propriétaire, au nom d'un transporteur externe) confirme la remise.
     *
     * @return array{finalized: bool, order: ?array, logistics: array}
     */
    public function confirmByCarrier(int $logisticsId, int $enterpriseId, int $userId, bool $isOwner, string $note = ''): array
    {
        return $this->transaction(function () use ($logisticsId, $enterpriseId, $userId, $isOwner, $note): array {
            $logistics = $this->repository->findForUpdate($logisticsId, $enterpriseId);
            if (!$logistics) {
                throw new RuntimeException('Livraison introuvable.');
            }
            if (!$isOwner && (int) $logistics['Id_Livreur'] !== $userId) {
                throw new RuntimeException("Cette livraison ne vous est pas assignée.");
            }
            if ($logistics['Statut_Livraison'] !== 'expediee') {
                throw new RuntimeException('Seule une livraison en route peut être confirmée.');
            }
            if ($logistics['Date_Confirmation_Livreur'] !== null) {
                throw new RuntimeException('La remise a déjà été confirmée.');
            }

            $this->repository->confirmByCarrier($logisticsId, trim($note));

            if (!$logistics['Id_Commande_B2B']) {
                $this->repository->markDelivered($logisticsId);
                return ['finalized' => true, 'order' => null, 'logistics' => $logistics];
            }

            $order = $this->orders->findForUpdate((int) $logistics['Id_Commande_B2B']);
            if (!$order || $order['Statut'] !== 'expediee') {
                throw new RuntimeException("La commande liée n'est pas en cours de livraison.");
            }

            if ($logistics['Date_Confirmation_Acheteur'] !== null) {
                $this->complete($order, $logisticsId, $enterpriseId, 'Remise confirmée par le livreur : livraison terminée.');
                return ['finalized' => true, 'order' => $order, 'logistics' => $logistics];
            }

            $this->orders->recordHistory(
                (int) $order['Id_Commande_B2B'],
                'expediee',
                'expediee',
                "Remise confirmée par le livreur, en attente de la confirmation de l'acheteur.",
                $enterpriseId
            );
            return ['finalized' => false, 'order' => $order, 'logistics' => $logistics];
        });
    }

    /**
     * L'acheteur confirme la réception d'une commande B2B expédiée.
     *
     * @return array{finalized: bool, order: array, logistics: ?array}
     */
    public function confirmByBuyer(int $orderId, int $buyerId): array
    {
        return $this->transaction(function () use ($orderId, $buyerId): array {
            $order = $this->orders->findForBuyer($orderId, $buyerId, ['expediee']);
            if (!$order) {
                throw new RuntimeException('Commande introuvable ou pas encore expédiée.');
            }

            $logistics = $this->repository->findActiveForOrder($orderId);
            // Retrait sur place (ou logistique désactivée) : pas de livreur, l'acheteur clôt seul.
            if (!$logistics) {
                $this->complete($order, null, $buyerId, "Réception confirmée par l'acheteur.");
                return ['finalized' => true, 'order' => $order, 'logistics' => null];
            }
            if ($logistics['Date_Confirmation_Acheteur'] !== null) {
                throw new RuntimeException('Vous avez déjà confirmé la réception.');
            }

            $this->repository->confirmByBuyer((int) $logistics['Id_Logistique']);

            if ($logistics['Date_Confirmation_Livreur'] !== null) {
                $this->complete($order, (int) $logistics['Id_Logistique'], $buyerId, "Réception confirmée par l'acheteur : livraison terminée.");
                return ['finalized' => true, 'order' => $order, 'logistics' => $logistics];
            }

            $this->orders->recordHistory(
                $orderId,
                'expediee',
                'expediee',
                "Réception confirmée par l'acheteur, en attente de la confirmation du livreur.",
                $buyerId
            );
            return ['finalized' => false, 'order' => $order, 'logistics' => $logistics];
        });
    }

    /** Annule une livraison pas encore expédiée (la commande B2B reste « prête »). */
    public function cancel(int $logisticsId, int $enterpriseId): array
    {
        return $this->transaction(function () use ($logisticsId, $enterpriseId): array {
            $logistics = $this->repository->findForUpdate($logisticsId, $enterpriseId);
            if (!$logistics) {
                throw new RuntimeException('Livraison introuvable.');
            }
            if ($logistics['Statut_Livraison'] !== 'traitement') {
                throw new RuntimeException('Seule une livraison pas encore expédiée peut être annulée.');
            }
            $this->repository->cancel($logisticsId);

            return $logistics;
        });
    }

    /** Clôture : fiche « livree », commande « livree », score de fiabilité du vendeur recalculé. */
    private function complete(array $order, ?int $logisticsId, int $actorId, string $note): void
    {
        if ($logisticsId !== null) {
            $this->repository->markDelivered($logisticsId);
        }
        $orderId = (int) $order['Id_Commande_B2B'];
        $this->orders->transition($orderId, 'livree');
        $this->orders->recordHistory($orderId, 'expediee', 'livree', $note, $actorId);
        $this->orders->recalculateSellerReliability((int) $order['Id_Entreprise_Vendeuse']);
    }

    private function validatePlannedDate(?string $value): string
    {
        $value = trim((string) $value);
        $date = $value !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('La date de livraison prévue est obligatoire.');
        }
        if ($value < date('Y-m-d')) {
            throw new InvalidArgumentException('La date de livraison prévue ne peut pas être dans le passé.');
        }

        return $value;
    }

    /** @return array{0: string, 1: ?int} [nom affiché, Id_Utilisateur du livreur de l'équipe] */
    private function resolveCarrier(int $enterpriseId, array $data): array
    {
        $carrierId = (int) ($data['carrier_id'] ?? 0);
        if ($carrierId > 0) {
            $carrier = $this->repository->findCarrier($carrierId, $enterpriseId);
            if (!$carrier) {
                throw new InvalidArgumentException("Ce livreur ne fait pas partie de votre équipe.");
            }
            return [$carrier['Nom_Utilisateur'], $carrierId];
        }

        $name = trim((string) ($data['carrier_name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Choisissez un livreur de l\'équipe ou indiquez le transporteur externe.');
        }
        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Nom du transporteur trop long (100 caractères maximum).');
        }

        return [$name, null];
    }

    private function nextTrackingNumber(): string
    {
        return (new DocumentNumberRepository($this->pdo))->next('Logistique', 'LIV');
    }

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    private function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
