<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use RuntimeException;
use ZipArchive;

/**
 * Odczytuje pierwszy arkusz pliku od ksiegowej i zwraca liste wierszy jako tablice
 * asocjacyjne kluczowane znormalizowana nazwa naglowka z pierwszego wiersza.
 *
 * Obsluguje trzy formaty spotykane w eksportach z programow ksiegowych:
 * - XLSX (Office Open XML, archiwum ZIP),
 * - XLS (stary format binarny BIFF8 / OLE2 Compound File),
 * - pliki z rozszerzeniem .xls, ktore w rzeczywistosci sa tabela HTML lub CSV
 *   (tak niektore programy ksiegowe "eksportuja do Excela").
 */
class AccountingWarehouseXlsxReader
{
    public function readFirstSheetAsAssoc(string $binary): array
    {
        $rows = $this->readRawRows($binary);

        if ($rows === array()) {
            return array();
        }

        $headerRow = array_shift($rows);
        $headers = array();
        foreach ($headerRow as $columnIndex => $headerValue) {
            $headers[$columnIndex] = $this->normalizeHeader((string) $headerValue);
        }

        $result = array();
        foreach ($rows as $row) {
            $isEmpty = true;
            $assoc = array();
            foreach ($headers as $columnIndex => $headerKey) {
                if ($headerKey === '') {
                    continue;
                }
                $value = trim((string) ($row[$columnIndex] ?? ''));
                if ($value !== '') {
                    $isEmpty = false;
                }
                $assoc[$headerKey] = $value;
            }
            if ($isEmpty) {
                continue;
            }
            $result[] = $assoc;
        }

        return $result;
    }

