<?php

declare(strict_types=1);

namespace Tests\Includes;

use PHPUnit\Framework\TestCase;
use Validator;

final class ValidatorTest extends TestCase
{
    public function testPassesWhenAllRulesSatisfied(): void
    {
        $v = Validator::make(['name' => 'Alex', 'age' => '30'])
            ->required('name')
            ->numeric('age')
            ->min('age', 18);

        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
        $this->assertSame([], $v->errors());
    }

    public function testRequiredFailsOnMissingField(): void
    {
        $v = Validator::make([])->required('email', 'Email');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('email', $v->errors());
        $this->assertStringContainsString('Email', $v->errors()['email']);
    }

    public function testRequiredFailsOnWhitespaceOnlyValue(): void
    {
        $v = Validator::make(['name' => '   '])->required('name');
        $this->assertTrue($v->fails());
    }

    public function testNumericRejectsNonNumericString(): void
    {
        $v = Validator::make(['qty' => 'abc'])->numeric('qty');
        $this->assertTrue($v->fails());
    }

    public function testNumericAcceptsEmptyValueDelegatingToRequired(): void
    {
        // numeric() ne doit pas doubler l'erreur de required() sur un champ vide —
        // chaque règle a une responsabilité unique.
        $v = Validator::make(['qty' => ''])->numeric('qty');
        $this->assertTrue($v->passes());
    }

    public function testMinRejectsValueBelowThreshold(): void
    {
        $v = Validator::make(['age' => '15'])->min('age', 18, 'Âge');
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('18', $v->errors()['age']);
    }

    public function testMaxRejectsValueAboveThreshold(): void
    {
        $v = Validator::make(['qty' => '999'])->max('qty', 100);
        $this->assertTrue($v->fails());
    }

    public function testMaxLengthRejectsTooLongString(): void
    {
        $v = Validator::make(['note' => str_repeat('a', 51)])->maxLength('note', 50);
        $this->assertTrue($v->fails());
    }

    public function testMaxLengthCountsMultibyteCharactersCorrectly(): void
    {
        // mb_strlen() attendu, pas strlen() : "café" doit compter 4 caractères, pas 5 octets.
        $v = Validator::make(['note' => 'café'])->maxLength('note', 4);
        $this->assertTrue($v->passes());
    }

    public function testEmailRejectsInvalidAddress(): void
    {
        $v = Validator::make(['email' => 'not-an-email'])->email('email');
        $this->assertTrue($v->fails());
    }

    public function testEmailAcceptsValidAddress(): void
    {
        $v = Validator::make(['email' => 'alex@techvision.tg'])->email('email');
        $this->assertTrue($v->passes());
    }

    public function testInRejectsValueOutsideAllowedList(): void
    {
        $v = Validator::make(['role' => 'superadmin'])->in('role', ['proprio', 'vendeur', 'livreur']);
        $this->assertTrue($v->fails());
    }

    public function testInAcceptsValueInsideAllowedList(): void
    {
        $v = Validator::make(['role' => 'vendeur'])->in('role', ['proprio', 'vendeur', 'livreur']);
        $this->assertTrue($v->passes());
    }

    public function testInUsesStrictComparison(): void
    {
        // in_array(..., true) attendu : "0" ne doit pas matcher un tableau [0, 1] par
        // coercition de type — sinon une valeur de rôle vide passerait par erreur.
        $v = Validator::make(['level' => '0'])->in('level', [0, 1]);
        $this->assertTrue($v->fails());
    }

    public function testFirstErrorReturnsEarliestRegisteredMessage(): void
    {
        $v = Validator::make(['name' => '', 'email' => 'bad'])
            ->required('name')
            ->email('email');

        $this->assertSame($v->errors()['name'], $v->firstError());
    }

    public function testFirstErrorReturnsEmptyStringWhenNoErrors(): void
    {
        $v = Validator::make(['name' => 'Alex'])->required('name');
        $this->assertSame('', $v->firstError());
    }

    public function testGetReturnsStoredValueOrDefault(): void
    {
        $v = Validator::make(['name' => 'Alex']);
        $this->assertSame('Alex', $v->get('name'));
        $this->assertSame('fallback', $v->get('missing', 'fallback'));
        $this->assertNull($v->get('missing'));
    }

    public function testChainAccumulatesMultipleErrorsAcrossFields(): void
    {
        $v = Validator::make(['name' => '', 'age' => '-5', 'email' => 'bad'])
            ->required('name')
            ->numeric('age')
            ->min('age', 0)
            ->email('email');

        $this->assertCount(3, $v->errors());
    }
}
