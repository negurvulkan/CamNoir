ALTER TABLE sessions ADD COLUMN IF NOT EXISTS extra_photos INT DEFAULT 0 AFTER photo_count;

CREATE TABLE IF NOT EXISTS bonus_codes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bonus_code_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bonus_code_id INT NOT NULL,
    session_id INT NOT NULL,
    redeemed_at DATETIME NOT NULL,
    CONSTRAINT fk_bonus_sessions_code FOREIGN KEY (bonus_code_id) REFERENCES bonus_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_bonus_sessions_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