    /**
     * Zwraca surowe wiersze (bez interpretacji naglowka) - pierwszy wiersz to naglowki.
     */
    private function readRawRows(string $binary): array
    {
        if ($binary === '') {
            return array();
        }

        if (substr($binary, 0, 2) === 'PK') {
            return $this->readXlsxRows($binary);
        }

        if (substr($binary, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return $this->readBiffRows($binary);
        }

        $trimmedStart = ltrim($binary);
        if (stripos($trimmedStart, '<') === 0) {
            return $this->readHtmlRows($binary);
        }

        return $this->readDelimitedRows($binary);
    }

    // ------------------------------------------------------------------
    // XLSX (Office Open XML)
    // ------------------------------------------------------------------

    private function readXlsxRows(string $xlsxBinary): array
    {
        if (!class_exists('\ZipArchive')) {
            throw new RuntimeException('Brak rozszerzenia ZipArchive na serwerze.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'aw_xlsx_read_');
        if ($tempFile === false) {
            throw new RuntimeException('Nie udalo sie utworzyc pliku tymczasowego do odczytu XLSX.');
        }

        if (file_put_contents($tempFile, $xlsxBinary) === false) {
            @unlink($tempFile);
            throw new RuntimeException('Nie udalo sie zapisac pliku tymczasowego do odczytu XLSX.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($tempFile) !== true) {
                throw new RuntimeException('Nie udalo sie otworzyc pliku XLSX - upewnij sie, ze to poprawny plik .xlsx.');
            }

            $sharedStrings = $this->readSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            if ($sheetXml === false) {
                throw new RuntimeException('Nie znaleziono arkusza w pliku XLSX.');
            }

            return $this->parseSheetRows($sheetXml, $sharedStrings);
        } finally {
            @unlink($tempFile);
        }
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return array();
        }

        $document = new DOMDocument();
        $document->loadXML($xml, LIBXML_NOCDATA);
        $strings = array();
        foreach ($document->getElementsByTagName('si') as $siNode) {
            $text = '';
            foreach ($siNode->getElementsByTagName('t') as $tNode) {
                $text .= (string) $tNode->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function parseSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $document = new DOMDocument();
        $document->loadXML($sheetXml, LIBXML_NOCDATA);

        $rows = array();
        foreach ($document->getElementsByTagName('row') as $rowNode) {
            $rowValues = array();
            foreach ($rowNode->getElementsByTagName('c') as $cellNode) {
                $cellRef = (string) $cellNode->getAttribute('r');
                $columnIndex = $this->columnIndexFromCellRef($cellRef);
                $cellType = (string) $cellNode->getAttribute('t');

                $value = '';
                if ($cellType === 'inlineStr') {
                    foreach ($cellNode->getElementsByTagName('t') as $tNode) {
                        $value .= (string) $tNode->textContent;
                    }
                } else {
                    $vNodes = $cellNode->getElementsByTagName('v');
                    $raw = $vNodes->length > 0 ? (string) $vNodes->item(0)->textContent : '';
                    if ($cellType === 's') {
                        $index = (int) $raw;
                        $value = $sharedStrings[$index] ?? '';
                    } else {
                        $value = $raw;
                    }
                }

                $rowValues[$columnIndex] = $value;
            }

            if ($rowValues === array()) {
                continue;
            }

            $maxIndex = max(array_keys($rowValues));
            $normalizedRow = array();
            for ($i = 0; $i <= $maxIndex; $i++) {
                $normalizedRow[$i] = $rowValues[$i] ?? '';
            }
            $rows[] = $normalizedRow;
        }

        return $rows;
    }

    private function columnIndexFromCellRef(string $cellRef): int
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $cellRef) ?? '';
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return max(0, $index - 1);
    }

    // ------------------------------------------------------------------
    // XLS (BIFF8 w kontenerze OLE2 Compound File)
    // ------------------------------------------------------------------

    private function readBiffRows(string $binary): array
    {
        $ole = new OleCompoundFileReader($binary);
        $workbookStream = $ole->getStream('Workbook') ?? $ole->getStream('Book');
        if ($workbookStream === null) {
            throw new RuntimeException('Nie znaleziono arkusza (strumienia Workbook) w pliku XLS.');
        }

        return $this->parseBiffWorkbook($workbookStream);
    }

    private function parseBiffWorkbook(string $stream): array
    {
        $length = strlen($stream);
        $offset = 0;

        $sharedStrings = array();
        $sheetOffsets = array();
        $firstSheetOffset = null;

        // Faza 1: Workbook Globals - zbieramy SST (wspolne teksty) oraz BOUNDSHEET (offsety arkuszy).
        while ($offset + 4 <= $length) {
            $recordType = unpack('v', substr($stream, $offset, 2))[1];
            $recordLength = unpack('v', substr($stream, $offset + 2, 2))[1];
            $dataStart = $offset + 4;
            $data = substr($stream, $dataStart, $recordLength);

            if ($recordType === 0x00FC) { // SST
                list($sharedStrings, $consumed) = $this->parseSst($stream, $dataStart, $recordLength);
                $offset = $consumed;
                continue;
            }

            if ($recordType === 0x0085) { // BOUNDSHEET
                if (strlen($data) >= 6) {
                    $streamPos = unpack('V', substr($data, 0, 4))[1];
                    $sheetType = ord($data[5]) & 0x06;
                    if ($firstSheetOffset === null && $sheetType === 0x00) {
                        $firstSheetOffset = $streamPos;
                    }
                    $sheetOffsets[] = $streamPos;
                }
            }

            if ($recordType === 0x000A) { // EOF of workbook globals substream
                $offset += 4 + $recordLength;
                break;
            }

            $offset += 4 + $recordLength;
        }

        if ($firstSheetOffset === null) {
            $firstSheetOffset = $sheetOffsets[0] ?? null;
        }
        if ($firstSheetOffset === null) {
            throw new RuntimeException('Nie znaleziono arkusza w pliku XLS.');
        }

        return $this->parseBiffSheet($stream, $firstSheetOffset, $sharedStrings);
    }

    /**
     * @return array{0: array<int, string>, 1: int} lista wspolnych tekstow oraz offset za rekordem SST (wraz z CONTINUE)
     */
    private function parseSst(string $stream, int $dataStart, int $firstRecordLength): array
    {
        $length = strlen($stream);
        // Zbieramy dane rekordu SST razem ze wszystkimi bezposrednio nastepujacymi rekordami CONTINUE
        // w jeden ciagly bufor - kazdy segment CONTINUE zaczyna sie od wlasnego bajtu flag dla stringa,
        // ktory jest kontynuowany, wiec zapamietujemy granice segmentow.
        $segments = array(substr($stream, $dataStart, $firstRecordLength));
        $segmentBounds = array(array($dataStart, $dataStart + $firstRecordLength));
        $cursor = $dataStart + $firstRecordLength;

        while ($cursor + 4 <= $length) {
            $recordType = unpack('v', substr($stream, $cursor, 2))[1];
            if ($recordType !== 0x003C) { // CONTINUE
                break;
            }
            $recordLength = unpack('v', substr($stream, $cursor + 2, 2))[1];
            $segStart = $cursor + 4;
            $segments[] = substr($stream, $segStart, $recordLength);
            $segmentBounds[] = array($segStart, $segStart + $recordLength);
            $cursor = $segStart + $recordLength;
        }

        $strings = $this->decodeSstSegments($segments);

        return array($strings, $cursor);
    }

    /**
     * @param string[] $segments
     * @return string[]
     */
    private function decodeSstSegments(array $segments): array
    {
        if ($segments === array()) {
            return array();
        }

        $first = $segments[0];
        if (strlen($first) < 8) {
            return array();
        }
        $uniqueCount = unpack('V', substr($first, 4, 4))[1];

        $segIndex = 0;
        $segData = substr($first, 8);
        $segPos = 0;
        $segCount = count($segments);

        $nextByte = function () use (&$segIndex, &$segData, &$segPos, &$segCount, $segments) {
            while ($segPos >= strlen($segData)) {
                $segIndex++;
                if ($segIndex >= $segCount) {
                    return null;
                }
                $segData = $segments[$segIndex];
                $segPos = 0;
            }
            $byte = $segData[$segPos];
            $segPos++;
            return $byte;
        };

        $readBytes = function (int $count) use ($nextByte) {
            $out = '';
            for ($i = 0; $i < $count; $i++) {
                $byte = $nextByte();
                if ($byte === null) {
                    return null;
                }
                $out .= $byte;
            }
            return $out;
        };

        $strings = array();
        for ($i = 0; $i < $uniqueCount; $i++) {
            $lenBytes = $readBytes(2);
            if ($lenBytes === null) {
                break;
            }
            $charCount = unpack('v', $lenBytes)[1];
            $flagsByte = $nextByte();
            if ($flagsByte === null) {
                break;
            }
            $flags = ord($flagsByte);
            $isUnicode = ($flags & 0x01) !== 0;
            $hasRichText = ($flags & 0x08) !== 0;
            $hasFarEast = ($flags & 0x04) !== 0;

            $richTextRuns = 0;
            if ($hasRichText) {
                $rtBytes = $readBytes(2);
                $richTextRuns = $rtBytes !== null ? unpack('v', $rtBytes)[1] : 0;
            }
            $farEastSize = 0;
            if ($hasFarEast) {
                $feBytes = $readBytes(4);
                $farEastSize = $feBytes !== null ? unpack('V', $feBytes)[1] : 0;
            }

            $text = '';
            if ($isUnicode) {
                $raw = $readBytes($charCount * 2);
                if ($raw !== null) {
                    $text = @iconv('UTF-16LE', 'UTF-8//IGNORE', $raw) ?: '';
                }
            } else {
                $raw = $readBytes($charCount);
                if ($raw !== null) {
                    $text = @iconv('CP1250', 'UTF-8//IGNORE', $raw) ?: $raw;
                }
            }

            if ($hasRichText) {
                $readBytes($richTextRuns * 4);
            }
            if ($hasFarEast) {
                $readBytes($farEastSize);
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function parseBiffSheet(string $stream, int $sheetOffset, array $sharedStrings): array
    {
        $length = strlen($stream);
        $offset = $sheetOffset;
        $rows = array();

        // Pomijamy BOF arkusza.
        if ($offset + 4 <= $length) {
            $recordLength = unpack('v', substr($stream, $offset + 2, 2))[1];
            $offset += 4 + $recordLength;
        }

        while ($offset + 4 <= $length) {
            $recordType = unpack('v', substr($stream, $offset, 2))[1];
            $recordLength = unpack('v', substr($stream, $offset + 2, 2))[1];
            $data = substr($stream, $offset + 4, $recordLength);

            if ($recordType === 0x000A) { // EOF
                break;
            }

            switch ($recordType) {
                case 0x0204: // LABEL
                    if (strlen($data) >= 6) {
                        $rowIdx = unpack('v', substr($data, 0, 2))[1];
                        $colIdx = unpack('v', substr($data, 2, 2))[1];
                        $strLen = unpack('v', substr($data, 6, 2))[1] ?? 0;
                        $flags = ord($data[8] ?? "\0");
                        $rest = substr($data, 9);
                        if (($flags & 0x01) !== 0) {
                            $raw = substr($rest, 0, $strLen * 2);
                            $text = @iconv('UTF-16LE', 'UTF-8//IGNORE', $raw) ?: '';
                        } else {
                            $raw = substr($rest, 0, $strLen);
                            $text = @iconv('CP1250', 'UTF-8//IGNORE', $raw) ?: $raw;
                        }
                        $rows[$rowIdx][$colIdx] = $text;
                    }
                    break;

                case 0x00FD: // LABELSST
                    if (strlen($data) >= 10) {
                        $rowIdx = unpack('v', substr($data, 0, 2))[1];
                        $colIdx = unpack('v', substr($data, 2, 2))[1];
                        $sstIndex = unpack('V', substr($data, 6, 4))[1];
                        $rows[$rowIdx][$colIdx] = $sharedStrings[$sstIndex] ?? '';
                    }
                    break;

                case 0x0203: // NUMBER
                    if (strlen($data) >= 14) {
                        $rowIdx = unpack('v', substr($data, 0, 2))[1];
                        $colIdx = unpack('v', substr($data, 2, 2))[1];
                        $value = unpack('d', substr($data, 6, 8))[1];
                        $rows[$rowIdx][$colIdx] = $this->formatNumber($value);
                    }
                    break;

                case 0x027E: // RK
                    if (strlen($data) >= 10) {
                        $rowIdx = unpack('v', substr($data, 0, 2))[1];
                        $colIdx = unpack('v', substr($data, 2, 2))[1];
                        $value = $this->decodeRk(substr($data, 6, 4));
                        $rows[$rowIdx][$colIdx] = $this->formatNumber($value);
                    }
                    break;

                case 0x00BD: // MULRK
                    if (strlen($data) >= 6) {
                        $rowIdx = unpack('v', substr($data, 0, 2))[1];
                        $firstCol = unpack('v', substr($data, 2, 2))[1];
                        $lastCol = unpack('v', substr($data, strlen($data) - 2, 2))[1];
                        $body = substr($data, 4, strlen($data) - 6);
                        $colCount = $lastCol - $firstCol + 1;
                        for ($i = 0; $i < $colCount; $i++) {
                            $chunk = substr($body, $i * 6, 6);
                            if (strlen($chunk) < 6) {
                                break;
                            }
                            $value = $this->decodeRk(substr($chunk, 2, 4));
                            $rows[$rowIdx][$firstCol + $i] = $this->formatNumber($value);
                        }
                    }
                    break;

                case 0x0006: // FORMULA - uzywamy cached result jesli to liczba lub string flag
                    if (strlen($data) >= 14) {
                        $rowIdx = unpack('v', substr($data, 0, 2))[1];
                        $colIdx = unpack('v', substr($data, 2, 2))[1];
                        $resultBytes = substr($data, 6, 8);
                        if (substr($resultBytes, 6, 2) === "\xFF\xFF" && ord($resultBytes[0]) === 0x00) {
                            // Wynik tekstowy - wartosc jest w nastepnym rekordzie STRING.
                            $next = $offset + 4 + $recordLength;
                            if ($next + 4 <= $length) {
                                $nextType = unpack('v', substr($stream, $next, 2))[1];
                                $nextLen = unpack('v', substr($stream, $next + 2, 2))[1];
                                if ($nextType === 0x0207) { // STRING
                                    $nextData = substr($stream, $next + 4, $nextLen);
                                    $charCount = unpack('v', substr($nextData, 0, 2))[1];
                                    $flags = ord($nextData[2] ?? "\0");
                                    $rest = substr($nextData, 3);
                                    if (($flags & 0x01) !== 0) {
                                        $raw = substr($rest, 0, $charCount * 2);
                                        $text = @iconv('UTF-16LE', 'UTF-8//IGNORE', $raw) ?: '';
                                    } else {
                                        $raw = substr($rest, 0, $charCount);
                                        $text = @iconv('CP1250', 'UTF-8//IGNORE', $raw) ?: $raw;
                                    }
                                    $rows[$rowIdx][$colIdx] = $text;
                                }
                            }
                        } elseif (!(ord($resultBytes[6]) === 0xFF && ord($resultBytes[7]) === 0xFF)) {
                            $value = unpack('d', $resultBytes)[1];
                            $rows[$rowIdx][$colIdx] = $this->formatNumber($value);
                        }
                    }
                    break;

                default:
                    break;
            }

            $offset += 4 + $recordLength;
        }

        if ($rows === array()) {
            return array();
        }

        $maxRowIndex = max(array_keys($rows));
        $result = array();
        for ($r = 0; $r <= $maxRowIndex; $r++) {
            if (!isset($rows[$r])) {
                continue;
            }
            $rowValues = $rows[$r];
            $maxColIndex = max(array_keys($rowValues));
            $normalizedRow = array();
            for ($c = 0; $c <= $maxColIndex; $c++) {
                $normalizedRow[$c] = (string) ($rowValues[$c] ?? '');
            }
            $result[] = $normalizedRow;
        }

        return $result;
    }

    private function decodeRk(string $rkBytes): float
    {
        $rk = unpack('V', $rkBytes)[1];
        $isInt = ($rk & 0x02) !== 0;
        $is100 = ($rk & 0x01) !== 0;

        if ($isInt) {
            $signedRk = $rk > 0x7FFFFFFF ? $rk - 0x100000000 : $rk;
            $number = (float) ($signedRk >> 2);
        } else {
            $bytes = "\x00\x00\x00\x00" . pack('V', $rk & 0xFFFFFFFC);
            $number = unpack('d', $bytes)[1];
        }

        if ($is100) {
            $number /= 100;
        }

        return $number;
    }

    private function formatNumber(float $value): string
    {
        if ($value == (int) $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    // ------------------------------------------------------------------
    // HTML (tabela) lub CSV/TSV - popularny "eksport do Excela" z rozszerzeniem .xls
    // ------------------------------------------------------------------

    private function readHtmlRows(string $binary): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $binary);
        libxml_use_internal_errors($previous);

        $table = $document->getElementsByTagName('table')->item(0);
        if ($table === null) {
            return $this->readDelimitedRows($binary);
        }

        $rows = array();
        foreach ($table->getElementsByTagName('tr') as $trNode) {
            $rowValues = array();
            $columnIndex = 0;
            foreach ($trNode->childNodes as $cellNode) {
                if (!in_array(strtolower((string) $cellNode->nodeName), array('td', 'th'), true)) {
                    continue;
                }
                $rowValues[$columnIndex] = trim((string) $cellNode->textContent);
                $columnIndex++;
            }
            if ($rowValues !== array()) {
                $rows[] = $rowValues;
            }
        }

        return $rows;
    }

    private function readDelimitedRows(string $binary): array
    {
        $text = $binary;
        if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
            $text = substr($text, 3);
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @iconv('CP1250', 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array_values(array_filter($lines, static function (string $line): bool {
            return trim($line) !== '';
        }));

        if ($lines === array()) {
            return array();
        }

        $delimiter = "\t";
        $firstLine = $lines[0];
        if (strpos($firstLine, "\t") === false) {
            $delimiter = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';
        }

        $rows = array();
        foreach ($lines as $line) {
            $rows[] = str_getcsv($line, $delimiter, '"', '\\');
        }

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        if ($header === '') {
            return '';
        }

        $map = array(
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
            'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
        );

        $normalized = strtr($header, $map);
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }
}
