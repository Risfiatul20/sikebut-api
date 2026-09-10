<?php
ini_set("memory_limit","-1");
ini_set("max_execution_time","300");
require __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'D:/Magang BPBJ/sikebut/13_Provinsi Sumatera Barat_Rekap_Ver4_Penetapan APBD 2026_Terkunci.xlsx';
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$reader->setReadEmptyCells(false);
$spreadsheet = $reader->load($path);
$ws = $spreadsheet->getSheet(0);
$highestRow = $ws->getHighestDataRow();

echo "=== 3 BARIS TERAKHIR (cek baris JUMLAH) ===\n";
for ($r = $highestRow-2; $r <= $highestRow; $r++) {
    $q = $ws->getCell('Q'.$r)->getValue();
    $w = $ws->getCell('W'.$r)->getValue();
    $y = $ws->getCell('Y'.$r)->getValue();
    $n = $ws->getCell('N'.$r)->getValue();
    if (is_object($n)) $n = $n->getPlainText();
    echo "Row $r: sub_kegiatan=".var_export($q,true)." standar_harga=".var_export($w,true)." pagu=".var_export($y,true)." program=".mb_substr((string)$n,0,30)."\n";
}

echo "\n=== STATISTIK FILE ===\n";
$withPagu = 0; $withStandar = 0; $withSubKeg = 0; $jumBaris = 0;
for ($r = 2; $r <= $highestRow; $r++) {
    $q = trim((string)$ws->getCell('Q'.$r)->getValue());
    $w = trim((string)$ws->getCell('W'.$r)->getValue());
    $y = $ws->getCell('Y'.$r)->getValue();
    if (is_object($y)) $y = $y->getPlainText();
    $y = (float) $y;
    if ($q !== '') $withSubKeg++;
    if ($w !== '') $withStandar++;
    if ($y > 0) $withPagu++;
    if ($q !== '' && $w !== '') $jumBaris++;
}
echo "Total baris data (tanpa header): ".($highestRow-1)."\n";
echo "Baris dgn sub_kegiatan: $withSubKeg\n";
echo "Baris dgn standar_harga: $withStandar\n";
echo "Baris dgn pagu > 0: $withPagu\n";
echo "Baris RINCIAN LENGKAP (sub+standar): $jumBaris\n";
