<?php

/**
 * TOTP (RFC 6238) service for MFA.
 *
 * Secrets are encrypted at rest with libsodium using a key derived from the
 * APP_KEY environment value (or a per-install key stored in storage/).
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use function InfoLoop\Core\env;

final class TotpService
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const WINDOW = 1; // +/- 1 step tolerance

    private string $key;

    public function __construct(private readonly App $app)
    {
        $this->key = $this->deriveKey();
    }

    /**
     * Generates a new base32 secret.
     */
    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Builds an otpauth:// URI for QR-code provisioning.
     */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    /**
     * Verifies a user-supplied code against the secret.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }
        $counter = (int) floor(($timestamp ?? time()) / self::PERIOD);
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals($this->codeAt($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generates single-use recovery codes.
     *
     * @return string[] Plain codes (shown once); store hashes only.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return array_map(
            static fn (): string => strtoupper(bin2hex(random_bytes(5))),
            range(1, $count)
        );
    }

    /**
     * @param string[] $codes
     * @return string[] sha256 hashes to store.
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(static fn (string $c): string => hash('sha256', strtoupper(trim($c))), $codes);
    }

    public function encrypt(string $plainSecret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plainSecret, $nonce, $this->key);
        return base64_encode($nonce . $cipher);
    }

    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted TOTP secret.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        if ($plain === false) {
            throw new \RuntimeException('Could not decrypt TOTP secret.');
        }
        return $plain;
    }

    private function codeAt(string $secret, int $counter): string
    {
        $binary = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binary, $this->base32Decode($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin((int) strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr((int) bindec($byte));
            }
        }
        return $decoded;
    }

    private function deriveKey(): string
    {
        $envKey = (string) (env('APP_KEY') ?? '');
        if ($envKey !== '') {
            return sodium_crypto_generichash($envKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }
        $keyFile = INFOLOOP_STORAGE . '/app.key';
        if (is_file($keyFile)) {
            $hex = trim((string) file_get_contents($keyFile));
            if (strlen($hex) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2) {
                return sodium_hex2bin($hex);
            }
        }
        if (!is_dir(INFOLOOP_STORAGE)) {
            mkdir(INFOLOOP_STORAGE, 0750, true);
        }
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        file_put_contents($keyFile, sodium_bin2hex($key), LOCK_EX);
        chmod($keyFile, 0600);
        return $key;
    }
}
