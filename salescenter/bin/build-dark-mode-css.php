<?php
/**
 * Generuje dist/css/dark-mode.css – ciemny motyw panelu.
 *
 * Arkusze panelu mają kolory wpisane na sztywno, więc skrypt czyta każdy z nich i dla każdej reguły
 * z kolorami tworzy jej kopię pod `html[data-bs-theme=dark]` z przeliczonymi kolorami:
 * jasne tła -> ciemne, ciemny tekst -> jasny, jasne obramowania -> ciemne. Kolory akcentów
 * (przyciski, statusy) zostają bez zmian. Kopiowane są wszystkie deklaracje kolorów reguły
 * (także niezmienione), żeby zachować kolejność i siłę selektorów oryginału.
 * Na końcu dopisywane są ręczne poprawki ($manual).
 *
 * Uruchom po każdej zmianie kolorów w CSS:  php bin/build-dark-mode-css.php
 */

$root = dirname(__DIR__);
$sources = [
    'app/Views/templates/layout/header.tpl',
    'dist/css/liquid-glass.css',
    'dist/css/orders.css',
    'dist/css/orders-documents.css',
    'dist/css/messages.css',
    'dist/css/orders-create.css',
    'dist/css/orders-cod.css',
    'dist/css/orders-automation.css',
    'dist/css/orders-ksef.css',
    'dist/css/orders-statuses.css',
    'dist/css/orders-print-templates.css',
    'dist/css/integrations.css',
    'dist/css/archive.css',
    'app/Views/templates/orders/correct.tpl',
];
const DARK = 'html[data-bs-theme=dark]';

/* ---------- kolory ---------- */

function parseColor(string $c): ?array
{
    $c = strtolower(trim($c));
    $named = ['white' => '#ffffff', 'black' => '#000000', 'red' => '#ff0000', 'green' => '#008000', 'blue' => '#0000ff'];
    if (isset($named[$c])) { $c = $named[$c]; }
    if ($c[0] === '#') {
        $h = substr($c, 1);
        if (strlen($h) === 3 || strlen($h) === 4) { $h = preg_replace('/(.)/', '$1$1', $h); }
        if (strlen($h) !== 6 && strlen($h) !== 8) { return null; }
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2)), strlen($h) === 8 ? hexdec(substr($h, 6, 2)) / 255 : 1.0];
    }
    if (preg_match('/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[,\/]\s*([\d.]+%?))?\s*\)$/', $c, $m)) {
        $a = isset($m[4]) && $m[4] !== '' ? (substr($m[4], -1) === '%' ? (float)$m[4] / 100 : (float)$m[4]) : 1.0;
        return [(float)$m[1], (float)$m[2], (float)$m[3], $a];
    }
    return null;
}

function toHsl(array $rgb): array
{
    [$r, $g, $b] = [$rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255];
    $max = max($r, $g, $b); $min = min($r, $g, $b); $l = ($max + $min) / 2;
    if ($max == $min) { return [0, 0, $l]; }
    $d = $max - $min;
    $s = $l > .5 ? $d / (2 - $max - $min) : $d / ($max + $min);
    if ($max == $r) { $h = ($g - $b) / $d + ($g < $b ? 6 : 0); } elseif ($max == $g) { $h = ($b - $r) / $d + 2; } else { $h = ($r - $g) / $d + 4; }
    return [$h * 60, $s, $l];
}

function fromHsl(float $h, float $s, float $l, float $a): string
{
    $h /= 360;
    $f = function ($p, $q, $t) {
        if ($t < 0) { $t += 1; } if ($t > 1) { $t -= 1; }
        if ($t < 1 / 6) { return $p + ($q - $p) * 6 * $t; }
        if ($t < 1 / 2) { return $q; }
        if ($t < 2 / 3) { return $p + ($q - $p) * (2 / 3 - $t) * 6; }
        return $p;
    };
    if ($s == 0) { $r = $g = $b = $l; } else {
        $q = $l < .5 ? $l * (1 + $s) : $l + $s - $l * $s; $p = 2 * $l - $q;
        $r = $f($p, $q, $h + 1 / 3); $g = $f($p, $q, $h); $b = $f($p, $q, $h - 1 / 3);
    }
    [$r, $g, $b] = [(int)round($r * 255), (int)round($g * 255), (int)round($b * 255)];
    if ($a >= .999) { return sprintf('#%02x%02x%02x', $r, $g, $b); }
    return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim(rtrim(number_format($a, 3, '.', ''), '0'), '.'));
}

