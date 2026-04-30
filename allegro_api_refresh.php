<?php

include(dirname(__FILE__) . '/../config/config.inc.php');
include(dirname(__FILE__) . '/../init.php');



define('ACCOUNT', 'CaseU'); // wprowadź Client_ID aplikacji
define('CLIENT_ID', 'a01c7effa562487782d2ea4805548790'); // wprowadź Client_ID aplikacji
define('CLIENT_SECRET', 'vpvjjuKQ3JpGFmnDOpOUi0kCUaktEQQq9ZbcTexboo3CiUJ9M8zVpedN0qWSMIHR'); // wprowadź Client_Secret aplikacji
define('REDIRECT_URI', 'https://magazyn.altreo.pl/crm/allegro_api.php'); // wprowadź redirect_uri
define('AUTH_URL', 'https://allegro.pl/auth/oauth/authorize');
define('TOKEN_URL', 'https://allegro.pl/auth/oauth/token');

function getRefreshToken($client_id){
    $token = DB::getInstance()->getRow("SELECT refresh_token FROM `pr_allegro_tokens` where CLIENT_ID = '$client_id'");
    return $token['refresh_token'];
}

function refreshAccessToken($client_id, $client_secret,$account) {
    // Endpoint do odświeżania tokena
    $url = "https://allegro.pl/auth/oauth/token";
    $refresh_token = getRefreshToken($client_id);
  
    // Nagłówki żądania
    $headers = [
        "Authorization: Basic " . base64_encode($client_id.':'.$client_secret),
        "Content-Type: application/x-www-form-urlencoded"
    ];

    // Parametry żądania
    $data = [
        "grant_type" => "refresh_token",
        "refresh_token" => $refresh_token
    ];

    // Inicjalizacja cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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

    if (isset($data['access_token'])) {
        print "done - ".$account."<br>";
        // // Zwrócenie nowego access tokena oraz refresh tokena
        // $to = "kontakt@altreo.pl"; // Odbiorca
        // $subject = "Odświeżono tokeny api";
        // $message = "Odświeżono tokeny api w ".$account."";
        // $headers = "From: kontakt@altreo.pl\r\n" .
        //            "Reply-To: kontakt@altreo.pl\r\n" .
        //            "X-Mailer: PHP/" . phpversion();
        
        //            mail($to, $subject, $message, $headers);
        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token']
        ];
        
    } else {
        // Wypisanie błędu, jeśli odświeżanie tokena się nie powiodło
        echo "Błąd podczas odświeżania tokena w ".$account.": " . $data['error_description']."<br>";
        $to = "kontakt@altreo.pl"; // Odbiorca
        $subject = "BLAD przy odnowieniu refresh API";
        $message = "Blad przy odswiezaniu tokenów w ".$account."";
        $headers = "From: kontakt@altreo.pl\r\n" .
                   "Reply-To: kontakt@altreo.pl\r\n" .
                   "X-Mailer: PHP/" . phpversion();
        
                   mail($to, $subject, $message, $headers);
        return null;
    }
}


   
$allTokens=DB::getInstance()->executeS("select * from pr_allegro_tokens");

foreach ($allTokens as $token){
    $tokens = refreshAccessToken($token['CLIENT_ID'],$token['CLIENT_SECRET'],$token['account']);
    if ($tokens) {

    $data = [
        'access_token' => $tokens['access_token'],
        'account' => $token['account'],
        'refresh_token' => $tokens['refresh_token'],
    ];
    
    
    $where = 'account = "' . pSQL($token['account']) . '"';
    $result = Db::getInstance()->update('allegro_tokens', $data, $where);

}
}

?>