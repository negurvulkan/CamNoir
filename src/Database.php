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

        $sessionExtra = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'extra_photos'");
        if ($sessionExtra->fetch() === false) {
            $pdo->exec("ALTER TABLE sessions ADD COLUMN extra_photos INT DEFAULT 0 AFTER photo_count");
        }

        $hasBonusCodes = $pdo->query("SHOW TABLES LIKE 'bonus_codes'");
        if ($hasBonusCodes->fetch() === false) {
            $pdo->exec("CREATE TABLE bonus_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(255) NOT NULL UNIQUE,
                description TEXT NULL,
                extra_photos INT NOT NULL DEFAULT 0,
                type ENUM('single_use','per_session','unlimited') NOT NULL DEFAULT 'single_use',
                max_uses INT NULL,
                used_count INT NOT NULL DEFAULT 0,
                event_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                expires_at DATETIME NULL,
                CONSTRAINT fk_bonus_codes_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        $hasBonusSessions = $pdo->query("SHOW TABLES LIKE 'bonus_code_sessions'");
        if ($hasBonusSessions->fetch() === false) {
            $pdo->exec("CREATE TABLE bonus_code_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bonus_code_id INT NOT NULL,
                session_id INT NOT NULL,
                redeemed_at DATETIME NOT NULL,
                CONSTRAINT fk_bonus_sessions_code FOREIGN KEY (bonus_code_id) REFERENCES bonus_codes(id) ON DELETE CASCADE,
                CONSTRAINT fk_bonus_sessions_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        self::$schemaChecked = true;
    }
}