/** Biel i szarości dostają chłodny, granatowy odcień panelu; pastele zachowują swój odcień. */
function neutralize(float $h, float $s, float $l, float $maxS): array
{
    if ($s < .2 || $l > .985) { return [222, .22]; }
    return [$h, min($s, $maxS)];
}

/** Przelicza kolor dla danej roli: text | bg | border | shadow. Zwraca null, gdy kolor zostaje. */
function darkColor(string $color, string $role): ?string
{
    $rgb = parseColor($color);
    if ($rgb === null) { return null; }
    [$h, $s, $l] = toHsl($rgb); $a = $rgb[3];
    if ($a == 0) { return null; }
    switch ($role) {
        case 'text':
            if ($l >= .6) { return null; }
            return fromHsl($h, $s, max(.98 - $l * .7, .72), $a);
        case 'bg':
            if ($a <= .3) {
                // Półprzezroczyste nakładki: białe rozjaśniają też ciemne tło, ciemne trzeba odwrócić.
                return $l < .35 ? fromHsl($h, $s, 1 - $l, $a) : null;
            }
            if ($l < .7) { return null; }
            $nl = $l >= .99 ? .155 : .10 + (1 - $l) * .9;
            [$h, $s] = neutralize($h, $s, $l, .35);
            return fromHsl($h, $s, $nl, $a);
        case 'border':
            if ($a <= .3) { return $l < .35 ? fromHsl($h, $s, 1 - $l, $a) : null; }
            if ($l < .7) { return null; }
            [$h, $s] = neutralize($h, $s, $l, .3);
            return fromHsl($h, $s, .2 + (1 - $l) * .6, $a);
        case 'shadow':
            if ($l < .8) { return null; }
            return 'rgba(255,255,255,' . rtrim(rtrim(number_format(min($a, .06), 3, '.', ''), '0'), '.') . ')';
    }
    return null;
}

function roleFor(string $prop, string $value): ?string
{
    $p = strtolower($prop);
    if (strpos($p, '--') === 0) {
        if (preg_match('/shadow/', $p)) { return 'shadow'; }
        if (preg_match('/line|border/', $p)) { return 'border'; }
        if (preg_match('/ink|text|muted|fg|color/', $p)) { return 'text'; }
        if (preg_match('/bg|soft|surface|glass|paper|panel|card|back/', $p)) { return 'bg'; }
        if (preg_match('/accent|brand|primary|status|platform/', $p)) { return null; }
        // Nieznana zmienna: jasne wartości to tło, ciemne szarości to tekst; nasycone kolory to akcenty
        // (używane i jako tekst, i jako tło pod białym napisem), więc zostają.
        if (preg_match('/#[0-9a-f]{3,8}\b|rgba?\([^)]*\)|\bwhite\b/i', $value, $m) && ($rgb = parseColor($m[0])) !== null) {
            [, $s, $l] = toHsl($rgb);
            return $l > .75 ? 'bg' : ($l < .45 && $s < .3 ? 'text' : null);
        }
        return null;
    }
    if (in_array($p, ['color', 'fill', 'stroke', 'caret-color', '-webkit-text-fill-color', 'text-decoration-color', 'column-rule-color'], true)) { return 'text'; }
    if (strpos($p, 'background') === 0) { return 'bg'; }
    if (strpos($p, 'border') === 0 || strpos($p, 'outline') === 0 || $p === 'column-rule') {
        return preg_match('/radius|width|style|spacing|collapse|image/', $p) ? null : 'border';
    }
    if ($p === 'box-shadow' || $p === 'text-shadow') { return 'shadow'; }
    return null;
}

