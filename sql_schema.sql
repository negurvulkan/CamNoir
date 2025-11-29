CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    max_photos_per_session INT DEFAULT 10,
    auto_delete_days INT DEFAULT 30,
    frame_branding_text VARCHAR(255) NULL,
    auto_approve_photos TINYINT(1) DEFAULT 0,
    theme_settings TEXT NULL,
    color_filters TEXT NULL,
    banner_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    photo_count INT DEFAULT 0,
    extra_photos INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    CONSTRAINT fk_sessions_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    session_id INT NOT NULL,
    picture_uuid VARCHAR(255) NOT NULL,
    delete_code VARCHAR(16) UNIQUE NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_approved TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    delete_request_status ENUM('pending', 'approved', 'rejected') NULL,
    delete_request_reason VARCHAR(255) NULL,
    delete_request_note TEXT NULL,
    delete_request_at DATETIME NULL,
    CONSTRAINT fk_photos_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_photos_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE delete_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    type ENUM('delete_code', 'session') NOT NULL,
    detail VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_delete_logs_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bonus_codes (
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

CREATE TABLE bonus_code_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bonus_code_id INT NOT NULL,
    session_id INT NOT NULL,
    redeemed_at DATETIME NOT NULL,
    CONSTRAINT fk_bonus_sessions_code FOREIGN KEY (bonus_code_id) REFERENCES bonus_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_bonus_sessions_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE unlockable_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NULL,
    type ENUM('sticker','overlay','filter','frame') NOT NULL,
    name VARCHAR(255) NOT NULL,
    asset_path VARCHAR(255) NULL,
    css_filter VARCHAR(255) NULL,
    rarity ENUM('common','uncommon','rare','legendary') NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_unlockable_items_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE unlock_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    type ENUM('single_use','per_session','unlimited') NOT NULL DEFAULT 'single_use',
    expires_at DATETIME NULL,
    event_id INT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_unlock_codes_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE unlock_code_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_id INT NOT NULL,
    item_id INT NOT NULL,
    CONSTRAINT fk_unlock_code_items_code FOREIGN KEY (code_id) REFERENCES unlock_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_unlock_code_items_item FOREIGN KEY (item_id) REFERENCES unlockable_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE unlocked_items_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    item_id INT NOT NULL,
    unlocked_at DATETIME NOT NULL,
    CONSTRAINT fk_unlocked_sessions_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_unlocked_sessions_item FOREIGN KEY (item_id) REFERENCES unlockable_items(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_session_item (session_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE unlock_code_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_id INT NOT NULL,
    session_id INT NULL,
    redeemed_at DATETIME NOT NULL,
    CONSTRAINT fk_unlock_usage_code FOREIGN KEY (code_id) REFERENCES unlock_codes(id) ON DELETE CASCADE,
    CONSTRAINT fk_unlock_usage_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
