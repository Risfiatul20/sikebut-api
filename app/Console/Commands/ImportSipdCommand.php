<?php

namespace App\Console\Commands;

use App\Imports\SipdPenetapanApbdImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportSipdCommand extends Command
{
    protected $signature = 'sipd:import 
                            {file : Path ke file excel SIPD} 
                            {--tahun= : Tahun anggaran (opsional)} 
                            {--versi= : Versi data SIPD (opsional)}';

    protected $description = 'Import data SIPD Penetapan APBD dan master referensi dari Excel';

    public function handle(): int
    {
        $file = $this->argument('file');
        $tahun = $this->option('tahun') ? (int) $this->option('tahun') : null;
        $versi = $this->option('versi') ?: null;

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $this->info("Memulai import file: {$file}");
        if ($tahun) {
            $this->line("Tahun: {$tahun}");
        }
        if ($versi) {
            $this->line("Versi: {$versi}");
        }

        try {
            Excel::import(new SipdPenetapanApbdImport(null, $tahun, $versi), $file);
            $this->info('Import SIPD berhasil diselesaikan.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Gagal melakukan import: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