function convertValue(string $value, string $role): string
{
    // Kolory statusów/platform wybiera użytkownik i bywają ciemne – tekst w nich rozjaśniamy.
    if ($role === 'text' && preg_match('/^var\(--(status|row-status|status-color|platform|msg-color|ms-c)\)(\s*!important)?$/', $value, $m)) {
        return 'color-mix(in srgb, var(--' . $m[1] . ') 65%, #f1f5f9)' . ($m[2] ?? '');
    }
    return preg_replace_callback('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|\b(?<![-\w])white\b(?![-\w])|\b(?<![-\w])black\b(?![-\w])/', function ($m) use ($role) {
        $new = darkColor($m[0], $role);
        return $new ?? $m[0];
    }, $value);
}

/* ---------- parser CSS ---------- */

function stripComments(string $css): string
{
    return preg_replace('~/\*.*?\*/~s', '', $css);
}

/** Dzieli CSS na elementy najwyższego poziomu: ['prelude' => ..., 'body' => ...]. */
function splitBlocks(string $css): array
{
    $out = []; $len = strlen($css); $i = 0; $start = 0;
    while ($i < $len) {
        $ch = $css[$i];
        if ($ch === '"' || $ch === "'") { $e = strpos($css, $ch, $i + 1); $i = $e === false ? $len : $e + 1; continue; }
        if ($ch === ';' ) { $start = $i + 1; $i++; continue; } // @import/@charset itp.
        if ($ch === '{') {
            $prelude = trim(substr($css, $start, $i - $start));
            $depth = 1; $j = $i + 1;
            while ($j < $len && $depth > 0) {
                $c = $css[$j];
                if ($c === '"' || $c === "'") { $e = strpos($css, $c, $j + 1); $j = $e === false ? $len : $e + 1; continue; }
                if ($c === '{') { $depth++; } elseif ($c === '}') { $depth--; }
                $j++;
            }
            $out[] = ['prelude' => $prelude, 'body' => substr($css, $i + 1, $j - $i - 2)];
            $i = $j; $start = $j; continue;
        }
        $i++;
    }
    return $out;
}

function splitTop(string $s, string $sep): array
{
    $parts = []; $depth = 0; $buf = ''; $q = null;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $c = $s[$i];
        if ($q !== null) { $buf .= $c; if ($c === $q) { $q = null; } continue; }
        if ($c === '"' || $c === "'") { $q = $c; $buf .= $c; continue; }
        if ($c === '(' || $c === '[') { $depth++; } elseif ($c === ')' || $c === ']') { $depth--; }
        if ($c === $sep && $depth === 0) { $parts[] = $buf; $buf = ''; continue; }
        $buf .= $c;
    }
    $parts[] = $buf;
    return $parts;
}

function scopeSelector(string $sel): string
{
    $sel = trim(preg_replace('/\s+/', ' ', $sel));
    if ($sel === ':root' || $sel === 'html') { return DARK; }
    if (preg_match('/^(:root|html)(?=[\s.:#\[>+~])/', $sel)) { return DARK . preg_replace('/^(:root|html)/', '', $sel); }
    return DARK . ' ' . $sel;
}

