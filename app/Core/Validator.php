<?php

/**
 * Small fluent validator used by controllers and services.
 *
 * Each rule() call appends an error when the value fails; valid() tells
 * whether the whole input passed.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Validator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, ?string $label = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '') || $value === []) {
            $this->errors[$field][] = sprintf('%s is required.', $label ?? ucfirst(str_replace('_', ' ', $field)));
        }
        return $this;
    }

    public function string(string $field, int $min = 0, int $max = 1000, ?string $label = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null) {
            return $this;
        }
        if (!is_string($value) || mb_strlen($value) < $min || mb_strlen($value) > $max) {
            $this->errors[$field][] = sprintf('%s must be a string between %d and %d characters.', $label ?? $field, $min, $max);
        }
        return $this;
    }

    public function email(string $field): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = 'A valid email address is required.';
        }
        return $this;
    }

    public function int(string $field, ?int $min = null, ?int $max = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_numeric($value)) {
            $this->errors[$field][] = 'Must be an integer.';
            return $this;
        }
        $int = (int) $value;
        if (($min !== null && $int < $min) || ($max !== null && $int > $max)) {
            $this->errors[$field][] = sprintf('Must be between %s and %s.', $min ?? '-∞', $max ?? '+∞');
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && !in_array($value, $allowed, true)) {
            $this->errors[$field][] = 'Invalid value selected.';
        }
        return $this;
    }

    public function url(string $field, bool $allowEmpty = true): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $allowEmpty ? $this : $this->required($field);
        }
        $host = parse_url((string) $value, PHP_URL_HOST);
        if (!is_string($host) || !str_contains($host, '.') && $host !== 'localhost') {
            $this->errors[$field][] = 'Must be a valid URL.';
        }
        return $this;
    }

    public function valid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * First error message or empty string.
     */
    public function firstError(): string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? '';
        }
        return '';
    }

    /**
     * Sanitised copy of the input (trimmed strings).
     *
     * @return array<string, mixed>
     */
    public function sanitized(): array
    {
        return array_map(
            static fn ($v): mixed => is_string($v) ? trim($v) : $v,
            $this->data
        );
    }
}
