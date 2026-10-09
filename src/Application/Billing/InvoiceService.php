<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Application\Inventory\PackagingConverter;
use App\Infrastructure\Persistence\ClientRepository;
use App\Infrastructure\Persistence\DocumentNumberRepository;
use App\Infrastructure\Persistence\InvoiceRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class InvoiceService
{
    private ClientRepository $clients;

    public function __construct(
        private PDO $pdo,
        private InvoiceRepository $repository
    ) {
        $this->clients = new ClientRepository($pdo);
    }

    public function availableProducts(int $enterpriseId): array
    {
        return $this->repository->availableProducts($enterpriseId);
    }

    public function createDirectSale(
        string $client,
        string $seller,
        array $items,
        int $enterpriseId,
        int $sellerId = 0,
        bool $withDelivery = true
    ): string {
        if (trim($client) === '' || $items === []) {
            throw new InvalidArgumentException('Client et articles sont obligatoires.');
        }

        $total = 0.0;
        $articles = [];
        $seen = [];
        $this->pdo->beginTransaction();

        try {
            foreach ($items as $item) {
                $productId = (int) ($item['produit'] ?? 0);
                $qteUnite = max(0, (int) ($item['qte_unite'] ?? 0));
                $qteCarton = max(0, (int) ($item['qte_carton'] ?? 0));

                if ($productId <= 0 || ($qteUnite <= 0 && $qteCarton <= 0)) {
                    continue;
                }
                if (isset($seen[$productId])) {
                    throw new InvalidArgumentException('Un produit ne peut apparaître qu’une seule fois.');
                }
                $seen[$productId] = true;

                $product = $this->repository->lockProduct($productId, $enterpriseId);
                if (!$product) {
                    throw new RuntimeException('Produit introuvable.');
                }

                // Coefficient carton lu uniquement depuis le produit verrouillé en base — jamais depuis le client,
                // pour empêcher toute falsification du facteur de conversion via une requête modifiée.
                $realQuantity = PackagingConverter::combinedUnits($product, $qteCarton, $qteUnite);

                if ((int) $product['Quantite_En_Stock'] < $realQuantity) {
                    throw new RuntimeException('Stock insuffisant pour ' . $product['Nom_Produit'] . '.');
                }

                $unitPrice = (float) $product['Prix_Unitaire_Produit'];
                $lineTotal = $unitPrice * $realQuantity;
                $total += $lineTotal;
                $articles[] = [
                    'id_produit' => $productId,
                    // Nom toujours relu en base : un libellé envoyé par le navigateur pourrait être falsifié.
                    'nom' => $product['Nom_Produit'],
                    'quantite_carton' => $qteCarton,
                    'quantite_unite' => $qteUnite,
                    'quantite_unites' => $realQuantity,
                    'prix_unitaire' => $unitPrice,
                    'total' => $lineTotal,
                ];
                $this->repository->decreaseStock($productId, $realQuantity);
            }

            if ($articles === []) {
                throw new InvalidArgumentException('Aucun article valide.');
            }

            $clientId = $this->clients->findOrCreate($client, $enterpriseId);

            $number = (new DocumentNumberRepository($this->pdo))->next('Vente', 'FAC');
            $saleId = $this->repository->createSale([
                'number' => $number,
                'client' => trim($client),
                'client_id' => $clientId,
                'seller' => $seller,
                'seller_id' => $sellerId,
                'articles' => json_encode($articles, JSON_UNESCAPED_UNICODE),
                'total' => $total,
                'enterprise_id' => $enterpriseId,
            ]);
            foreach ($articles as $article) {
                $this->repository->createSaleLine($saleId, $article);
            }
            $invoiceId = $this->repository->createInvoice($saleId, $number, $total, $enterpriseId, $clientId);
            // Pas de suivi logistique pour un retrait sur place (ni tant que la fonctionnalité
            // est désactivée, cf. includes/roles.php).
            if (FEATURE_LOGISTIQUE_ACTIVE && $withDelivery) {
                $this->repository->createLogistics(
                    $saleId,
                    $invoiceId,
                    $enterpriseId,
                    (new DocumentNumberRepository($this->pdo))->next('Logistique', 'LIV')
                );
            }
            $this->pdo->commit();

            return $number;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
