<?php
/**
 * SimpleXLSX class
 * Simplified version of standard SimpleXLSX
 * License: MIT
 */

class SimpleXLSX
{
    public static function parse($filename, $is_data = false, $debug = false)
    {
        $xlsx = new self();
        $xlsx->debug = $debug;
        if ($is_data) {
            if ($xlsx->parseData($filename)) {
                return $xlsx;
            }
        } else {
            if ($xlsx->parseFile($filename)) {
                return $xlsx;
            }
        }
        return false;
    }
    protected $debug = false;
    protected $temp_files = [];
    protected $package = [];
    protected $sharedstrings = [];
    protected $sheets = [];
    protected $sheetNames = [];
    protected $styles = [];
    public function parseFile($filename)
    {
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }
        return $this->processZip($filename);
    }
    public function parseData($data)
    {
        $filename = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($filename, $data);
        $this->temp_files[] = $filename;
        return $this->processZip($filename);
    }
    protected function processZip($filename)
    {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filename) === true) {
                $this->package = [
                    'sharedStrings.xml' => $zip->getFromName('xl/sharedStrings.xml'),
                    'styles.xml' => $zip->getFromName('xl/styles.xml'),
                    'workbook.xml' => $zip->getFromName('xl/workbook.xml')
                ];
                if ($this->package['workbook.xml']) {
                    $this->sheets = []; // Reset
                    $this->sheetNames = [];
                    $simpleXml = simplexml_load_string($this->package['workbook.xml']);
                    if ($simpleXml->sheets) {
                        foreach ($simpleXml->sheets->sheet as $sheet) {
                            $this->sheetNames[(string) $sheet['name']] = (string) $sheet['sheetId'];
                            $rId = (string) $sheet->attributes('r', true)->id;
                            // rId logic to path can be complex, simplifying assuming standard layout
                            // Usually xl/worksheets/sheet{$sheetId}.xml or similar but rId maps to rels.
                            // For simplicity in this minified version, we try to scan the zip for `xl/worksheets/sheet*.xml`
                        }
                    }
                }
                // Extract sheet data
                for ($i = 1; $i <= 20; $i++) { // Try up to 20 sheets
                    $content = $zip->getFromName("xl/worksheets/sheet$i.xml");
                    if ($content)
                        $this->package['sheets'][$i] = $content;
                }
                $zip->close();
                return true;
            }
        }
        return false;
    }
    public function rows($worksheetIndex = 0)
    {
        // Simplified row extraction logic for standard xlsx (string based) since simplexml memory usage
        // This is a placeholder. For production, the user should download the full lib.
        // However, I will implement a basic robust reader here.
        if (!isset($this->package['sheets'][$worksheetIndex + 1]))
            return [];

        $xml = simplexml_load_string($this->package['sheets'][$worksheetIndex + 1]);
        $sharedStrings = [];
        if ($this->package['sharedStrings.xml']) {
            $ssXml = simplexml_load_string($this->package['sharedStrings.xml']);
            foreach ($ssXml->si as $val) {
                $sharedStrings[] = (string) $val->t;
            }
        }

        $rows = [];
        foreach ($xml->sheetData->row as $r) {
            $row = [];
            foreach ($r->c as $c) {
                $cellVal = (string) $c->v;
                if ((string) $c['t'] === 's') {
                    $cellVal = isset($sharedStrings[$cellVal]) ? $sharedStrings[$cellVal] : $cellVal;
                }
                $row[] = $cellVal;
            }
            $rows[] = $row;
        }
        return $rows;
    }
    // Helper to get worksheet names
    public function getSheetNames()
    {
        return array_keys($this->sheetNames);
    }
}
?>