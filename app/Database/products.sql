CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    sku_prefix VARCHAR(20) NOT NULL DEFAULT 'PRD',
    allegro_category_id VARCHAR(64) DEFAULT NULL,
    empik_category_id VARCHAR(190) DEFAULT NULL,
    mediamarkt_category_id VARCHAR(190) DEFAULT NULL,
    temu_category_id VARCHAR(190) DEFAULT NULL,
    temu_category_name VARCHAR(255) DEFAULT NULL,
    temu_category_path TEXT DEFAULT NULL,
    temu_category_parameters LONGTEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_categories_slug (slug),
    KEY idx_categories_name (name),
    KEY idx_categories_allegro (allegro_category_id),
    KEY idx_categories_empik (empik_category_id),
    KEY idx_categories_mediamarkt (mediamarkt_category_id),
    KEY idx_categories_temu (temu_category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(64) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    category_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    localization VARCHAR(120) DEFAULT NULL,
    dimensions VARCHAR(120) DEFAULT NULL,
    img VARCHAR(255) DEFAULT NULL,
    price_net DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    price_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 23.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_products_sku (sku),
    KEY idx_products_name (product_name),
    KEY idx_products_localization (localization),
    KEY idx_products_updated_at (updated_at),
    KEY idx_products_deleted_at (deleted_at),
    KEY idx_products_price_net (price_net),
    KEY idx_products_price_gross (price_gross),
    KEY idx_products_category_id (category_id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS allegro_tokens (
    id TINYINT UNSIGNED NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    token_type VARCHAR(30) DEFAULT NULL,
    scope VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS allegro_cache (
    cache_key VARCHAR(190) NOT NULL,
    payload LONGTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cache_key),
    KEY idx_allegro_cache_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_allegro_parameters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    parameter_id VARCHAR(64) NOT NULL,
    value LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_product_allegro_param (product_id, parameter_id),
    KEY idx_product_allegro_product_id (product_id),
    CONSTRAINT fk_product_allegro_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_temu_parameters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    parameter_id VARCHAR(190) NOT NULL,
    value LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_product_temu_param (product_id, parameter_id),
    KEY idx_product_temu_product_id (product_id),
    CONSTRAINT fk_product_temu_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_mediamarkt_parameters (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    parameter_id VARCHAR(190) NOT NULL,
    value LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_product_mediamarkt_param (product_id, parameter_id),
    KEY idx_product_mediamarkt_product_id (product_id),
    CONSTRAINT fk_product_mediamarkt_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
