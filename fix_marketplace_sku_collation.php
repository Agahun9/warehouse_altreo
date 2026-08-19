<?php

declare(strict_types=1);

/**
 * One-time, CLI-only fix for the computers products page timing out on the
 * "Wystawione aukcje" / "Brak aktywnych aukcji" filters.
 *
 * That filter runs a correlated EXISTS lookup (per product row) against
 * allegro_offers.sku / empik_offers.shop_sku+product_sku /
 * mediamarkt_offers.shop_sku+product_sku / erli_products.sku /
 * morele_offers.sku, comparing under an explicit COLLATE utf8mb4_unicode_ci
 * (needed to avoid "Illegal mix of collations" since these tables were created
 * at different times, some still utf8mb4_general_ci). That explicit COLLATE
 * only lets MySQL use the sku index for a seek if the column's real collation
 * already IS utf8mb4_unicode_ci - otherwise it forces a full table/index scan
 * per product row, which is what made the page take ~10 minutes and time out.
 *
 * Fixing the mismatch requires ALTER TABLE ... MODIFY COLUMN ... COLLATE, which
 * for an existing column is an ALGORITHM=COPY ALTER (MySQL rebuilds the whole
 * table). On a table with hundreds of thousands of rows that can take a while,
 * so this must run from the CLI (no execution time limit) instead of from a
 * web request, where shared hosting can kill a long-running query/connection
 * mid-flight ("MySQL server has gone away") and leave you retrying forever.
 *
 * Usage (over SSH, from the project root):
 *   php fix_marketplace_sku_collation.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Ten skrypt trzeba uruchomic z linii komend (SSH): php fix_marketplace_sku_collation.php\n";
    exit(1);
}

define('BASE_PATH', __DIR__);
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\AllegroStorageRepository;
use App\Models\EmpikStorageRepository;
use App\Models\MediaMarktStorageRepository;
use App\Models\ErliStorageRepository;
use App\Models\MoreleStorageRepository;

function step(string $label, callable $callback): void
{
    echo '[' . date('H:i:s') . "] {$label}... ";
    flush();

    $startedAt = microtime(true);

    try {
        $changes = $callback();
    } catch (Throwable $exception) {
        $elapsed = round(microtime(true) - $startedAt, 1);
        echo "BLAD po {$elapsed}s: " . $exception->getMessage() . "\n";
        return;
    }

    $elapsed = round(microtime(true) - $startedAt, 1);

    if ($changes === array()) {
        echo "OK ({$elapsed}s, juz poprawny collation)\n";
        return;
    }

    echo "OK ({$elapsed}s)\n";
    foreach ($changes as $change) {
        echo "    - {$change}\n";
    }
}

echo "Naprawa collation kolumn sku dla filtra marketplace na stronie komputerow.\n";
echo "To moze potrwac dluzej dla duzych tabel (ALTER przebudowuje cala tabele) - prosze czekac.\n\n";

$database = Database::instance();

step('allegro_offers.sku', static function () use ($database): array {
    $repository = new AllegroStorageRepository($database);
    $repository->ensureSchema();
    $change = $repository->fixSkuCollation();

    return $change !== null ? array($change) : array();
});

step('empik_offers.shop_sku / product_sku', static function () use ($database): array {
    $repository = new EmpikStorageRepository($database);
    $repository->ensureSchema();

    return $repository->fixSkuCollation();
});

step('mediamarkt_offers.shop_sku / product_sku', static function () use ($database): array {
    $repository = new MediaMarktStorageRepository($database);
    $repository->ensureSchema();

    return $repository->fixSkuCollation();
});

step('erli_products.sku', static function () use ($database): array {
    $repository = new ErliStorageRepository($database);
    $repository->ensureSchema();

    return $repository->fixSkuCollation();
});

step('morele_offers.sku', static function () use ($database): array {
    $repository = new MoreleStorageRepository($database);
    $repository->ensureSchema();

    return $repository->fixSkuCollation();
});

echo "\nGotowe. Filtr marketplace na stronie komputerow powinien teraz uzywac indeksu sku zamiast pelnego skanu.\n";