function convertBlocks(string $css): string
{
    $out = '';
    foreach (splitBlocks($css) as $block) {
        $p = $block['prelude'];
        if ($p === '') { continue; }
        if ($p[0] === '@') {
            if (preg_match('/^@(media|supports|layer)\b/i', $p)) {
                if (preg_match('/^@media\b/i', $p) && preg_match('/\bprint\b/i', $p) && !preg_match('/\bscreen\b/i', $p)) { continue; }
                $inner = convertBlocks($block['body']);
                if ($inner !== '') { $out .= $p . "{\n" . $inner . "}\n"; }
            }
            continue; // @keyframes, @font-face, @page – bez zmian
        }
        $decls = [];
        $changed = false;
        $parsed = [];
        $lightBg = false;
        foreach (splitTop($block['body'], ';') as $decl) {
            $decl = trim($decl);
            if ($decl === '' || strpos($decl, ':') === false) { continue; }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $role = roleFor($prop, $val);
            if ($role === null) { continue; }
            $parsed[] = [$prop, $val, $role];
            if ($role === 'bg' && strpos($prop, '--') !== 0) {
                // Kryjące tło akcentu, które się nie zmienia (np. żółty znaczek, fioletowy przycisk) –
                // tekst tej reguły był dobrany do niego, więc też zostaje.
                if (convertValue($val, 'bg') === $val && preg_match_all('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', $val, $bm)) {
                    foreach ($bm[0] as $bc) {
                        if (($rgb = parseColor($bc)) !== null && $rgb[3] > .5) { $lightBg = true; }
                    }
                }
            }
        }
        foreach ($parsed as [$prop, $val, $role]) {
            if ($role === 'text' && $lightBg) { $role = 'keep'; }
            $new = $role === 'keep' ? $val : convertValue($val, $role);
            if ($new !== $val) { $changed = true; }
            $decls[] = $prop . ':' . $new;
        }
        if (!$changed) {
            // Reguła bez zmian kolorów też musi wygrać z podniesionymi regułami o mniejszej sile,
            // więc kopiujemy ją, jeśli ustawia jakiekolwiek kolory.
            if ($decls === []) { continue; }
        }
        $selectors = array_map('scopeSelector', splitTop($p, ','));
        $out .= implode(',', $selectors) . '{' . implode(';', $decls) . "}\n";
    }
    return $out;
}

/* ---------- ręczne poprawki ---------- */

