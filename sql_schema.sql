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
    banner_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    photo_count INT DEFAULT 0,
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
