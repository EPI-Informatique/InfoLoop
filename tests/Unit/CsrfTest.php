<?php

/**
 * Unit tests for CSRF token generation/verification and the Request
 * integration (POST field and X-CSRF-Token header).
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\App;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Request;
use InfoLoop\Core\Session;

final class CsrfTest extends \TestCase
{
    private function app(): App
    {
        @session_start();
        return new App([
            'app' => ['timezone' => 'UTC'],
            'session' => ['name' => 'iltest', 'lifetime' => 3600],
            'log' => ['dir' => sys_get_temp_dir(), 'level' => 'error'],
        ]);
    }

    public function testTokenIsStablePerSession(): void
    {
        $app = $this->app();
        $csrf = new Csrf($app);
        $t1 = $csrf->token();
        $t2 = $csrf->token();
        $this->assertSame($t1, $t2);
        $this->assertMatchesRegex('/^[0-9a-f]{64}$/', $t1);
    }

    public function testVerify(): void
    {
        $app = $this->app();
        $csrf = new Csrf($app);
        $token = $csrf->token();
        $this->assertTrue($csrf->verify($token));
        $this->assertFalse($csrf->verify(null));
        $this->assertFalse($csrf->verify(''));
        $this->assertFalse($csrf->verify(str_repeat('0', 64)));
    }

    public function testVerifyRequestFromPostAndHeader(): void
    {
        $app = $this->app();
        $csrf = new Csrf($app);
        $token = $csrf->token();

        $postReq = new Request('POST', '/', [], ['_csrf' => $token], [], [], [], '');
        $this->assertTrue($csrf->verifyRequest($postReq));

        $headerReq = new Request('POST', '/', [], [], [], [], ['HTTP_X_CSRF_TOKEN' => $token], '');
        $this->assertTrue($csrf->verifyRequest($headerReq));

        $badReq = new Request('POST', '/', [], ['_csrf' => 'wrong'], [], [], [], '');
        $this->assertFalse($csrf->verifyRequest($badReq));
    }

    public function testFieldRendersHiddenInput(): void
    {
        $app = $this->app();
        $csrf = new Csrf($app);
        $field = $csrf->field();
        $this->assertStringContains('type="hidden"', $field);
        $this->assertStringContains('name="_csrf"', $field);
        $this->assertStringContains($csrf->token(), $field);
    }
}
