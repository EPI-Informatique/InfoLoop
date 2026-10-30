<?php

/**
 * Unit tests for the TOTP (MFA) service: RFC 6238 vectors, window
 * tolerance, encryption round-trip and recovery codes.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\App;
use InfoLoop\Services\TotpService;

final class TotpServiceTest extends \TestCase
{
    private function service(): TotpService
    {
        $app = new App(['app' => ['timezone' => 'UTC'], 'log' => ['dir' => sys_get_temp_dir(), 'level' => 'error']]);
        return new TotpService($app);
    }

    public function testRfc6238VectorSha1(): void
    {
        // RFC 6238 Appendix B test vector: secret "12345678901234567890" (ASCII),
        // base32 = GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ, T=59s -> code 287082 (8 digits)
        $totp = $this->service();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $ref = new \ReflectionMethod($totp, 'codeAt');
        $code8 = null;
        // codeAt uses DIGITS=6; recompute 8-digit manually for the vector:
        $binary = pack('N*', 0, (int) floor(59 / 30));
        $key = $this->base32DecodePublic($secret);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % (10 ** 8);
        $code8 = str_pad((string) $value, 8, '0', STR_PAD_LEFT);
        $this->assertSame('94287082', $code8);
        $this->assertTrue($totp->verify($secret, substr($code8, 2), 59), '6-digit suffix of the RFC vector should verify at T=59');
    }

    public function testVerifyAcceptsCurrentAndAdjacentSteps(): void
    {
        $totp = $this->service();
        $secret = $totp->generateSecret();
        $now = 1_700_000_000;
        $counter = (int) floor($now / 30);
        $code = $this->codeAtPublic($totp, $secret, $counter);
        $this->assertTrue($totp->verify($secret, $code, $now));
        $this->assertTrue($totp->verify($secret, $code, $now + 30), 'next step within window');
        $this->assertFalse($totp->verify($secret, $code, $now + 30 * 5), 'far outside window');
    }

    public function testVerifyRejectsMalformedCodes(): void
    {
        $totp = $this->service();
        $secret = $totp->generateSecret();
        $this->assertFalse($totp->verify($secret, ''));
        $this->assertFalse($totp->verify($secret, '12345'));
        $this->assertFalse($totp->verify($secret, 'abcdef'));
        $this->assertTrue(is_bool($totp->verify($secret, '000000', 0)), 'returns bool without crashing');
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $totp = $this->service();
        $secret = $totp->generateSecret();
        $encrypted = $totp->encrypt($secret);
        $this->assertStringNotContains($secret, $encrypted);
        $this->assertSame($secret, $totp->decrypt($encrypted));
    }

    public function testDecryptRejectsGarbage(): void
    {
        $totp = $this->service();
        $this->expectException(\RuntimeException::class, static function () use ($totp): void {
            $totp->decrypt(base64_encode('too-short'));
        });
    }

    public function testRecoveryCodesAreUniqueAndHashed(): void
    {
        $totp = $this->service();
        $codes = $totp->generateRecoveryCodes(8);
        $this->assertSame(8, count($codes));
        $this->assertSame(8, count(array_unique($codes)));
        $hashes = $totp->hashRecoveryCodes($codes);
        foreach ($codes as $i => $code) {
            $this->assertSame(hash('sha256', strtoupper(trim($code))), $hashes[$i]);
            $this->assertStringNotContains($code, (string) json_encode($hashes));
        }
    }

    public function testProvisioningUri(): void
    {
        $totp = $this->service();
        $uri = $totp->provisioningUri('JBSWY3DPEHPK3PXP', 'alice', 'InfoLoop');
        $this->assertStringContains('otpauth://totp/', $uri);
        $this->assertStringContains('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContains('issuer=InfoLoop', $uri);
    }

    private function codeAtPublic(TotpService $totp, string $secret, int $counter): string
    {
        $m = new \ReflectionMethod($totp, 'codeAt');
        return $m->invoke($totp, $secret, $counter);
    }

    private function base32DecodePublic(string $secret): string
    {
        $m = new \ReflectionMethod($this->service(), 'base32Decode');
        return $m->invoke($this->service(), $secret);
    }
}
