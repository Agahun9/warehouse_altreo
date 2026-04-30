<?php
function getAccessToken($account){
    $token = DB::getInstance()->getRow("SELECT access_token FROM `pr_allegro_tokens` where account = '".$account."'");
    return $token['access_token'];
}

function upsertAllegroProduct($offer, $account)
{
    $offerId = (int)$offer['id'];
    $signature = isset($offer['external']['id']) ? pSQL($offer['external']['id']) : '';
    $status = pSQL($offer['publication']['status']);
    $name = pSQL($offer['name']);
    $img = pSQL($offer['primaryImage']['url']);
    $price = (float)$offer['sellingMode']['price']['amount'];

    // Pobierz istniejący JSON z bazy
    $existing = Db::getInstance()->getRow("SELECT `allegro_product` FROM `pr_caseu_allegro_products` WHERE offerid = $offerId");
    $allegroProduct = $existing && $existing['allegro_product']
        ? json_decode($existing['allegro_product'], true)
        : [];

    if (!is_array($allegroProduct)) $allegroProduct = [];

    // Aktualizacja tylko konkretnych fragmentów
    $allegroProduct['stock'] = $offer['stock'] ?? $allegroProduct['stock'] ?? [];
    $allegroProduct['stats'] = $offer['stats'] ?? $allegroProduct['stats'] ?? [];
    $allegroProduct['publication'] = $offer['publication'] ?? $allegroProduct['publication'] ?? [];

    $jsonData = pSQL(json_encode($allegroProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $existingOffer = Db::getInstance()->getRow("SELECT * FROM `pr_caseu_allegro_products` WHERE `offerid` = $offerId");
    $OffertoDelete = Db::getInstance()->getRow("SELECT * FROM `pr_caseu_allegro_products_deleted` WHERE `offerid` = $offerId");

    if ($OffertoDelete) {
        Db::getInstance()->update('caseu_allegro_products_deleted', [
            'status' => $status,
            'account' => $account
        ], "offerid = $offerId");
    }

    if ($signature != '' && !$OffertoDelete) {
        $data = [
            'offerid' => $offerId,
            'signature' => $signature,
            'status' => $status,
            'account' => $account,
            'name' => $name,
            'img' => $img,
            'price' => $price,
            'allegro_product' => $jsonData,
            'date_add' => date('Y-m-d H:i:s')
        ];

        if ($existingOffer) {
            Db::getInstance()->update('caseu_allegro_products', $data, "offerid = $offerId");
        } else {
            Db::getInstance()->insert('caseu_allegro_products', $data);
        }
    }
}

// Funkcja do pobierania ofert z Allegro z ręcznym tokenem dostępowym
function getAllOffersFromAllegroAndSaveToDb($account)
{
    set_time_limit(0);
    ini_set('memory_limit', '1024M');

    $limit = 800;        // bezpieczny limit Allegro
    $maxBatches = 50;    // max 80000 ofert na jedno uruchomienie
    $batches = 0;

    // 🔒 LOCK
    Db::getInstance()->execute("
        INSERT INTO pr_allegro_sync_state (account, a_offset, is_running, updated_at)
        VALUES ('".pSQL($account)."', 0, 1, NOW())
        ON DUPLICATE KEY UPDATE
            is_running = IF(is_running = 0 OR updated_at < NOW() - INTERVAL 10 MINUTE, 1, is_running),
            updated_at = NOW()
    ");

    $state = Db::getInstance()->getRow("
        SELECT is_running, a_offset
        FROM pr_allegro_sync_state
        WHERE account = '".pSQL($account)."'
    ");

    // jeśli już trwa inny sync
    if (!$state || (int)$state['is_running'] !== 1) {
        return;
    }

    $offset = (int)$state['a_offset'];
    $accessToken = getAccessToken($account);

    do {
        $url = 'https://api.allegro.pl/sale/offers?' . http_build_query([
            'limit'  => $limit,
            'offset' => $offset
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                "Accept: application/vnd.allegro.public.v1+json"
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            break;
        }

        $data = json_decode($response, true);

        if (isset($data['error']) && $data['error'] === 'invalid_token') {
            break;
        }

        if (empty($data['offers'])) {
            // koniec listy → reset offsetu
            Db::getInstance()->execute("
                UPDATE pr_allegro_sync_state
                SET a_offset = 0, is_running = 0, updated_at = NOW()
                WHERE account = '".pSQL($account)."'
            ");
            return;
        }

        foreach ($data['offers'] as $offer) {
            upsertAllegroProduct($offer, $account);
        }

        $offset += $limit;

        Db::getInstance()->execute("
            UPDATE pr_allegro_sync_state
            SET a_offset = ".(int)$offset.", updated_at = NOW()
            WHERE account = '".pSQL($account)."'
        ");

        $batches++;
        usleep(200000); // 200 ms

    } while ($batches < $maxBatches);

    // 🔓 UNLOCK
    Db::getInstance()->execute("
        UPDATE pr_allegro_sync_state
        SET is_running = 0, updated_at = NOW()
        WHERE account = '".pSQL($account)."'
    ");
}





function buildAllegroHtmlDescription($sections) {
    $html = '';

    foreach ($sections as $sectionIndex => $section) {
        foreach ($section['items'] ?? [] as $item) {

            if ($item['type'] === 'TEXT' && !empty(trim($item['content']))) {
                $content = trim($item['content']);
                $lines = preg_split("/\r?\n/", $content);

                foreach ($lines as $lineIndex => $line) {
                    $line = trim($line);
                    if ($line === '') continue;

                    // Pierwsza linia pierwszej sekcji → <h1>
                    if ($sectionIndex === 0 && $lineIndex === 0) {
                        $html .= '<h1>' . $line . '</h1>' . PHP_EOL;
                        continue;
                    }

                    // Linie zaczynające się od emoji → <h2>
                    if (preg_match('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $line)) {
                        $html .= '<h2>' . $line . '</h2>' . PHP_EOL;
                        continue;
                    }

                    // Linie z listami punktowanymi
                    if (preg_match('/^[-*•]/', $line)) {
                        $html .= '<ul><li>' . ltrim($line, "-*• ") . '</li></ul>' . PHP_EOL;
                        continue;
                    }

                    // Normalny paragraf
                    $html .= '<p>' . $line . '</p>' . PHP_EOL;
                }
            }

            if ($item['type'] === 'IMAGE' && !empty($item['url'])) {
                $url = $item['url'];
                $html .= '<p><img src="' . $url . '" loading="lazy" /></p>' . PHP_EOL;
            
            }
        }
    }

    return $html;
}





function updateAllegroOfferData($offerId)
{
    // Pobranie konta z bazy
    $account = Db::getInstance()->executeS("SELECT * FROM `pr_caseu_allegro_products` WHERE offerid = " . (int)$offerId);
    if (!$account) {
        echo "⚠️ Brak oferty w bazie (ID: $offerId)<br>";
        return;
    }

    $accessToken = getAccessToken($account[0]['account']);
    $url = "https://api.allegro.pl/sale/product-offers/$offerId"; 

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        echo "❌ Błąd podczas pobierania danych oferty ID: $offerId<br>";
        return;
    }

    $offerData = json_decode($response, true);
    if (!is_array($offerData)) {
        echo "❌ Błąd dekodowania JSON-a z API Allegro dla oferty ID: $offerId<br>";
        return;
    }

    // Pobranie istniejącego JSON-a z bazy
    $existing = Db::getInstance()->getRow("SELECT `allegro_product` FROM `pr_caseu_allegro_products` WHERE offerid = " . (int)$offerId);
    $allegroProduct = $existing && $existing['allegro_product']
        ? json_decode($existing['allegro_product'], true)
        : [];

    if (!is_array($allegroProduct)) $allegroProduct = [];
    // print ("<pre>");
    // print_r ($offerData);
        // 3️⃣ Normalizacja opisu do HTML



// Tworzymy HTML z opisem
$descriptionHtml = buildAllegroHtmlDescription($offerData['description']['sections']);

// Tworzymy tablicę danych
$extraData = [
    'name'       => $offerData['name'] ?? null,
    'offerId'    => $offerId,
    'delivery'   => $offerData['delivery'] ?? [],
    'payments'   => $offerData['payments'] ?? [],
    'parameters' => [
        'offer'   => $offerData['parameters'] ?? [],
        'product' => $offerData['productSet'][0]['product']['parameters'] ?? [],
    ],
    'images' => $offerData['images'] ?? [],
    'product' => [
        'id'    => $offerData['productSet'][0]['product']['id'] ?? null,
        'ean'   => $offerData['productSet'][0]['product']['parameters'][11]['values'][0] ?? null,
        'brand' => $offerData['productSet'][0]['product']['parameters'][0]['values'][0] ?? null,
    ],
    'stock' => $offerData['stock'] ?? [],
    'price' => $offerData['sellingMode']['price'] ?? []
];

// JSON danych
$allegroProduct = array_merge($allegroProduct, $extraData);
$jsonData = json_encode($extraData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// // INSERT lub UPDATE jeśli oferta już istnieje
// $sql = "INSERT INTO `pr_caseu_allegro_products_description` (`offerid`, `description`, `json_data`)
//         VALUES (" . (int)$offerId . ", '" . '' . "', '" . pSQL($jsonData) . "')
//         ON DUPLICATE KEY UPDATE
//         `description` = VALUES(`description`),
//         `json_data` = VALUES(`json_data`)";
//  if (Db::getInstance()->execute($sql)) {
//     echo "✅ Zapisano/zmodyfikowano dane dla oferty ID w kolumnie desc: $offerId<br>";
// } else {
//     echo "❌ Błąd przy zapisie danych dla oferty ID w kolumnie desc: $offerId<br>";
// }      


  $result = Db::getInstance()->update(
        'caseu_allegro_products',
        ['allegro_product' => pSQL($jsonData)],
        'offerid = ' . (int)$offerId
    );
 if ($result) {
    echo "✅ Zapisano/zmodyfikowano dane dla oferty ID w kolumnie allegro: $offerId<br>";
} else {
    echo "❌ Błąd przy zapisie danych dla oferty ID w kolumnie allegro: $offerId<br>";
}       


}




function getOfferById($offerId) {

    $account = Db::getInstance()->executeS("SELECT * FROM `pr_caseu_allegro_products` where offerid = ".$offerId."");
    $accessToken = getAccessToken($account[0]['account']);
    // URL do endpointu pobierania oferty w środowisku produkcyjnym
    $url = "https://api.allegro.pl/sale/product-offers/$offerId"; 

    // Przygotowanie nagłówków
    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/json"
    ];

    // Inicjalizacja cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Wykonanie żądania i przetworzenie odpowiedzi
    $response = curl_exec($ch);

    // Sprawdzenie błędów
    if ($response === false) {
        echo 'Błąd: ' . curl_error($ch);
    } else {
        $data = json_decode($response, true);
        
        // Sprawdzenie, czy wystąpił błąd związany z tokenem
        if (isset($data['error']) && $data['error'] === 'invalid_token') {
            echo "Błąd: Nieprawidłowy lub wygasły token dostępu.";
        } else {
           return $data;
            // Wyświetlenie lub dalsze przetwarzanie danych o ofercie
       
        }
    }

    curl_close($ch);
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function setStatusOffer($offerId,$action,$account) {
 

    $accessToken = getAccessToken($account);
    $commandId = generateUUID();
    // Endpoint do aktualizacji statusu oferty
    $url = "https://api.allegro.pl/sale/offer-publication-commands/$commandId";
 
    // Przygotowanie nagłówków
    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];

    // Przygotowanie danych żądania do zakończenia oferty
    $data = [
        "publication" => [
            "action" => $action
        ],
        "offerCriteria" => [
            [
                "type" => "CONTAINS_OFFERS",
                "offers" => [
                    ["id" => $offerId]
                ]
            ]
        ]
    ];

    // Inicjalizacja cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Wykonanie żądania
    $response = curl_exec($ch);

    // Sprawdzenie błędów cURL
    if ($response === false) {
        echo 'Błąd cURL: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    // Przetworzenie odpowiedzi
    $data = json_decode($response, true);
   
    if (isset($data['id'])) {
        return $commandId;
    } else {
        return false;
    }



}

function updateOfferFields($offerId, $account, $fields = []) {
    $accessToken = getAccessToken($account);
    $url = "https://api.allegro.pl/sale/product-offers/$offerId";

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];

    $data = [];

    // SKU
    if (isset($fields['sku'])) {
        $data['external'] = ['id' => $fields['sku']];
    }
    // Cena
    if (isset($fields['price'])) {
        $data['sellingMode'] = [
            'price' => [
                'amount' => number_format($fields['price'], 2, '.', ''),
                'currency' => 'PLN'
            ]
        ];
    }
    // Zmiana produktu (product.id)
    if (isset($fields['product_id'])) {
        $data['product'] = [ 'id' => $fields['product_id'] ];
    }
    // Producent (manufacturer)
    if (isset($fields['manufacturer'])) {
        $data['parameters'][] = [
            'id' => 'manufacturer',
            'values' => [$fields['manufacturer']]
        ];
    }
    // Osoba odpowiedzialna (custom field, np. responsible)
    if (isset($fields['responsible'])) {
        $data['parameters'][] = [
            'id' => 'responsible',
            'values' => [$fields['responsible']]
        ];
    }
    // Status
    if (isset($fields['status'])) {
        $data['publication'] = [ 'status' => $fields['status'] ];
    }
    if (isset($fields['delivery'])) {
        $data['delivery'] = [ 'handlingTime' => $fields['delivery'] ];
    }
    if (isset($fields['invoice'])) {
        $data['payments'] = [ 'invoice' => $fields['invoice'] ];
    }

    if (empty($data)) {
        // Nic do aktualizacji
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        echo 'Błąd cURL: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    $responseData = json_decode($response, true);
    print_r($ch);
    if (isset($responseData['errors'])) {
        return $responseData['errors'];
    } else {
        return true;
    }
}





function updateOfferTAX($offerId,$account) {
    $accessToken = getAccessToken($account); // Funkcja zwracająca ważny token dostępowy
    $url = "https://api.allegro.pl/sale/product-offers/$offerId";

    // Przygotowanie nagłówków
    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json", // Użycie wersji publicznej
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];

    // Przygotowanie danych żądania
    $data = [
        "taxSettings" => [
            "rates" => [
                [
                    "rate" => "23.00",
                    "countryCode" => "PL"
                ],
                [
                    "rate" => "21.00",
                    "countryCode" => "CZ"
                ],
                [
                    "rate" => "27.00",
                    "countryCode" => "HU"
                ],
                [
                    "rate" => "23.00",
                    "countryCode" => "SK"
                ]
            ]
        ]

    ];

    // Inicjalizacja cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH"); // Zmiana na PATCH
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Wykonanie żądania
    $response = curl_exec($ch);

    // Sprawdzenie błędów cURL
    if ($response === false) {
        echo 'Błąd cURL: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    // Przetworzenie odpowiedzi
    $responseData = json_decode($response, true);

    if (isset($responseData['errors'])) {
        return $responseData['errors'];
    } else {
      
        return true;
    }
}


function getOfferPublicationErrors($commandId, $account) {
    $accessToken = getAccessToken($account);
    
    // Endpoint do sprawdzenia statusu publikacji oferty
    $url = "https://api.allegro.pl/sale/offer-publication-commands/$commandId/tasks";
    
    // Przygotowanie nagłówków
    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];
    
    // Inicjalizacja cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Wykonanie żądania
    $response = curl_exec($ch);
    
    // Sprawdzenie błędów cURL
    if ($response === false) {
        echo 'Błąd cURL: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    // Przetworzenie odpowiedzi
    $data = json_decode($response, true);

    if (isset($data['tasks'])) {
        return $data['tasks'];
    } else {
        return false;
    }
}


function updateOfferWithAllegroProduct($offerId, $account) {
    $accessToken = getAccessToken($account);
    
    // Endpoint do aktualizacji oferty
    $url = "https://api.allegro.pl/sale/product-offers/$offerId";
    
    // Przygotowanie nagłówków
    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];
    
    // Dane do aktualizacji (wymuszenie synchronizacji z produktem Allegro)
    $data = json_encode([
        "product" => [
            "set" => true
        ]
    ]);
    
    // Inicjalizacja cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    // Wykonanie żądania
    $response = curl_exec($ch);
    
    // Sprawdzenie błędów cURL
    if ($response === false) {
        echo 'Błąd cURL: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    // Przetworzenie odpowiedzi
    return json_decode($response, true);
}
function getCategoryParameters($categoryId, $account) {
    $accessToken = trim(getAccessToken($account));
    $url = "https://api.allegro.pl/sale/categories/$categoryId/parameters";

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        echo "❌ cURL error: " . curl_error($ch) . "\n";
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}













function getCompatibleProductGroups($type, $account) {
    $accessToken = trim(getAccessToken($account));
    $url = "https://api.allegro.pl/sale/compatible-products/groups?type=" . urlencode($type);

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        // Możesz zapisać do logu, zamiast echo
        // error_log("cURL error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    if ($httpCode >= 400) {
        // error_log("HTTP error $httpCode");
        return null;
    }

    return json_decode($response, true);
}

function getCompatibleProducts($type, $account, $options = []) {
    $accessToken = trim(getAccessToken($account));
    if (!$accessToken) {
        // error_log("Brak tokenu dostępu");
        return null;
    }

    $queryParams = ['type' => $type];

    if (isset($options['phrase']) && $options['phrase'] !== '') {
        $queryParams['phrase'] = $options['phrase'];
    } else {
        if (isset($options['limit'])) {
            $queryParams['limit'] = $options['limit'];
        }
        if (isset($options['offset'])) {
            $queryParams['offset'] = $options['offset'];
        }
        if (isset($options['group.id'])) {
            $queryParams['group.id'] = $options['group.id'];
        }
    }

    $url = "https://api.allegro.pl/sale/compatible-products?" . http_build_query($queryParams);

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        // error_log("cURL error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    if ($httpCode >= 400) {
        // error_log("HTTP error $httpCode");
        return null;
    }

    return json_decode($response, true);
}

function getAllPhoneBrandsWithModels($account) {
    $type = "PHONE";
    $limit = 200;
    $result = [];

    $groups = getCompatibleProductGroups($type, $account);
    if (!$groups || !isset($groups['groups'])) {
        // error_log("Nie udało się pobrać grup kompatybilności.");
        return [];
    }

    foreach ($groups['groups'] as $group) {
        $brandName = $group['text'];
        $groupId = $group['id'];

        $models = [];
        $offset = 0;

        do {
            $response = getCompatibleProducts($type, $account, [
                'group.id' => $groupId,
                'limit' => $limit,
                'offset' => $offset
            ]);

            if (!$response || !isset($response['compatibleProducts'])) {
                break;
            }

            foreach ($response['compatibleProducts'] as $product) {
                $models[] = $product['text'];
            }

            $fetchedCount = count($response['compatibleProducts']);
            $offset += $fetchedCount;

            if ($fetchedCount < $limit) {
                break;
            }
        } while ($offset < $response['totalCount']);

        $result[$brandName] = $models;
    }

    return $result;
}

function getAllPhoneModelsFlatList($account) {
    $type = "PHONE";
    $limit = 200;
    $result = [];

    $groups = getCompatibleProductGroups($type, $account);
    if (!$groups || !isset($groups['groups'])) {
        return [];
    }

    foreach ($groups['groups'] as $group) {
        $groupId = $group['id'];

        $offset = 0;

        do {
            $response = getCompatibleProducts($type, $account, [
                'group.id' => $groupId,
                'limit' => $limit,
                'offset' => $offset
            ]);

            if (!$response || !isset($response['compatibleProducts'])) {
                break;
            }

            foreach ($response['compatibleProducts'] as $product) {
                $result[] = [
                    'name' => $product['text'],
                    'id' => $product['id']
                ];
            }

            $fetchedCount = count($response['compatibleProducts']);
            $offset += $fetchedCount;

            if ($fetchedCount < $limit) {
                break;
            }
        } while ($offset < $response['totalCount']);
    }

    return $result;
}


function updateProductAndSafety($offerId, $account, $fields = []) {
    $accessToken = getAccessToken($account);

    $headers = [
        "Authorization: Bearer " . trim($accessToken),
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];

    // 1. Pobierz aktualną ofertę
    $ch = curl_init("https://api.allegro.pl/sale/product-offers/$offerId");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return [
            "error" => "Nie udało się pobrać oferty",
            "httpCode" => $httpCode,
            "response" => $response
        ];
    }

    $offerData = json_decode($response, true);

    // Zachowujemy oryginalny opis i zdjęcia
    $originalDescription = $offerData['description']['sections'] ?? [];
    $originalImages = $offerData['images'] ?? [];

    // 2. Przygotowanie danych do zmiany productSet
    $patchData = [];
    if (isset($fields['JSON_DATA'])) {
        $offerData1 = json_decode($fields['JSON_DATA'], true);

        $productId = $offerData1['productId'] ?? null;
        $safetyDescription = $offerData1['safetyDescription'] ?? '';
        $producer = $offerData1['producer'] ?? '';


        if ($productId || $safetyDescription || $producer) {
            $productSetItem = [];

            if ($productId) {
                $productSetItem['product'] = ['id' => $productId];
            }

            if ($safetyDescription) {
                $productSetItem['safetyInformation'] = [
                    'type' => 'TEXT',
                    'description' => $safetyDescription
                ];
            }

            if ($producer) {
                $productSetItem['responsibleProducer'] = [
                    'type' => 'NAME',
                    'name' => $producer
                ];
            }

            if (!empty($productSetItem)) {
                $patchData['productSet'] = [$productSetItem];
            }
        }
    }

    // PATCH do zmiany productSet
    $respPatch = null;
    $httpCodePatch = null;
    if (!empty($patchData)) {
        $ch = curl_init("https://api.allegro.pl/sale/product-offers/$offerId");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($patchData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCodePatch = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respPatch = json_decode($response, true);
    }

    // 3. Przywrócenie opisu i zdjęć
    $respRestore = null;
    $httpCodeRestore = null;
    if (!empty($originalDescription) || !empty($originalImages)) {
        $restoreData = [];
        if (!empty($originalDescription)) $restoreData['description'] = ["sections" => $originalDescription];
        if (!empty($originalImages)) $restoreData['images'] = $originalImages;

        sleep(1); // krótka pauza dla bezpieczeństwa

        $ch = curl_init("https://api.allegro.pl/sale/product-offers/$offerId");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($restoreData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCodeRestore = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respRestore = json_decode($response, true);
    }

    // return [
    //     "productSet_http" => $httpCodePatch,
    //     "productSet_response" => $respPatch,
    //     "restore_http" => $httpCodeRestore,
    //     "restore_response" => $respRestore
    // ];
}







function createAllegroOfferFromProduct($account, $productId)
{
    $accessToken = getAccessToken($account);
    if (!$accessToken) {
        return ['error' => 'Brak access token'];
    }

    $url = 'https://api.allegro.pl/sale/product-offers';

    $data = [
       "productSet" => [
    [
        "product" => [
            "id" => $productId,

            // GPSR – odpowiedzialny producent (OBOWIĄZKOWE jeśli brak w produkcie)
           
        ],
 "responsibleProducer" => [
                "name" => "CASE U"
                
 ],
        "quantity" => [
            "value" => 1
        ]
    ]
],
        


        "name" => "Etui silikonowe do Samsung Galaxy S23 przezroczyste",

        "afterSalesServices" => [
            "returnPolicy" => [
                "id" => "bbff7853-0cf6-48d6-aaaa-2268833aac1f"
            ]
        ],


        "sellingMode" => [
            "format" => "BUY_NOW",
            "price" => [
                "amount" => "29.99",
                "currency" => "PLN"
            ]
        ],

        "stock" => [
            "available" => 10,
            "unit" => "UNIT"
        ],

        "delivery" => [
            "shippingRates" => [
                "id" => "fe6274c8-b0f6-442b-becf-225529f24841" // MUSI ISTNIEĆ NA KONCIE
            ],
            "handlingTime" => "PT24H"
        ],

        "payments" => [
            "invoice" => "VAT"
        ],

        "location" => [
            "countryCode" => "PL",
            "province" => "MALOPOLSKIE",
            "city" => "Kraków",
            "postCode" => "30-702"
        ],

        "publication" => [
            "status" => "ACTIVE"
        ],

        "external" => [
            "id" => "ETUI-S23-" . time()
        ],

        "language" => "pl-PL"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Accept: application/vnd.allegro.public.v1+json",
            "Accept-Language: pl-PL",
            "Content-Type: application/vnd.allegro.public.v1+json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => 'CURL ERROR', 'msg' => $curlErr];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 300) {
        return [
            'error' => 'ALLEGRO ERROR',
            'httpCode' => $httpCode,
            'response' => $decoded
        ];
    }

    // zapis do Twojej tabeli
    upsertAllegroProduct($decoded, $account);

    return [
        'success' => true,
        'offerId' => $decoded['id']
    ];
}

function AutomaticlyAssignroduct($offerId) {
    // 1. Pobranie danych konta i ZAPISANYCH wcześniej danych produktu z bazy
    $accountData = Db::getInstance()->getRow("SELECT * FROM `pr_caseu_allegro_products` WHERE offerid = " . (int)$offerId);
    if (empty($accountData)) {
        return ['success' => false, 'message' => 'Nie znaleziono oferty w lokalnej bazie.'];
    }
    
    $accessToken = getAccessToken($accountData['account']);
    
    // Dekodujemy stare dane z bazy
    $localSavedData = json_decode($accountData['allegro_product'], true);

    // 2. Pobieramy AKTUALNĄ ofertę z Allegro (żeby wyciągnąć EAN)
    $of = getOfferById($offerId);
    if (!$of) {
        return ['success' => false, 'message' => 'Nie udało się pobrać danych z Allegro.'];
    }

    // Wyciągamy EAN
    $parameters = $of['productSet'][0]['product']['parameters'] ?? [];
    $ean = '';
    foreach ($parameters as $param) {
        if ($param['name'] === 'EAN (GTIN)' || $param['id'] == '225693') {
            $ean = $param['values'][0] ?? '';
            break;
        }
    }
    
    if (empty($ean)) {
        return ['success' => false, 'message' => 'Brak EAN w ofercie.'];
    }

    // 3. Szukamy produktu w katalogu Allegro
    $queryParams = http_build_query(['phrase' => $ean, 'mode' => 'GTIN', 'language' => 'pl-PL']);
    $url = "https://api.allegro.pl/sale/products?" . $queryParams;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken", 
        "Accept: application/vnd.allegro.public.v1+json"
    ]);
    $res = curl_exec($ch);
    $searchData = json_decode($res, true);
    curl_close($ch);

    if (empty($searchData['products'])) {
        return ['success' => false, 'message' => 'Nie znaleziono produktu w katalogu Allegro.'];
    }
    
    $productId = $searchData['products'][0]['id'];

    // 4. PRZYGOTOWANIE DANYCH DO PATCH (Kluczowy moment naprawy)
    
    // a) Ustalenie źródła zdjęć (Baza -> API)
    $rawImages = [];
    if (!empty($localSavedData['images'])) {
        $rawImages = $localSavedData['images'];
    } elseif (!empty($of['images'])) {
        $rawImages = $of['images'];
    }

    // b) Jeśli w bazie zdjęcia były zapisane jako string JSON (widoczne w Twoim zrzucie), odkoduj je
    if (is_string($rawImages)) {
        $decoded = json_decode($rawImages, true);
        if (is_array($decoded)) {
            $rawImages = $decoded;
        }
    }

    // c) Normalizacja do formatu wymaganego przez PATCH: [['url' => '...'], ['url' => '...']]
    $formattedImages = [];
    if (is_array($rawImages)) {
        foreach ($rawImages as $img) {
            if (is_string($img)) {
                // Sytuacja: ["http://url1", "http://url2"]
                $formattedImages[] = ['url' => $img];
            } elseif (is_array($img)) {
                if (isset($img['original'])) {
                    // Sytuacja z GET /sale/offers: ['original' => 'http...', 'thumbnail' => '...']
                    $formattedImages[] = ['url' => $img['original']];
                } elseif (isset($img['url'])) {
                    // Sytuacja poprawna
                    $formattedImages[] = ['url' => $img['url']];
                }
            }
        }
    }

    // Jeśli po normalizacji nadal brak zdjęć, nie wysyłaj pustej tablicy, bo usunie zdjęcia
    // W takim wypadku lepiej przerwać lub pominąć pole images
    if (empty($formattedImages)) {
         return ['success' => false, 'message' => 'Błąd przetwarzania zdjęć - brak poprawnych URLi.'];
    }

    $originalName = (!empty($localSavedData['name'])) ? $localSavedData['name'] : $of['name'];

    // 5. WYSYŁKA DANYCH (Jeden, poprawny request)
    $patchUrl = "https://api.allegro.pl/sale/product-offers/" . $offerId;
    
    $patchData = [
        "productSet" => [
            [
                "product" => ["id" => $productId]
            ]
        ],
        "name" => $originalName,
        "images" => $formattedImages // Tutaj musi być tablica obiektów z kluczem 'url'
    ];

    // Opcjonalnie: Jeśli masz opis w bazie, dodaj go. 
    // Opis z $of ma inną strukturę niż wymagana w PATCH, więc bezpieczniej brać go z bazy jeśli jest poprawny
    // lub pominąć, jeśli nie chcemy go zmieniać (PATCH zmienia tylko to co wyślemy).
    
    $chPatch = curl_init($patchUrl);
    curl_setopt($chPatch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chPatch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($chPatch, CURLOPT_POSTFIELDS, json_encode($patchData));
    
    $patchHeaders = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];
    
    curl_setopt($chPatch, CURLOPT_HTTPHEADER, $patchHeaders);
    $patchResponse = curl_exec($chPatch);
    $patchHttpCode = curl_getinfo($chPatch, CURLINFO_HTTP_CODE);
    curl_close($chPatch);

    if ($patchHttpCode === 200 || $patchHttpCode === 202) {
        return ['success' => true, 'message' => 'Pomyślnie powiązano z produktem i przywrócono dane.'];
    } else {
        return [
            'success' => false, 
            'http_code' => $patchHttpCode,
            'details' => json_decode($patchResponse, true),
            'sent_data' => $patchData // Do debugowania
        ];
    }
}














function setFixedImageAndDescription($offerId) {
    // 1. Pobranie danych konta
    $accountData = Db::getInstance()->getRow(
        "SELECT * FROM `pr_caseu_allegro_products` WHERE offerid = " . (int)$offerId
    );
    if (empty($accountData)) {
        return [
            'success' => false,
            'message' => 'Nie znaleziono oferty w lokalnej bazie.'
        ];
    }

    // 2. Pobranie tokena
    $accessToken = trim(getAccessToken($accountData['account']));

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/vnd.allegro.public.v1+json",
        "Content-Type: application/vnd.allegro.public.v1+json"
    ];

    $patchUrl = "https://api.allegro.pl/sale/product-offers/$offerId";

    // 3. Sztywne zdjęcia (tablica stringów)
    $images = [
        "https://a.allegroimg.com/s720/110227/073a4ea24ae1b7189e04b1703c01/Rovicky-skorzany-portfel-damski-klasyczny-brelok-zestaw-prezent-Wzor-dominujacy-bez-wzoru"
    ];

    // 4. Sztywny opis (HTML w text, bez items)
    $description = "<p>Przykładowy opis oferty. Tutaj możesz wpisać dowolny tekst, który będzie widoczny w ofercie.</p>" ;
    // 5. Przygotowanie danych do PATCH
    $patchData = [
        "images" => $images,
        "description" => [
            "sections" => $description
        ]
    ];
    

    // 6. Wysyłka PATCH
    $ch = curl_init($patchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_POSTFIELDS => json_encode($patchData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'message' => "Błąd cURL: $error"
        ];
    }

    curl_close($ch);

    // 7. Obsługa odpowiedzi
    $decoded = json_decode($response, true);

    if (in_array($httpCode, [200, 202])) {
        return [
            'success' => true,
            'message' => 'Zdjęcie i opis zostały zaktualizowane.',
            'response' => $decoded
        ];
    }

    return [
        'success' => false,
        'message' => 'Nie udało się zaktualizować zdjęcia i opisu.',
        'details' => $decoded,
        'httpCode' => $httpCode
    ];
}
