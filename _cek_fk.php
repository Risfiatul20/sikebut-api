<?php
ini_set("memory_limit","-1");
ini_set("max_execution_time","300");
require __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Database\Capsule\Manager as Capsule;

// Koneksi DB
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'pgsql', 'host' => '103.22.250.210', 'port' => '54320',
    'database' => 'sikebut', 'username' => 'sipedal', 'password' => '@Password2026',
    'charset' => 'utf8', 'schema' => 'dev',
]);
$capsule->setAsGlobal(); $capsule->bootEloquent();
$pdo = $capsule->getConnection()->getPdo();

$path = 'D:/Magang BPBJ/sikebut/13_Provinsi Sumatera Barat_Rekap_Ver4_Penetapan APBD 2026_Terkunci.xlsx';
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$reader->setReadEmptyCells(false);
$spreadsheet = $reader->load($path);
$ws = $spreadsheet->getSheet(0);
$highestRow = $ws->getHighestDataRow();

$subUnits = []; $subKegs = []; $standars = [];
for ($r = 2; $r <= $highestRow; $r++) {
    $su = trim((string)$ws->getCell('I'.$r)->getValue());
    $sk = trim((string)$ws->getCell('Q'.$r)->getValue());
    $sh = trim((string)$ws->getCell('W'.$r)->getValue());
    if ($su !== '') $subUnits[$su] = true;
    if ($sk !== '') $subKegs[$sk] = true;
    if ($sh !== '') $standars[$sh] = true;
}
echo "Kode unik di file: sub_unit=".count($subUnits)." sub_kegiatan=".count($subKegs)." standar_harga=".count($standars)."\n";

function missing($pdo, $table, $col, array $keys) {
    if (!$keys) return 0;
    $chunks = array_chunk(array_keys($keys), 500);
    $missing = [];
    foreach ($chunks as $chunk) {
        $in = implode(',', array_map(fn($k) => $pdo->quote($k), $chunk));
        $rows = $pdo->query("SELECT $col FROM dev.$table WHERE $col IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        $found = array_flip($rows);
        foreach ($chunk as $k) if (!isset($found[$k])) $missing[$k] = true;
    }
    return count($missing);
}
echo "TIDAK ada di ref_skpd: ".missing($pdo, 'ref_skpd', 'kode_skpd', $subUnits)."\n";
echo "TIDAK ada di ref_sub_kegiatan: ".missing($pdo, 'ref_sub_kegiatan', 'kode_sub_kegiatan', $subKegs)."\n";
echo "TIDAK ada di ref_standar_harga: ".missing($pdo, 'ref_standar_harga', 'kode_standar_harga', $standars)."\n";
