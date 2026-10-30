<?php

/**
 * Base active-record style model.
 *
 * Provides typed find/insert/update/delete helpers around the Database
 * helper. Concrete models add domain queries and validation.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

abstract class Model
{
    protected ?Database $db = null;

    /** Table name, overridden by subclasses. */
    protected string $table = '';

    /** @var string[] Primary key column(s). */
    protected array $primaryKey = ['id'];

    public function __construct(protected readonly App $app)
    {
    }

    public function db(): Database
    {
        return $this->db ??= new Database($this->app->db());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->db->one(
            sprintf('SELECT * FROM %s WHERE %s = :id', $this->table, $this->primaryKey[0]),
            ['id' => $id]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $orderBy = 'id', string $direction = 'ASC', int $limit = 0, int $offset = 0): array
    {
        $sql = sprintf('SELECT * FROM %s ORDER BY %s %s', $this->table, $orderBy, $direction);
        if ($limit > 0) {
            $sql .= sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        }
        return $this->db->all($sql);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateById(int $id, array $data): int
    {
        return $this->db->update($this->table, $data, $this->primaryKey[0] . ' = :__id', ['__id' => $id]);
    }

    public function deleteById(int $id): int
    {
        return $this->db->delete($this->table, $this->primaryKey[0] . ' = :__id', ['__id' => $id]);
    }

    /**
     * Finds the first row matching a single column.
     *
     * @return array<string, mixed>|null
     */
    public function findBy(string $column, mixed $value): ?array
    {
        return $this->db->one(
            sprintf('SELECT * FROM %s WHERE %s = :value', $this->table, $column),
            ['value' => $value]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function where(string $column, mixed $value, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        return $this->db->all(
            sprintf('SELECT * FROM %s WHERE %s = :value ORDER BY %s %s', $this->table, $column, $orderBy, $direction),
            ['value' => $value]
        );
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        return (int) $this->db->scalar(sprintf('SELECT COUNT(*) FROM %s WHERE %s', $this->table, $where), $params);
    }
}
