<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Minimalny odczyt kontenera OLE2 Compound File (CFBF), uzywanego m.in. przez
 * stary binarny format .xls, aby wyciagnac zawartosc pojedynczego strumienia
 * (np. "Workbook") po nazwie.
 */
class OleCompoundFileReader
{
    private const FREE_SECTOR = 0xFFFFFFFF;
    private const END_OF_CHAIN = 0xFFFFFFFE;
    private const SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /** @var string */
    private $data;

    /** @var int */
    private $sectorSize;

    /** @var int */
    private $miniSectorSize;

    /** @var int */
    private $miniStreamCutoff;

    /** @var int[] */
    private $fat;

    /** @var int[] */
    private $miniFat;

    /** @var string */
    private $miniStreamContainer;

    /** @var array<int, array{name: string, type: int, startSector: int, size: int}> */
    private $entries;

    public function __construct(string $data)
    {
        if (substr($data, 0, 8) !== self::SIGNATURE) {
            throw new RuntimeException('Nieprawidlowy naglowek pliku OLE2 (CFBF).');
        }
        $this->data = $data;

        $sectorShift = $this->readUInt16(30);
        $miniSectorShift = $this->readUInt16(32);
        $this->sectorSize = 1 << $sectorShift;
        $this->miniSectorSize = 1 << $miniSectorShift;
        $this->miniStreamCutoff = $this->readUInt32(56);

        $numFatSectors = $this->readUInt32(44);
        $dirStartSector = $this->readUInt32(48);
        $miniFatStartSector = $this->readUInt32(60);
        $numMiniFatSectors = $this->readUInt32(64);
        $difatStartSector = $this->readUInt32(68);
        $numDifatSectors = $this->readUInt32(72);

        $this->fat = $this->buildFat($numFatSectors, $difatStartSector, $numDifatSectors);
        $directoryStream = $this->readChain($dirStartSector);
        $this->entries = $this->parseDirectory($directoryStream);

        $rootEntry = $this->findRootEntry();
        $this->miniStreamContainer = $rootEntry !== null
            ? $this->readChain($rootEntry['startSector'], $rootEntry['size'])
            : '';

        $this->miniFat = $numMiniFatSectors > 0 ? $this->readFatSectorsChain($miniFatStartSector) : array();
    }

    public function getStream(string $name): ?string
    {
        foreach ($this->entries as $entry) {
            if ($entry['type'] !== 2) { // 2 = stream object
                continue;
            }
            if (strcasecmp($entry['name'], $name) === 0) {
                if ($entry['size'] < $this->miniStreamCutoff) {
                    return $this->readMiniChain($entry['startSector'], $entry['size']);
                }

                return $this->readChain($entry['startSector'], $entry['size']);
            }
        }

        return null;
    }

    /**
     * @return array{name: string, startSector: int, size: int}|null
     */
    private function findRootEntry(): ?array
    {
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 5) { // 5 = root storage
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return int[]
     */
    private function buildFat(int $numFatSectors, int $difatStartSector, int $numDifatSectors): array
    {
        $fatSectorIds = array();
        for ($i = 0; $i < 109; $i++) {
            $sectorId = $this->readUInt32(76 + $i * 4);
            if ($sectorId === self::FREE_SECTOR || count($fatSectorIds) >= $numFatSectors) {
                continue;
            }
            $fatSectorIds[] = $sectorId;
        }

        if ($numDifatSectors > 0) {
            $difatSector = $difatStartSector;
            while ($difatSector !== self::END_OF_CHAIN && $difatSector !== self::FREE_SECTOR) {
                $sectorData = $this->readSector($difatSector);
                $entriesPerSector = intdiv($this->sectorSize, 4) - 1;
                for ($i = 0; $i < $entriesPerSector; $i++) {
                    $sectorId = unpack('V', substr($sectorData, $i * 4, 4))[1];
                    if ($sectorId !== self::FREE_SECTOR && count($fatSectorIds) < $numFatSectors) {
                        $fatSectorIds[] = $sectorId;
                    }
                }
                $difatSector = unpack('V', substr($sectorData, $entriesPerSector * 4, 4))[1];
            }
        }

        $fat = array();
        $entriesPerSector = intdiv($this->sectorSize, 4);
        foreach ($fatSectorIds as $fatSectorId) {
            $sectorData = $this->readSector($fatSectorId);
            for ($i = 0; $i < $entriesPerSector; $i++) {
                $fat[] = unpack('V', substr($sectorData, $i * 4, 4))[1];
            }
        }

        return $fat;
    }

    /**
     * @return int[]
     */
    private function readFatSectorsChain(int $startSector): array
    {
        $stream = $this->readChain($startSector);
        $entries = array();
        $count = intdiv(strlen($stream), 4);
        for ($i = 0; $i < $count; $i++) {
            $entries[] = unpack('V', substr($stream, $i * 4, 4))[1];
        }

        return $entries;
    }

    private function readSector(int $sectorId): string
    {
        $offset = 512 + $sectorId * $this->sectorSize;

        return substr($this->data, $offset, $this->sectorSize);
    }

    private function readChain(int $startSector, ?int $size = null): string
    {
        $out = '';
        $sector = $startSector;
        $seen = array();
        while ($sector !== self::END_OF_CHAIN && $sector !== self::FREE_SECTOR && $sector >= 0) {
            if (isset($seen[$sector])) {
                break;
            }
            $seen[$sector] = true;
            $out .= $this->readSector($sector);
            $sector = $this->fat[$sector] ?? self::END_OF_CHAIN;
        }

        return $size !== null ? substr($out, 0, $size) : $out;
    }

    private function readMiniChain(int $startSector, int $size): string
    {
        $out = '';
        $sector = $startSector;
        $seen = array();
        while ($sector !== self::END_OF_CHAIN && $sector !== self::FREE_SECTOR && $sector >= 0) {
            if (isset($seen[$sector])) {
                break;
            }
            $seen[$sector] = true;
            $offset = $sector * $this->miniSectorSize;
            $out .= substr($this->miniStreamContainer, $offset, $this->miniSectorSize);
            $sector = $this->miniFat[$sector] ?? self::END_OF_CHAIN;
        }

        return substr($out, 0, $size);
    }

    /**
     * @return array<int, array{name: string, type: int, startSector: int, size: int}>
     */
    private function parseDirectory(string $directoryStream): array
    {
        $entries = array();
        $count = intdiv(strlen($directoryStream), 128);
        for ($i = 0; $i < $count; $i++) {
            $raw = substr($directoryStream, $i * 128, 128);
            if (strlen($raw) < 128) {
                continue;
            }
            $nameLen = unpack('v', substr($raw, 64, 2))[1];
            $type = ord($raw[66]);
            if ($type === 0) { // unused entry
                continue;
            }
            $nameBytes = substr($raw, 0, max(0, $nameLen - 2));
            $name = @iconv('UTF-16LE', 'UTF-8//IGNORE', $nameBytes) ?: '';
            $startSector = unpack('V', substr($raw, 116, 4))[1];
            $size = unpack('V', substr($raw, 120, 4))[1];

            $entries[] = array(
                'name' => $name,
                'type' => $type,
                'startSector' => $startSector,
                'size' => $size,
            );
        }

        return $entries;
    }

    private function readUInt16(int $offset): int
    {
        return unpack('v', substr($this->data, $offset, 2))[1];
    }

    private function readUInt32(int $offset): int
    {
        return unpack('V', substr($this->data, $offset, 4))[1];
    }
}
