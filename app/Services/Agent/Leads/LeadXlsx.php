<?php

namespace App\Services\Agent\Leads;

/** Reads the first sheet of an .xlsx file into CSV text (no library needed; needs PHP's zip extension). */
class LeadXlsx
{
    /** @throws \RuntimeException */
    public static function toCsv(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('this server has no zip support');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('not a valid .xlsx file');
        }
        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $doc = simplexml_load_string($xml);
                foreach ($doc->si ?? [] as $si) {
                    $t = '';
                    foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                        $t .= (string) $node;
                    }
                    $shared[] = $t;
                }
            }

            $sheetName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n) && ($sheetName === null || strnatcmp($n, $sheetName) < 0)) {
                    $sheetName = $n;
                }
            }
            if (! $sheetName || ($xml = $zip->getFromName($sheetName)) === false) {
                throw new \RuntimeException('no sheet found');
            }
        } finally {
            $zip->close();
        }

        $doc = simplexml_load_string($xml);
        $out = fopen('php://memory', 'r+');
        $width = 0;
        $rows = [];
        foreach ($doc->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::colIndex((string) $c['r']);
                $type = (string) $c['t'];
                if ($type === 's') {
                    $v = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $v = '';
                    foreach ($c->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                        $v .= (string) $node;
                    }
                } else {
                    $v = (string) $c->v;
                    if ($v !== '' && is_numeric($v) && stripos($v, 'e') !== false) {
                        $v = sprintf('%.0f', (float) $v); // long phone numbers saved as 2.34803E+12
                    }
                }
                $cells[$col] = trim($v);
                $width = max($width, $col + 1);
            }
            $rows[] = $cells;
        }
        foreach ($rows as $cells) {
            $line = [];
            for ($i = 0; $i < $width; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            fputcsv($out, $line, ',', '"', '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private static function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/i', $ref, $m);
        $n = 0;
        foreach (str_split(strtoupper($m[1] ?? 'A')) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
