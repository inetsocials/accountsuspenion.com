<?php
declare(strict_types=1);

namespace DR\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. Supports MySQL/MariaDB (production) and SQLite (fallback and tests).
 * All queries use bound parameters.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect(Config::get('db', []));
        }
        return self::$pdo;
    }

    public static function connect(array $db): void
    {
        $driver = $db['driver'] ?? 'mysql';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($driver === 'sqlite') {
            $path = $db['path'] ?? (DR_STORAGE . '/db/ascrm.sqlite');
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $opts);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $db['host'] ?? 'localhost',
                (int) ($db['port'] ?? 3306),
                $db['name'] ?? ''
            );
            $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', $opts);
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        }
        self::$pdo = $pdo;
        self::$driver = $driver;
    }

    public static function driver(): string
    {
        self::pdo();
        return self::$driver;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $name = is_int($k) ? $k + 1 : (str_starts_with((string) $k, ':') ? $k : ':' . $k);
            $type = is_int($v) ? PDO::PARAM_INT : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $st->bindValue($name, $v, $type);
        }
        $st->execute();
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $r = self::run($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function insert(string $table, array $data): int
    {
        self::assertIdent($table);
        $cols = array_keys($data);
        array_map([self::class, 'assertIdent'], $cols);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')';
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        self::assertIdent($table);
        $sets = [];
        $bind = [];
        foreach ($data as $k => $v) {
            self::assertIdent($k);
            $sets[] = $k . ' = :set_' . $k;
            $bind['set_' . $k] = $v;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge($bind, $params))->rowCount();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function assertIdent(string $s): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $s)) {
            throw new \InvalidArgumentException('Invalid identifier');
        }
    }

    /** For tests: reset the singleton. */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
