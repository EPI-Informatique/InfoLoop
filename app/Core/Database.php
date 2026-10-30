<?php

/**
 * Thin query-builder helper over PDO with strict typing and named
 * placeholders. Models compose this instead of writing raw SQL inline.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

use PDO;
use PDOStatement;

final class Database
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Executes a statement and returns it.
     *
     * @param array<string, mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $this->bind($stmt, $params);
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function insert(string $table, array $params): int
    {
        $columns = array_keys($params);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function update(string $table, array $params, string $where, array $whereParams = []): int
    {
        $sets = array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($params));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return $this->run($sql, array_merge($params, $whereParams))->rowCount();
    }

    public function delete(string $table, string $where, array $whereParams = []): int
    {
        return $this->run(sprintf('DELETE FROM %s WHERE %s', $table, $where), $whereParams)->rowCount();
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Binds parameters with correct PDO types.
     *
     * @param array<string, mixed> $params
     */
    private function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = is_string($key) && !str_starts_with($key, ':') ? ':' . $key : $key;
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value, $type);
        }
    }
}
