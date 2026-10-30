<?php

/**
 * Unit tests for the Request value object accessors.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\Request;

final class RequestTest extends \TestCase
{
    private function make(array $query = [], array $post = [], string $method = 'GET', string $uri = '/x', string $body = ''): Request
    {
        return new Request($method, $uri, $query, $post, [], [], ['REMOTE_ADDR' => '10.0.0.5'], $body);
    }

    public function testPathParsing(): void
    {
        $this->assertSame('/a/b', $this->make([], [], 'GET', '/a/b?x=1')->path());
        $this->assertSame('/', $this->make([], [], 'GET', '/')->path());
    }

    public function testTypedAccessors(): void
    {
        $r = $this->make(['a' => '1', 'b' => 'x'], ['c' => 'hello'], 'POST', '/');
        $this->assertSame(1, $r->int('a'));
        $this->assertSame('x', $r->string('b'));
        $this->assertSame('hello', $r->string('c'));
        $this->assertSame(0, $r->int('missing', 0));
        $this->assertSame('d', $r->string('missing', 'd'));
    }

    public function testIntClamping(): void
    {
        $r = $this->make(['n' => '999']);
        $this->assertSame(10, $r->int('n', 0, 0, 10));
        $r2 = $this->make(['n' => '-5']);
        $this->assertSame(0, $r2->int('n', 0, 0, 10));
    }

    public function testBool(): void
    {
        $this->assertTrue($this->make(['f' => '1'])->bool('f'));
        $this->assertTrue($this->make(['f' => 'on'])->bool('f'));
        $this->assertFalse($this->make(['f' => '0'])->bool('f'));
        $this->assertTrue($this->make([])->bool('f', true));
    }

    public function testArrayOfStringsFiltersEmpty(): void
    {
        $r = new Request('POST', '/', ['items' => [' a ', '', 'b', 5]], [], [], [], [], '');
        $this->assertSame(['a', 'b', '5'], $r->arrayOfStrings('items'));
        $this->assertSame([], $r->arrayOfStrings('nope'));
    }

    public function testJsonBody(): void
    {
        $r = $this->make([], [], 'POST', '/', '{"page_id": 7, "event": "view"}');
        $this->assertSame(['page_id' => 7, 'event' => 'view'], $r->json());
        $this->assertSame([], $this->make([], [], 'POST', '/', 'not json')->json());
    }

    public function testIpValidation(): void
    {
        $this->assertSame('10.0.0.5', $this->make()->ip());
        $bad = new Request('GET', '/', [], [], [], [], ['REMOTE_ADDR' => 'not-an-ip'], '');
        $this->assertSame('0.0.0.0', $bad->ip());
    }

    public function testMethodHelpers(): void
    {
        $r = $this->make([], [], 'POST', '/');
        $this->assertTrue($r->isMethod('post'));
        $this->assertFalse($r->isMethod('GET'));
    }
}
