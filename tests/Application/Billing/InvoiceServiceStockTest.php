<?php

declare(strict_types=1);

namespace Tests\Application\Billing;

use App\Application\Billing\InvoiceService;
use App\Infrastructure\Persistence\InvoiceRepository;
use RuntimeException;
use Tests\DatabaseTestCase;

/**
 * Vérifie l'invariant le plus critique de l'application : impossible de vendre plus
 * d'unités qu'il n'y en a en stock. createDirectSale() gère elle-même sa transaction
 * (begin/commit/rollback en interne) — on ne peut donc pas l'envelopper dans une
 * transaction de test qu'on annulerait après coup (PDO ne supporte pas les
 * transactions imbriquées). On teste à la place le chemin de rejet : la vente doit
 * échouer AVANT tout commit dès qu'une ligne dépasse le stock disponible, donc aucune
 * écriture ne doit jamais atteindre la base — zéro résidu à nettoyer, par construction.
 */
final class InvoiceServiceStockTest extends DatabaseTestCase
{
    private const PRODUCT_ID = 3; // Souris sans fil, Id_Entreprise 1 — stock fixture connu
    private const ENTERPRISE_ID = 1;

    public function testOversellIsRejectedAndStockIsUnchanged(): void
    {
        $pdo = $this->getPdo();
        $service = new InvoiceService($pdo, new InvoiceRepository($pdo));

        $stockBefore = (int) $pdo
            ->query('SELECT Quantite_En_Stock FROM Produit WHERE Id_Produit = ' . self::PRODUCT_ID)
            ->fetchColumn();

        $this->assertGreaterThan(0, $stockBefore, 'Précondition : le produit fixture doit avoir du stock.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Stock insuffisant/');

        try {
            $service->createDirectSale(
                'PHPUNIT-TEST-oversell',
                'phpunit',
                [[
                    'produit' => self::PRODUCT_ID,
                    'qte_unite' => $stockBefore + 1_000_000,
                    'qte_carton' => 0,
                ]],
                self::ENTERPRISE_ID
            );
        } finally {
            $stockAfter = (int) $pdo
                ->query('SELECT Quantite_En_Stock FROM Produit WHERE Id_Produit = ' . self::PRODUCT_ID)
                ->fetchColumn();
            $this->assertSame($stockBefore, $stockAfter, 'Le stock ne doit pas bouger quand la vente échoue.');

            // Filet de sécurité si jamais le rejet ne se produit pas comme attendu et
            // qu'une vente a été committée sous ce libellé — ne laisse rien dans la démo.
            $pdo->exec("DELETE FROM Vente WHERE Nom_Client = 'PHPUNIT-TEST-oversell'");
        }
    }

    public function testUnknownProductIsRejected(): void
    {
        $pdo = $this->getPdo();
        $service = new InvoiceService($pdo, new InvoiceRepository($pdo));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/introuvable/');

        try {
            $service->createDirectSale(
                'PHPUNIT-TEST-unknown-product',
                'phpunit',
                [['produit' => 999999, 'qte_unite' => 1, 'qte_carton' => 0]],
                self::ENTERPRISE_ID
            );
        } finally {
            $pdo->exec("DELETE FROM Vente WHERE Nom_Client = 'PHPUNIT-TEST-unknown-product'");
        }
    }

    public function testEmptyClientNameIsRejectedBeforeTouchingTheDatabase(): void
    {
        $pdo = $this->getPdo();
        $service = new InvoiceService($pdo, new InvoiceRepository($pdo));

        $this->expectException(\InvalidArgumentException::class);

        $service->createDirectSale(
            '   ',
            'phpunit',
            [['produit' => self::PRODUCT_ID, 'qte_unite' => 1, 'qte_carton' => 0]],
            self::ENTERPRISE_ID
        );
    }
}
