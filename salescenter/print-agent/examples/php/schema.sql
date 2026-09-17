CREATE TABLE print_agent_stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE print_agent_jobs (
    id CHAR(36) PRIMARY KEY,
    station_id BIGINT UNSIGNED NOT NULL,
    pdf_url TEXT NOT NULL,
    printer_name VARCHAR(300) NOT NULL,
    print_settings VARCHAR(300) NULL,
    status ENUM('queued','processing','printed','error','printer_offline') NOT NULL DEFAULT 'queued',
    status_message VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    reported_at DATETIME NULL,
    CONSTRAINT fk_print_job_station FOREIGN KEY (station_id) REFERENCES print_agent_stations(id),
    INDEX ix_print_jobs_next (station_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wygeneruj losowy token (minimum 32 bajty), pokaż go operatorowi tylko raz,
-- a do bazy zapisz SHA-256: hash('sha256', $plainToken).
