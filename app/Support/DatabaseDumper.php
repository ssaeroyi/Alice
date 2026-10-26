<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;

class DatabaseDumper
{
    private PDO $pdo;

    private string $database;

    private bool $snapshot;

    private function __construct(PDO $pdo, string $database, bool $snapshot)
    {
        $this->pdo = $pdo;
        $this->database = $database;
        $this->snapshot = $snapshot;
    }

    public static function create(array $connection): self
    {
        if (DB::connection()->transactionLevel() > 0) {
            return new self(DB::connection()->getPdo(), $connection['database'], false);
        }

        $dsn = 'mysql:host='.$connection['host'].';port='.($connection['port'] ?? 3306).';dbname='.$connection['database'].';charset=utf8mb4';

        $pdo = new PDO($dsn, $connection['username'], $connection['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);

        return new self($pdo, $connection['database'], true);
    }

    public function dump(): \Generator
    {
        if ($this->snapshot) {
            $this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        }

        yield $this->header();

        $tables = $this->tables();

        foreach ($tables as $table) {
            yield '--'.\PHP_EOL;
            yield '-- Table structure for `'.$table['name'].'`'.\PHP_EOL;
            yield '--'.\PHP_EOL;
            yield 'DROP TABLE IF EXISTS `'.$table['name'].'`;'.\PHP_EOL;

            $create = $this->pdo->query('SHOW CREATE TABLE `'.$table['name'].'`')->fetch(PDO::FETCH_ASSOC);
            $statement = array_values($create)[1];

            yield $statement.';'.\PHP_EOL.\PHP_EOL;

            if ($table['type'] !== 'VIEW') {
                yield from $this->dataInserts($table['name']);
            }
        }

        yield from $this->triggers();

        yield from $this->routines('PROCEDURE', 'SHOW PROCEDURE STATUS', 'SHOW CREATE PROCEDURE');
        yield from $this->routines('FUNCTION', 'SHOW FUNCTION STATUS', 'SHOW CREATE FUNCTION');

        yield '--'.\PHP_EOL;
        yield '-- End of Alice database dump'.\PHP_EOL;
    }

    public function tableCount(): int
    {
        return count($this->tables());
    }

    private function header(): string
    {
        return '-- Alice database dump'.\PHP_EOL
            .'-- Generated: '.now()->format('Y-m-d H:i:s T').\PHP_EOL
            .'-- Database: `'.$this->database.'`'.\PHP_EOL.\PHP_EOL;
    }

    private function tables(): array
    {
        $rows = $this->pdo->query(
            'SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '.$this->pdo->quote($this->database).' ORDER BY TABLE_NAME'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn (array $row) => ['name' => $row['TABLE_NAME'], 'type' => $row['TABLE_TYPE'] === 'VIEW' ? 'VIEW' : 'TABLE'],
            $rows
        );
    }

    private function dataInserts(string $table): \Generator
    {
        $statement = $this->pdo->query('SELECT * FROM `'.$table.'`');
        $columns = null;
        $batch = [];
        $batchSize = 0;

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
            }

            $batch[] = '('.implode(', ', array_map(fn ($value) => $value === null ? 'NULL' : $this->pdo->quote((string) $value), array_values($row))).')';
            $batchSize++;

            if ($batchSize >= 100) {
                yield $this->batchInsert($table, $columns, $batch);
                $batch = [];
                $batchSize = 0;
            }
        }

        if ($batchSize > 0) {
            yield $this->batchInsert($table, $columns, $batch);
        }
    }

    private function batchInsert(string $table, array $columns, array $rows): string
    {
        $quoted = array_map(fn (string $column) => '`'.$column.'`', $columns);

        return 'INSERT INTO `'.$table.'` ('.implode(', ', $quoted).') VALUES'.\PHP_EOL
            .'  '.implode(','.\PHP_EOL.'  ', $rows).';'.\PHP_EOL.\PHP_EOL;
    }

    private function triggers(): \Generator
    {
        $rows = $this->pdo->query(
            'SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = '.$this->pdo->quote($this->database).' ORDER BY TRIGGER_NAME'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $create = $this->pdo->query('SHOW CREATE TRIGGER `'.$row['TRIGGER_NAME'].'`')->fetch(PDO::FETCH_ASSOC);
            $sql = $create['SQL Original Statement'] ?? $create['Statement'];

            yield '--'.\PHP_EOL;
            yield '-- Trigger `'.$row['TRIGGER_NAME'].'`'.\PHP_EOL;
            yield '--'.\PHP_EOL;
            yield $sql.';'.\PHP_EOL.\PHP_EOL;
        }
    }

    private function routines(string $type, string $statusSql, string $createSql): \Generator
    {
        $rows = $this->pdo->query(
            $statusSql.' WHERE Db = '.$this->pdo->quote($this->database)
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $name = $row['Name'];
            $create = $this->pdo->query($createSql.' `'.$name.'`')->fetch(PDO::FETCH_ASSOC);
            $sql = $create['Create '.ucfirst(strtolower($type)).''];

            yield '--'.\PHP_EOL;
            yield '-- '.$type.' `'.$name.'`'.\PHP_EOL;
            yield '--'.\PHP_EOL;
            yield $sql.';'.\PHP_EOL.\PHP_EOL;
        }
    }
}
