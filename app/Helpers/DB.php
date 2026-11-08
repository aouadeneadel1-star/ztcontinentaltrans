<?php
declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOException;
use PDOStatement;

/** Accès PDO unique (requêtes préparées partout : protection injection SQL). */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = require ROOT_PATH . '/config/database.php';
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
            try {
                self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                self::$pdo->exec("SET time_zone = '" . date('P') . "'");
            } catch (PDOException $e) {
                error_log('Connexion BDD impossible : ' . $e->getMessage());
                http_response_code(500);
                if (PHP_SAPI === 'cli') {
                    fwrite(STDERR, "Connexion à la base impossible : " . $e->getMessage() . "\n");
                    exit(1);
                }
                View::error(500, 'Base de données indisponible', 'Impossible de se connecter à la base de données. Vérifiez config/database.php.');
            }
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, int $id, array $data): void
    {
        $set = implode(',', array_map(static fn($c) => '`' . $c . '` = ?', array_keys($data)));
        self::run('UPDATE `' . $table . '` SET ' . $set . ' WHERE id = ?', [...array_values($data), $id]);
    }
}
