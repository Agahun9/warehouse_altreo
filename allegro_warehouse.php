<?php
/**
 * Example Application
 *
 * @package Example-application
 */
session_start();

 if($_SESSION['login']!=1 || $_SESSION['role']!= 'admin'){
    header("Location: /crm/login.php?status=error");
    die();
 }
 if($_GET['logout']=='true'){
    $_SESSION['login']=0;
    header("Location: /crm/login.php?status=logout");
    die();
 }
error_reporting(-1);
ini_set('display_errors', 'On');
require_once 'libs/Smarty.class.php';
include(dirname(__FILE__).'/../config/config.inc.php');
include(dirname(__FILE__).'/../init.php');
require_once (dirname(__FILE__).'/classes/caseueu_parameters.php');
require_once (dirname(__FILE__).'/classes/caseu_product.php');
require_once (dirname(__FILE__).'/sellasist_api.php');
ini_set('max_input_vars', '20000');
ini_set('post_max_size', '64M');

$db = DB::getInstance();
$currentUrl = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$smarty = new Smarty;
//$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = false;
$smarty->cache_lifetime = 120;

// Pagination setup
$per_page_options = [20, 50, 100, 200,5000,10000];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $per_page_options) ? (int)$_GET['per_page'] : 50;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;


// Pobieranie parametrĂłw z GET
$name_filter = isset($_GET['name_filter']) ? pSQL($_GET['name_filter']) : '';
$signature_filter = isset($_GET['signature_filter']) ? pSQL($_GET['signature_filter']) : '';
$status_filter = isset($_GET['status']) ? pSQL($_GET['status']) : '';
$search_filter = isset($_GET['search_filter']) ? pSQL($_GET['search_filter']) : '';
$from_filter = isset($_GET['from_filter']) ? (int)$_GET['from_filter'] : null;
$to_filter = isset($_GET['to_filter']) ? (int)$_GET['to_filter'] : null;
$offerid_filter = isset($_GET['offerid_filter']) ? $_GET['offerid_filter'] : '';
$account_filter = isset($_GET['account_filter']) ? $_GET['account_filter'] : '';
$allegro_products_checked = isset($_GET['allegro_products']) && $_GET['allegro_products'] == '1';

// Budowanie warunku WHERE
$where_conditions = [];

if (!empty($account_filter)) {
    // UsuĹ„ zbÄ™dne spacje
    $account_filter = trim($account_filter);

    // SprawdĹş, czy filtr zaczyna siÄ™ od "!" â€“ oznacza negacjÄ™ (czyli NOT IN)
    $is_negative = false;
    if (strpos($account_filter, '!') === 0) {
        $is_negative = true;
        $account_filter = ltrim($account_filter, '!'); // usuĹ„ "!"
        $account_filter = trim($account_filter);
    }

    // Rozdziel po przecinku (obsĹ‚uga wielu kont)
    $accounts = array_map('trim', explode(',', $account_filter));

    // Zabezpieczenie przed SQL Injection â€” escapowanie kaĹĽdej wartoĹ›ci
    $accounts = array_map(function($a) use ($db) {
        return $db->escape($a);
    }, $accounts);

    // Zbuduj listÄ™ w formacie SQL
    $accounts_list = "'" . implode("','", $accounts) . "'";

    // StwĂłrz warunek SQL
    if ($is_negative) {
        $where_conditions[] = "prcap.account NOT IN ($accounts_list)";
    } else {
        $where_conditions[] = "prcap.account IN ($accounts_list)";
    }
}
// Filtracja po nazwie
if (!empty($name_filter)) {
    $where_conditions[] = "`name` LIKE '%" . $name_filter . "%'";
}

// Filtracja po signature
if (!empty($signature_filter)) {

    if (strpos($signature_filter, '!') !== false) {
        // JeĹ›li zmienna zawiera znak "!", wykonaj odpowiedniÄ… logikÄ™
        $where_conditions[] = "`signature` NOT LIKE '" . str_replace('!', '', $signature_filter) . "'";
    } else {
        // Standardowa logika, jeĹ›li nie ma znaku "!"
        $where_conditions[] = "`signature` = '" . $signature_filter . "'";
    }
}

