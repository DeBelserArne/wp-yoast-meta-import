<?php

/**
 * SimpleXLSX — A lightweight XLSX spreadsheet reader.
 * Based on the work of Sergey Shuchkin (https://github.com/shuchkin/simplexlsx).
 *
 * Reads shared-strings and sheet data from an .xlsx file using ZipArchive + SimpleXML.
 * Licensed under the MIT License.
 */

namespace Shuchkin;

class SimpleXLSX
{

    public static $iconv = null;    // iconv encoding override (UTF-8 default)
    protected $sheets = [];
    protected $sharedStrings = [];
    protected $sheetData = [];
    protected static $parseError = '';

    /**
     * @param string $filename Path to .xlsx file
     */
    public function __construct($filename)
    {
        if (!class_exists('ZipArchive')) {
            self::$parseError = 'PHP ZipArchive class is not available.';
            return;
        }

        $zip = new \ZipArchive();
        $rc  = $zip->open($filename);
        if ($rc !== true) {
            self::$parseError = "Failed to open file as ZIP (code $rc).";
            return;
        }

        // Read shared strings
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $xml = simplexml_load_string($ss, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOENT);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    $val = '';
                    if (isset($si->t)) {
                        $val = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $val .= (string) ($r->t ?? '');
                        }
                    }
                    $this->sharedStrings[] = self::parseStr($val);
                }
            }
        }

        // Read all sheets
        $wb_xml = $zip->getFromName('xl/workbook.xml');
        $sheet_names = [];
        if ($wb_xml !== false) {
            $wb = simplexml_load_string($wb_xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOENT);
            if ($wb && isset($wb->sheets->sheet)) {
                foreach ($wb->sheets->sheet as $s) {
                    $sheet_names[] = (string) $s['name'];
                }
            }
        }

        // Read sheet data
        for ($i = 1; $i <= count($sheet_names); $i++) {
            $sheet_xml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($sheet_xml === false) {
                continue;
            }
            $xml = simplexml_load_string($sheet_xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOENT);
            if (!$xml || !isset($xml->sheetData->row)) {
                $this->sheets[] = [];
                continue;
            }

            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $v = '';
                    if (isset($c->v)) {
                        $v = (string) $c->v;
                    }
                    $t = (string) ($c['t'] ?? '');

                    if ($t === 's' && $v !== '') {
                        // Shared string
                        $v = $this->sharedStrings[(int) $v] ?? $v;
                    } elseif ($t === 'inlineStr') {
                        $v = (string) ($c->is->t ?? '');
                    }

                    $cells[] = self::parseStr($v);
                }
                $rows[] = $cells;
            }
            $this->sheets[] = $rows;
        }

        $zip->close();

        if (empty($this->sheets)) {
            self::$parseError = 'No sheet data found in file.';
        }
    }

    /**
     * Returns all rows from the first sheet.
     * @return array
     */
    public function rows($sheetIndex = 0)
    {
        return $this->sheets[$sheetIndex] ?? [];
    }

    /**
     * Returns all sheets.
     * @return array
     */
    public function sheets()
    {
        return $this->sheets;
    }

    /**
     * Returns the last parse error.
     * @return string
     */
    public static function parseError()
    {
        return self::$parseError;
    }

    protected static function parseStr($str)
    {
        if (self::$iconv !== null && function_exists('iconv')) {
            $str = iconv(self::$iconv, 'UTF-8', $str);
        }
        return $str;
    }
}
