<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>accra_shop magazyn nowy — integracja Allegro</title>
    <style>
        :root { color-scheme: light; font-family: Inter, system-ui, sans-serif; color: #172033; background: #f4f6fb; }
        body { margin: 0; padding: 48px 20px; }
        main { max-width: 760px; margin: auto; padding: 40px; background: #fff; border-radius: 18px; box-shadow: 0 16px 50px rgba(23,32,51,.1); }
        h1 { margin-top: 0; font-size: clamp(28px, 5vw, 42px); }
        p { line-height: 1.7; }
        code { padding: 3px 7px; border-radius: 6px; background: #eef1f7; }
        a { color: #3157d5; }
    </style>
</head>
<body>
<main>
    <h1>accra_shop magazyn nowy</h1>
    <p>Wewnętrzny system magazynowy firmy ALTREO zintegrowany z Allegro REST API.</p>
    <p>Aplikacja służy do pobierania i zarządzania ofertami sprzedażowymi, łączenia ofert z produktami magazynowymi, aktualizacji cen i stanów, a także kończenia oraz wznawiania ofert.</p>
    <p>Aktualna wersja integracji: <code>2026.09.08</code>.</p>
    <p>Właściciel integracji: <a href="https://altreo.pl/">ALTREO</a>.</p>
</main>
</body>
</html>