// Filtracja po statusie
if (!empty($status_filter)) {
   
    if ($status_filter == 'ACTIVE'){
        $where_conditions[] = "(status = 'ACTIVE' OR status = 'ACTIVATE') ";
    }
    elseif ($status_filter == 'ENDED'){
        $where_conditions[] = "(status = 'END' OR status = 'ENDED') ";
    }
    else{
 $where_conditions[] = "`status` = '" . $status_filter . "'";
    }
}


// Filtracja po sĹ‚owie kluczowym w nazwie lub opisie
if (!empty($search_filter)) {
    $search_filter = str_replace(' ', '%', $search_filter); // Zamiana spacji na % dla wyszukiwania wielowyrazowego
    $where_conditions[] = "(`name` LIKE '%" . $search_filter . "%' OR `description` LIKE '%" . $search_filter . "%')";
}

// Filtrowanie po iloĹ›ci (quantity)
if (!is_null($from_filter) && $from_filter != '') {
    $where_conditions[] = "`quantity` >= " . $from_filter;
}

if (!is_null($to_filter)  && $to_filter != '') {
    $where_conditions[] = "`quantity` <= " . $to_filter;
}

// P// Pobieranie wartoĹ›ci z GET
$sold_from = isset($_GET['sold_from']) && $_GET['sold_from'] !== '' ? (int)$_GET['sold_from'] : null;
$sold_to   = isset($_GET['sold_to']) && $_GET['sold_to'] !== '' ? (int)$_GET['sold_to'] : null;
$delivery  = isset($_GET['delivery']) && $_GET['delivery'] !== '' ? $_GET['delivery'] : null;
$payment   = isset($_GET['payment']) && $_GET['payment'] !== '' ? $_GET['payment'] : null;

// Filtrowanie po "sold"
if (!is_null($sold_from)) {
    $where_conditions[] = "CAST(JSON_EXTRACT(allegro_product, '$.stock.sold') AS UNSIGNED) >= " . $sold_from;
}

if (!is_null($sold_to)) {
    $where_conditions[] = "CAST(JSON_EXTRACT(allegro_product, '$.stock.sold') AS UNSIGNED) <= " . $sold_to;
}

// Filtrowanie po "delivery"
if (!is_null($delivery)) {
    $where_conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(allegro_product, '$.delivery')) = '" . pSQL($delivery) . "'";
}

// Filtrowanie po "payments.invoice"
if (!is_null($payment)) {
    $where_conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(allegro_product, '$.payments.invoice')) = '" . pSQL($payment) . "'";
}

// Filtracja po offerid (jeĹ›li podano)
if (!empty($offerid_filter)) {
    // Rozdzielenie parametrĂłw offerid po przecinkach i utworzenie warunku
    $offerid_array = explode(',', $offerid_filter);
    $offerid_array = array_map('intval', $offerid_array);  // Upewnij siÄ™, ĹĽe wszystkie offerid sÄ… liczbami
    $where_conditions[] = "prcap.offerid IN (" . implode(',', $offerid_array) . ")";
}
if ($allegro_products_checked) {
    
    $where_conditions[] = "prcap.allegro_product = ''";

}
// Pobieramy offerid z formularza
$offerid_filter = $_POST['offerid_filter'] ?? '';

// Zamieniamy wpisany tekst na tablicÄ™ (jeĹ›li jest)
$offerid_array = [];
if (!empty($offerid_filter)) {
    $offerid_array = explode(',', $offerid_filter);
    $offerid_array = array_map('trim', $offerid_array);
}

