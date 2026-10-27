<?php
final class Db {
    private static ?PDO $pdo = null;
    static function pdo(bool $noDb = false): PDO {
        if (!$noDb && self::$pdo) return self::$pdo;
        $dsn = sprintf('mysql:host=%s;port=%s;%scharset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), $noDb ? '' : 'dbname=' . env('DB_NAME', 'krono') . ';');
        $p = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);
        return $noDb ? $p : (self::$pdo = $p);
    }
    static function q(string $sql, array $params = []): PDOStatement { $s = self::pdo()->prepare($sql); $s->execute($params); return $s; }
}
