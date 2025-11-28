<?php

class Database
{
    private static ?PDO $instance = null;
    private static bool $schemaChecked = false;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $dsn = env('DB_DSN');
            $user = env('DB_USER');
            $pass = env('DB_PASS');
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        self::ensureSchema(self::$instance);
        return self::$instance;
    }

    private static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaChecked) {
            return;
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM photos LIKE 'is_approved'");
        if ($stmt->fetch() === false) {
            $pdo->exec("ALTER TABLE photos ADD COLUMN is_approved TINYINT(1) DEFAULT 0 AFTER file_path");
        }

        self::$schemaChecked = true;
    }
}