// JeĹ›li przesĹ‚ano plik txt
if (isset($_FILES['offerid_file']) && $_FILES['offerid_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['offerid_file']['tmp_name'];
    $content = file_get_contents($file_tmp);
    $content = preg_replace('/\s+/', '', $content); // usuĹ„ spacje, entery
    $file_offerids = explode(',', $content);

    // Dodaj do istniejÄ…cej tablicy
    $offerid_array = array_merge($offerid_array, $file_offerids);
}

// UsuĹ„ puste elementy i duplikaty oraz upewnij siÄ™, ĹĽe to liczby
$offerid_array = array_filter($offerid_array, function($id) {
    return preg_match('/^\d+$/', $id);
});
$offerid_array = array_unique($offerid_array);

// Dopiero teraz budujemy warunek WHERE jeĹ›li mamy coĹ› w tablicy
if (!empty($offerid_array)) {
    $ids = implode(',', $offerid_array);
    $where_conditions[] = "prcap.offerid IN ($ids)";
}

$where_conditions[] = " signature != 0 AND account NOT IN ('ACCRA_SHOP', 'ACCRA_COMPUTERS')"; // Dodanie warunku, aby wykluczyÄ‡ produkty z ACCRA_SHOP i signature 14162
// ĹÄ…czenie warunkĂłw w zapytaniu
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';


// Wspolne JOIN-y dla licznika i listy wynikow.
$products_join_sql = "
LEFT JOIN (


    SELECT 
        pcp.id_product_ps AS id_produktu,
        'produkt' AS typ,
        pcp.quantity_physical AS quantity
    FROM pr_caseu_product pcp

    UNION ALL

    SELECT 
        pcp.id_product_ps_glass AS id_produktu,
        'szklo' AS typ,
        LEAST(
            pcp.quantity_physical,
            pcp1.quantity_physical 
        ) AS quantity
    FROM pr_caseu_product pcp
    JOIN pr_caseu_product pcp1 
        ON pcp.id_glass = pcp1.id
    WHERE pcp.id_product_ps_glass IS NOT NULL
      AND pcp.id_product_ps_glass > 0

) AS all_products 

    ON all_products.id_produktu = prcap.signature";

// Count total products for pagination
$count_sql = "SELECT COUNT(DISTINCT prcap.offerid) AS total_products
FROM pr_caseu_allegro_products prcap
$products_join_sql
$where_clause";
$total_products_row = $db->getRow($count_sql);

$total_products = $total_products_row ? (int)$total_products_row['total_products'] : 0;
$total_pages = max(1, ceil($total_products / $per_page));
// Finalne zapytanie SQL z paginacjÄ…

$sql = "SELECT 
    prcap.*,
    all_products.typ,
    all_products.quantity
FROM pr_caseu_allegro_products prcap
$products_join_sql
    
$where_clause
GROUP BY prcap.offerid
LIMIT $per_page OFFSET $offset";

// Wykonanie zapytania
$allegro_products = $db->executeS($sql);

$sql = "SELECT COUNT(id) as task_count FROM `pr_task_queue`;";
$task_count = $db->executeS($sql);




// Build pagination URLs for Smarty
$base_query = $_GET;
unset($base_query['page']); // We'll set page manually
$pagination_urls = [];
for ($i = 1; $i <= $total_pages; $i++) {
    $pagination_urls[$i] = '?' . http_build_query(array_merge($base_query, ['page' => $i]));
}
$pagination_urls['first'] = '?' . http_build_query(array_merge($base_query, ['page' => 1]));
$pagination_urls['prev'] = '?' . http_build_query(array_merge($base_query, ['page' => max(1, $page-1)]));
$pagination_urls['next'] = '?' . http_build_query(array_merge($base_query, ['page' => min($total_pages, $page+1)]));
$pagination_urls['last'] = '?' . http_build_query(array_merge($base_query, ['page' => $total_pages]));

$smarty->assign(array(
    'allegro_products' => $allegro_products,
    'task_count' => $task_count,
    'total_products' => $total_products,
    'total_pages' => $total_pages,
    'current_page' => $page,
    'per_page' => $per_page,
    'per_page_options' => $per_page_options,
    'pagination_urls' => $pagination_urls
));


if (isset($_POST['action'])) {
    if ($_POST['action'] == "export") {
        // Export selected offers to CSV
        if (isset($_POST['offer']) && is_array($_POST['offer'])) {
            $res = [];
            foreach ($_POST['offer'] as $offer) {
                print $offer."|";
            }
            $_SESSION['notification'] = [
                'type' => 'success',
                'msg' => 'Dane zostaĹ‚y wyeksportowane do pliku: ' . $filePath
            ];
        } else {
            $_SESSION['notification'] = [
                'type' => 'danger',
                'msg' => 'Brak danych do eksportu.'
            ];
        }
        // header("Location: $currentUrl");
        return;
    }
        if($_POST['action'] == 'Empik_CSV'){
           require_once 'altreo_exports.php';
        exportToEmpikCASE($_POST['offer'] ?? []);
    }

    // Bulk price update
    if ($_POST['action'] == 'bulk_price_update' && isset($_POST['offer']) && isset($_POST['new_price'])) {
        $new_price = floatval($_POST['new_price']);
        $updated = 0;
        foreach ($_POST['offer'] as $offerid) {
            $product = $db->getRow("SELECT * FROM pr_caseu_allegro_products WHERE offerid = ".intval($offerid));
            if ($product) {
                $db->update('caseu_allegro_products', ['price' => $new_price], "offerid = ".intval($offerid));
                $updated++;
            }
        }
        $_SESSION['notification'] = [
            'type' => 'success',
            'msg' => 'Ceny zostaĹ‚y zaktualizowane dla ' . $updated . ' ofert.'
        ];
        header("Location: $currentUrl");
        return;
    }

    // Bulk parameter update (example: status)
    if ($_POST['action'] == 'bulk_status_update' && isset($_POST['offer']) && isset($_POST['new_status'])) {
        $new_status = $_POST['new_status'];
        $updated = 0;
        foreach ($_POST['offer'] as $offerid) {
            $db->update('caseu_allegro_products', ['status' => $new_status], "offerid = ".intval($offerid));
            $updated++;
        }
        $_SESSION['notification'] = [
            'type' => 'success',
            'msg' => 'Status zostaĹ‚ zaktualizowany dla ' . $updated . ' ofert.'
        ];
        header("Location: $currentUrl");
        return;
    }


    // Batch queue actions (delete, SKU, etc.)
    if (isset($_POST['offer']) && is_array($_POST['offer']) && !in_array($_POST['action'], ['export', 'bulk_price_update', 'bulk_status_update'])) {
        $notifications = [];
        foreach ($_POST['offer'] as $offer) {
            $existingtask = Db::getInstance()->getRow("SELECT * FROM `pr_task_queue` WHERE `offerId` = ".$offer AND " action = '".$_POST['action']."'");
            if ($existingtask) {
                $notifications[] = [
                    'type' => 'warning',
                    'msg' => 'Oferta ID ' . $offer . ' juĹĽ znajduje siÄ™ w kolejce zadaĹ„.'
                ];
            } else {
                if ($_POST['action'] == 'DELETE') {
                    $insertData = [
                        'offerid' => $offer,
                        'status' => 'ACTIVE',
                        'date' => date('Y-m-d H:i:s')
                    ];
                    $res = Db::getInstance()->insert('caseu_allegro_products_deleted', $insertData);
                    $insertData = [
                        'offerId' => $offer,
                        'action' => 'END',
                        'new_varriable' => '',
                        'date_add' => date('Y-m-d H:i:s')
                    ];
                    $res2 = Db::getInstance()->insert('task_queue', $insertData);
                    $sql = "DELETE FROM `pr_caseu_allegro_products` WHERE offerid= $offer";
                    $db->execute($sql);
                    if ($res && $res2) {
                        $notifications[] = [
                            'type' => 'success',
                            'msg' => 'Oferta ID ' . $offer . ' zostaĹ‚a usuniÄ™ta i dodana do kolejki.'
                        ];
                    } else {
                        $notifications[] = [
                            'type' => 'danger',
                            'msg' => 'BĹ‚Ä…d podczas usuwania oferty ID ' . $offer
                        ];
                    }
                }
                else if($_POST['action'] == 'DELETE_only_offer') {
                   
                    $sql = "DELETE FROM `pr_caseu_allegro_products` WHERE offerid= $offer";
                    $db->execute($sql);
                    $notifications[] = [
                        'type' => 'success',
                        'msg' => 'Oferta ID ' . $offer . ' zostaĹ‚a usuniÄ™ta z systemu.'
                    ];
                }
                else if($_POST['action'] == 'update_product_offer'){
                    $data = [
                        "productId" => $_POST['productId'] ?? '',
                        "safetyDescription" => $_POST['safetyDescription'] ?? '',
                        "producer" => $_POST['producer'] ?? ''
                    ];

                    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

                       $insertData = [
                        'offerId' => $offer,
                        'action' => $_POST['action'],
                        'new_varriable' => $jsonData,
                        'date_add' => date('Y-m-d H:i:s')
                    ];
       
                    $res = Db::getInstance()->insert('task_queue', $insertData);
                    if ($res) {
                        $notifications[] = [
                            'type' => 'success',
                            'msg' => 'Dodano zadanie zmiany produktyzacji do kolejki dla oferty ID ' . $offer
                        ];
                    } else {
                        $notifications[] = [
                            'type' => 'danger',
                            'msg' => 'BĹ‚Ä…d podczas dodawania zadania do kolejki dla oferty ID ' . $offer
                        ];
                    }
                }
                else if($_POST['action'] == 'INVOICE-SETTINGS'){
               
                       $insertData = [
                        'offerId' => $offer,
                        'action' => $_POST['action'],
                        'new_varriable' => $_POST['invoice_option_input'] ?? '',
                        'date_add' => date('Y-m-d H:i:s')
                    ];
       
                    $res = Db::getInstance()->insert('task_queue', $insertData);
                    if ($res) {
                        $notifications[] = [
                            'type' => 'success',
                            'msg' => 'Dodano zadanie zmiany ustawieĹ„ faktury do kolejki dla oferty ID ' . $offer
                        ];
                    } else {
                        $notifications[] = [
                            'type' => 'danger',
                            'msg' => 'BĹ‚Ä…d podczas dodawania zadania do kolejki dla oferty ID ' . $offer
                        ];
                    }
                }
                else if($_POST['action'] == 'DELIVERY-SETTINGS'){
               
                       $insertData = [
                        'offerId' => $offer,
                        'action' => $_POST['action'],
                        'new_varriable' => $_POST['delivery_value_input'] ?? '',
                        'date_add' => date('Y-m-d H:i:s')
                    ];
       
                    $res = Db::getInstance()->insert('task_queue', $insertData);
                    if ($res) {
                        $notifications[] = [
                            'type' => 'success',
                            'msg' => 'Dodano zadanie zmiany ustawieĹ„ wysyĹ‚ki do kolejki dla oferty ID ' . $offer
                        ];
                    } else {
                        $notifications[] = [
                            'type' => 'danger',
                            'msg' => 'BĹ‚Ä…d podczas dodawania zadania do kolejki dla oferty ID ' . $offer
                        ];
                    }
                }
                else {
                    $new_varriable = ($_POST['action'] == "SKU") ? $_POST['SKU'] : '';
                    $insertData = [
                        'offerId' => $offer,
                        'action' => $_POST['action'],
                        'new_varriable' => $new_varriable,
                        'date_add' => date('Y-m-d H:i:s')
                    ];
                    $res = Db::getInstance()->insert('task_queue', $insertData);
                    if ($res) {
                        $notifications[] = [
                            'type' => 'success',
                            'msg' => 'Dodano zadanie do kolejki dla oferty ID ' . $offer
                        ];
                    } else {
                        $notifications[] = [
                            'type' => 'danger',
                            'msg' => 'BĹ‚Ä…d podczas dodawania zadania do kolejki dla oferty ID ' . $offer
                        ];
                    }
                }
            }
        }
        if (!empty($notifications)) {
            // If only one notification, show as is. If many, join messages.
            $_SESSION['notification'] = count($notifications) === 1 ? $notifications[0] : [
                'type' => 'info',
                'msg' => implode('<br>', array_map(function($n){return $n['msg'];}, $notifications))
            ];
        }
        header("Location: $currentUrl");
        return;
    }
}

// Show notification if set
if (isset($_SESSION['notification'])) {
    $smarty->assign('notification', $_SESSION['notification']);
    unset($_SESSION['notification']);
}

$smarty->display('allegro/allegro_warehouse.tpl');

