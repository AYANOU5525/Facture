<?php

declare(strict_types=1);

namespace Tests\Includes;

use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // includes/csrf.php stocke le pool dans $_SESSION — session déjà démarrée par le
        // bootstrap (require de csrf.php). On repart d'un pool vide à chaque test.
        $_SESSION['csrf_tokens'] = [];
    }

    public function testJetonCsrfGeneratesUniqueTokens(): void
    {
        $a = jetonCsrf();
        $b = jetonCsrf();

        $this->assertNotSame($a, $b);
        $this->assertSame(64, strlen($a)); // bin2hex(random_bytes(32)) => 64 caractères hex
    }

    public function testVerifierCsrfAcceptsAKnownToken(): void
    {
        $token = jetonCsrf();
        $this->assertTrue(verifierCsrf($token));
    }

    public function testVerifierCsrfRejectsAnUnknownToken(): void
    {
        jetonCsrf();
        $this->assertFalse(verifierCsrf('does-not-exist-in-the-pool'));
    }

    public function testVerifierCsrfIsSingleUse(): void
    {
        $token = jetonCsrf();

        $this->assertTrue(verifierCsrf($token), 'Le token doit être valide la première fois.');
        $this->assertFalse(verifierCsrf($token), 'Le même token rejoué doit être refusé (usage unique).');
    }

    public function testVerifierCsrfRejectsEmptyToken(): void
    {
        $this->assertFalse(verifierCsrf(''));
    }

    public function testPoolIsCappedAtThirtyTokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 35; $i++) {
            $tokens[] = jetonCsrf();
        }

        $this->assertCount(30, $_SESSION['csrf_tokens']);

        // Les 5 premiers tokens générés ont été évincés du pool (FIFO) : plus valides.
        $this->assertFalse(verifierCsrf($tokens[0]));
        // Le dernier token généré doit lui être encore valide.
        $this->assertTrue(verifierCsrf($tokens[34]));
    }

    public function testConsumingOneTokenDoesNotInvalidateOthers(): void
    {
        $a = jetonCsrf();
        $b = jetonCsrf();
        $c = jetonCsrf();

        $this->assertTrue(verifierCsrf($b));
        $this->assertTrue(verifierCsrf($a));
        $this->assertTrue(verifierCsrf($c));
    }
}
