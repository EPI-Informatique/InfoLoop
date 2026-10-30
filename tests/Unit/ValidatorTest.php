<?php

/**
 * Unit tests for the Validator.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\Validator;

final class ValidatorTest extends \TestCase
{
    public function testRequired(): void
    {
        $v = Validator::make(['name' => ''])->required('name');
        $this->assertFalse($v->valid());
        $this->assertStringContains('required', $v->firstError());
        $this->assertTrue(Validator::make(['name' => 'x'])->required('name')->valid());
    }

    public function testStringLength(): void
    {
        $this->assertFalse(Validator::make(['s' => 'ab'])->string('s', 3, 10)->valid());
        $this->assertTrue(Validator::make(['s' => 'abc'])->string('s', 3, 10)->valid());
        $this->assertFalse(Validator::make(['s' => str_repeat('x', 11)])->string('s', 3, 10)->valid());
    }

    public function testEmail(): void
    {
        $this->assertFalse(Validator::make(['e' => 'not-an-email'])->email('e')->valid());
        $this->assertTrue(Validator::make(['e' => 'a@b.co'])->email('e')->valid());
        $this->assertTrue(Validator::make([])->email('e')->valid(), 'optional when absent');
    }

    public function testIntRange(): void
    {
        $this->assertFalse(Validator::make(['n' => 'abc'])->int('n')->valid());
        $this->assertFalse(Validator::make(['n' => 5])->int('n', 10, 20)->valid());
        $this->assertTrue(Validator::make(['n' => 15])->int('n', 10, 20)->valid());
    }

    public function testIn(): void
    {
        $this->assertFalse(Validator::make(['r' => 'hacker'])->in('r', ['admin', 'editor'])->valid());
        $this->assertTrue(Validator::make(['r' => 'editor'])->in('r', ['admin', 'editor'])->valid());
    }

    public function testSanitizedTrims(): void
    {
        $v = Validator::make(['a' => '  hi  ', 'b' => 5]);
        $this->assertSame(['a' => 'hi', 'b' => 5], $v->sanitized());
    }

    public function testErrorsGroupedByField(): void
    {
        $v = Validator::make([])->required('a')->required('b');
        $errors = $v->errors();
        $this->assertSame(2, count($errors));
        $this->assertTrue(isset($errors['a'], $errors['b']));
    }
}
