<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RkbmdPemeliharaanResource;
use App\Models\RefKegiatan;
use App\Models\RefProgram;
use App\Models\RefSkpd;
use App\Models\RefSubKegiatan;
use App\Models\RkbmdPemeliharaan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class RkbmdPemeliharaanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = RkbmdPemeliharaan::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_barang', 'ilike', "%{$search}%")
                    ->orWhere('kode_fikasi', 'ilike', "%{$search}%")
                    ->orWhere('nama_skpd', 'ilike', "%{$search}%")
                    ->orWhere('nama_pemeliharaan', 'ilike', "%{$search}%");
            });
        }

        if ($kodeSkpd = $request->query('kode_skpd')) {
            $query->where('kode_skpd', $kodeSkpd);
        }

        // Filter by sub kegiatan (kolom sumber: kode_sub_kegiatan) — dipakai popup RKBMD di form Identifikasi.
        if ($kodeSubKegiatan = $request->query('kode_sub_kegiatan')) {
            $query->where('kode_sub_kegiatan', $kodeSubKegiatan);
        }

        if ($periode = $request->query('periode')) {
            $query->where('periode', (int) $periode);
        }

        $perPage = (int) $request->query('per_page', 15);

        if ($perPage > 0) {
            return RkbmdPemeliharaanResource::collection($query->paginate($perPage));
        }

        return RkbmdPemeliharaanResource::collection($query->get());
    }

    public function show(int $id): RkbmdPemeliharaanResource
    {
        $data = RkbmdPemeliharaan::findOrFail($id);

        return new RkbmdPemeliharaanResource($data);
    }

    /**
     * Tambah data RKBMD Pemeliharaan secara manual (tanpa file Excel).
     * Dipakai untuk uji & kelengkapan data di menu RKBMD Pemeliharaan.
     */
    public function store(Request $request): RkbmdPemeliharaanResource
    {
        $validated = $request->validate([
            'nama_barang' => ['required', 'string', 'max:255'],
            'kode_fikasi' => ['nullable', 'string', 'max:50'],
            'jumlah_barang' => ['required', 'integer', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'kondisi_b' => ['nullable', 'integer', 'min:0'],
            'kondisi_rr' => ['nullable', 'integer', 'min:0'],
            'kondisi_rb' => ['nullable', 'integer', 'min:0'],
            'nama_pemeliharaan' => ['nullable', 'string', 'max:255'],
            'jumlah_pemeliharaan' => ['nullable', 'integer', 'min:0'],
            'satuan_pemeliharaan' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'kode_skpd' => ['required', 'string', 'max:50'],
            'kode_sub_kegiatan' => ['required', 'string', 'max:50'],
            'periode' => ['required', 'integer', 'in:2025,2026,2027'],
        ]);

        // Auto-fill nama SKPD dari referensi
        $skpd = RefSkpd::find($validated['kode_skpd']);
        $sub = RefSubKegiatan::find($validated['kode_sub_kegiatan']);
        $kegiatan = $sub ? RefKegiatan::find($sub->kode_kegiatan) : null;
        $program = $kegiatan ? RefProgram::find($kegiatan->kode_program) : null;

        if (! $skpd) {
            throw ValidationException::withMessages(['kode_skpd' => 'Kode SKPD tidak ditemukan di referensi.']);
        }
        if (! $sub) {
            throw ValidationException::withMessages(['kode_sub_kegiatan' => 'Kode sub kegiatan tidak ditemukan di referensi.']);
        }

        // ID manual karena tabel tidak auto-increment
        $nextId = (int) RkbmdPemeliharaan::max('id_pemeliharaan') + 1;

        $data = RkbmdPemeliharaan::create([
            'id_pemeliharaan' => $nextId,
            'kode_fikasi' => $validated['kode_fikasi'] ?? null,
            'nama_barang' => $validated['nama_barang'],
            'jumlah_barang' => $validated['jumlah_barang'],
            'satuan' => $validated['satuan'] ?? null,
            'kondisi_b' => $validated['kondisi_b'] ?? 0,
            'kondisi_rr' => $validated['kondisi_rr'] ?? 0,
            'kondisi_rb' => $validated['kondisi_rb'] ?? 0,
            'nama_pemeliharaan' => $validated['nama_pemeliharaan'] ?? null,
            'jumlah_pemeliharaan' => $validated['jumlah_pemeliharaan'] ?? 0,
            'satuan_pemeliharaan' => $validated['satuan_pemeliharaan'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
            'periode' => $validated['periode'],
            'nm_status' => 'Ditetapkan',
            'id_status' => 3,
            'kode_skpd' => $validated['kode_skpd'],
            'nama_skpd' => $skpd->nama_skpd,
            'kode_sub_skpd' => $skpd->parent_kode_skpd ?? null,
            'kode_program' => $kegiatan->kode_program ?? null,
            'nama_program' => $program->nama_program ?? null,
            'kode_kegiatan' => $sub->kode_kegiatan ?? null,
            'nama_giat_nama_giat' => $kegiatan->nama_kegiatan ?? null,
            'kode_sub_kegiatan' => $sub->kode_sub_kegiatan,
            'nama_sub_giat_nama_sub_giat' => $sub->nama_sub_kegiatan,
        ]);

        return new RkbmdPemeliharaanResource($data);
    }
}
