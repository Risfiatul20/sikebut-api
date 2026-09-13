<?php

ini_set('memory_limit', '-1');
ini_set('max_execution_time', '300');
require __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'D:/Magang BPBJ/sikebut/13_Provinsi Sumatera Barat_Rekap_Ver4_Penetapan APBD 2026_Terkunci.xlsx';
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$reader->setReadEmptyCells(false);
$spreadsheet = $reader->load($path);

echo "=== SHEETS ===\n";
foreach ($spreadsheet->getSheetNames() as $i => $name) {
    $ws = $spreadsheet->getSheet($i);
    echo "Sheet[$i]: '$name' — baris: ".$ws->getHighestDataRow().', kolom: '.$ws->getHighestDataColumn()."\n";
}

// Ambil sheet pertama saja untuk inspeksi header & 5 baris pertama
$ws = $spreadsheet->getSheet(0);
echo "\n=== SHEET PERTAMA: '".$ws->getTitle()."' ===\n";
$highestCol = $ws->getHighestDataColumn();
$highestRow = $ws->getHighestDataRow();
for ($r = 1; $r <= min(6, $highestRow); $r++) {
    $vals = [];
    for ($c = 'A'; $c <= $highestCol; $c++) {
        $v = $ws->getCell($c.$r)->getValue();
        if (is_object($v) && method_exists($v, 'getPlainText')) {
            $v = $v->getPlainText();
        }
        if ($v !== null && trim((string) $v) !== '') {
            $vals[] = "$c=".mb_substr((string) $v, 0, 45);
        }
    }
    echo "Row $r: ".implode(' | ', $vals)."\n";
}
