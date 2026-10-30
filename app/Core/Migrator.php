<?php

/**
 * Database migrator.
 *
 * Runs versioned SQL migrations from app/database/migrations in order and
 * records applied versions in the `migrations` table so the schema can
 * evolve safely (idempotent, forward-only).
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Migrator
{
    private string $migrationsDir;

    public function __construct(private readonly App $app)
    {
        $this->migrationsDir = INFOLOOP_APP . '/database/migrations';
    }

    /**
     * Creates the migrations bookkeeping table when missing.
     */
    public function ensureMigrationsTable(): void
    {
        $this->app->db()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                version VARCHAR(50) PRIMARY KEY,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return string[] Already applied migration versions.
     */
    public function applied(): array
    {
        $this->ensureMigrationsTable();
        return array_map(
            static fn (array $row): string => (string) $row['version'],
            $this->app->db()->query('SELECT version FROM migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Applies every pending migration inside a transaction per file.
     *
     * @return string[] Versions applied during this run.
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->applied();
        $pending = array_filter($this->migrationFiles(), static fn (string $v): bool => !in_array($v, $applied, true));
        sort($pending);
        $ran = [];
        foreach ($pending as $version) {
            $file = $this->migrationsDir . '/' . $version . '.sql';
            $sql = (string) file_get_contents($file);
            $this->app->db()->beginTransaction();
            try {
                foreach ($this->splitStatements($sql) as $statement) {
                    $this->app->db()->exec($statement);
                }
                $stmt = $this->app->db()->prepare('INSERT INTO migrations (version) VALUES (:v)');
                $stmt->execute([':v' => $version]);
                $this->app->db()->commit();
                $ran[] = $version;
                $this->app->logger()->info("Migration applied", ['version' => $version]);
            } catch (\Throwable $e) {
                $this->app->db()->rollBack();
                $this->app->logger()->error("Migration failed", ['version' => $version, 'error' => $e->getMessage()]);
                throw $e;
            }
        }
        return $ran;
    }

    /**
     * @return string[] Available migration versions (file names without .sql).
     */
    public function migrationFiles(): array
    {
        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        return array_map(static fn (string $f): string => basename($f, '.sql'), $files);
    }

    /**
     * Splits a SQL file into statements on ";" at line ends (sufficient for
     * our migration files which never embed semicolons inside strings).
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        foreach (preg_split('/\r?\n/', $sql) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with($trimmed, ';')) {
                $statements[] = $buffer;
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }
        return $statements;
    }
}