$manual = <<<'CSS'
/* ===== Ręczne poprawki trybu ciemnego ===== */
html[data-bs-theme=dark] {
  color-scheme: dark;
  --altreo-bg: #0b1020;
  --altreo-ink: #e2e8f0;
  --altreo-muted: #94a3b8;
  --altreo-glass: rgba(22, 29, 46, .82);
  --altreo-glass-strong: rgba(26, 34, 54, .92);
  --altreo-border: rgba(148, 163, 184, .16);
  --altreo-line: rgba(148, 163, 184, .16);
  --altreo-shadow: 0 18px 48px rgba(0, 0, 0, .35), inset 0 1px 0 rgba(255, 255, 255, .04);
  --bs-body-color: #e2e8f0;
  --bs-body-bg: #0b1020;
  --bs-border-color: rgba(148, 163, 184, .2);
  --bs-secondary-color: #94a3b8;
  --bs-tertiary-bg: #121a2c;
  --bs-secondary-bg: #182136;
  --bs-emphasis-color: #f8fafc;
}
html[data-bs-theme=dark] body {
  color: #e2e8f0;
  background:
    radial-gradient(circle at 82% 7%, rgba(99, 102, 241, .16), transparent 28rem),
    radial-gradient(circle at 27% 91%, rgba(14, 165, 233, .10), transparent 33rem),
    linear-gradient(145deg, #0b1020 0%, #0e1526 48%, #120f24 100%) fixed !important;
}
html[data-bs-theme=dark] body::before { opacity: .1; }
html[data-bs-theme=dark] .app-content-header h1,
html[data-bs-theme=dark] .app-content-header h2,
html[data-bs-theme=dark] .app-content-header h3,
html[data-bs-theme=dark] :where(h1, h2, h3, h4, h5, h6) { color: #f1f5f9; }
html[data-bs-theme=dark] .text-dark, html[data-bs-theme=dark] .text-body { color: #e2e8f0 !important; }
html[data-bs-theme=dark] .text-black-50 { color: rgba(255, 255, 255, .5) !important; }
html[data-bs-theme=dark] .bg-white { background-color: rgba(22, 29, 46, .82) !important; }
html[data-bs-theme=dark] .table { --bs-table-color: #e2e8f0; --bs-table-striped-color: #e2e8f0; --bs-table-hover-color: #f8fafc; --bs-table-striped-bg: rgba(255, 255, 255, .025); --bs-table-hover-bg: rgba(255, 255, 255, .05); }
html[data-bs-theme=dark] .table-light { --bs-table-color: #e2e8f0; --bs-table-bg: #182136; --bs-table-border-color: rgba(148, 163, 184, .2); }
html[data-bs-theme=dark] .btn-light { color: #e2e8f0; background-color: #1e293b; border-color: rgba(148, 163, 184, .25); }
html[data-bs-theme=dark] .btn-outline-dark { color: #e2e8f0; border-color: rgba(226, 232, 240, .4); }
html[data-bs-theme=dark] .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
html[data-bs-theme=dark] .modal-backdrop.show { opacity: .6; }
html[data-bs-theme=dark] code { color: #f9a8d4; }
html[data-bs-theme=dark] kbd { background: #334155; }
html[data-bs-theme=dark] hr { border-color: rgba(148, 163, 184, .3); }
html[data-bs-theme=dark] ::placeholder { color: #64748b; opacity: 1; }
html[data-bs-theme=dark] input[type=date]::-webkit-calendar-picker-indicator,
html[data-bs-theme=dark] input[type=datetime-local]::-webkit-calendar-picker-indicator,
html[data-bs-theme=dark] input[type=time]::-webkit-calendar-picker-indicator { filter: invert(.85); }
html[data-bs-theme=dark] select option { color: #e2e8f0; background: #161d2e; }
html[data-bs-theme=dark] * { scrollbar-color: rgba(148, 163, 184, .35) transparent; }
html[data-bs-theme=dark] img { color-scheme: normal; }
/* Licznik w zakładce dostaje tło z .om-tabs b, a kolor tekstu z .ms-count (dobrany do żółtego tła). */
html[data-bs-theme=dark] .om-tabs b { color: #c7d2fe; }

/* Przełącznik motywu */
.sc-theme-toggle { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; padding: 0; border: 1px solid transparent; border-radius: 11px; background: transparent; color: #475569; font-size: 1.05rem; cursor: pointer; transition: background .16s ease, color .16s ease; }
.sc-theme-toggle:hover { color: #4338ca; background: rgba(99, 102, 241, .08); }
.sc-theme-toggle .sc-theme-icon-dark { display: none; }
html[data-bs-theme=dark] .sc-theme-toggle { color: #fbbf24; }
html[data-bs-theme=dark] .sc-theme-toggle:hover { color: #fde68a; background: rgba(255, 255, 255, .08); }
html[data-bs-theme=dark] .sc-theme-toggle .sc-theme-icon-light { display: none; }
html[data-bs-theme=dark] .sc-theme-toggle .sc-theme-icon-dark { display: inline; }
.sc-theme-toggle-floating { position: fixed; top: 14px; right: 14px; z-index: 1000; background: rgba(255, 255, 255, .7); border-color: rgba(148, 163, 184, .3); }
html[data-bs-theme=dark] .sc-theme-toggle-floating { background: rgba(22, 29, 46, .85); border-color: rgba(148, 163, 184, .25); }
CSS;

/* ---------- budowanie ---------- */

$out = "/* PLIK GENEROWANY – nie edytuj ręcznie. Źródło: bin/build-dark-mode-css.php */\n";
foreach ($sources as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) { fwrite(STDERR, "Brak pliku: $rel\n"); continue; }
    $content = file_get_contents($path);
    if (substr($rel, -4) === '.tpl') {
        preg_match_all('~<style[^>]*>(.*?)</style>~s', $content, $m);
        $content = str_replace(['{literal}', '{/literal}'], '', implode("\n", $m[1]));
    }
    $converted = convertBlocks(stripComments($content));
    if ($converted !== '') { $out .= "\n/* --- $rel --- */\n" . $converted; }
}
$out .= "\n" . $manual . "\n";

$target = $root . '/dist/css/dark-mode.css';
file_put_contents($target, $out);
echo 'Zapisano ' . $target . ' (' . strlen($out) . " B)\n";
