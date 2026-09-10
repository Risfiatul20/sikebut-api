<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KegiatanResource;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\SubKegiatanResource;
use App\Models\RefKegiatan;
use App\Models\RefProgram;
use App\Models\RefSubKegiatan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class RefProgramController extends Controller
{
    /**
     * Sub kegiatan codes mapped to the logged-in user when their role is PPK.
     *
     * @return array<int, string>|null null = no scoping applied
     */
    private function ppkSubKegiatanCodes(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || strtoupper((string) $user->role) !== 'PPK') {
            return null;
        }

        return DB::table('dev.user_sub_kegiatan')
            ->where('user_id', $user->id)
            ->pluck('kode_sub_kegiatan')
            ->all();
    }

    /**
     * List program data with optional SKPD filter.
     */
    public function programs(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');
        $kodeSkpd = $request->query('kode_skpd');
        $kodeSubKegiatan = $request->query('kode_sub_kegiatan');
        $ppkCodes = $this->ppkSubKegiatanCodes($request);
        $perPage = (int) $request->query('per_page', 0);

        // Catatan: sengaja TANPA cache aplikasi (Cache::remember). Menyimpan object
        // Eloquent di cache file rawan error unserialize (__PHP_Incomplete_Class)
        // saat struktur model berubah. Tabel referensi ini kecil & sudah dilindungi
        // cache HTTP (ClientCache) + cache fetch Next.js.

        $query = RefProgram::with('bidangUrusan');

        if ($ppkCodes !== null) {
            $query->whereIn('kode_program', function ($q) use ($ppkCodes) {
                $q->select('kode_program')
                    ->from('dev.ref_kegiatan')
                    ->whereIn('kode_kegiatan', function ($q2) use ($ppkCodes) {
                        $q2->select('kode_kegiatan')
                            ->from('dev.ref_sub_kegiatan')
                            ->whereIn('kode_sub_kegiatan', $ppkCodes);
                    });
            });
        }

        if ($kodeSubKegiatan) {
            $query->whereIn('kode_program', function ($q) use ($kodeSubKegiatan) {
                $q->select('kode_program')
                    ->from('dev.ref_kegiatan')
                    ->whereIn('kode_kegiatan', function ($q2) use ($kodeSubKegiatan) {
                        $q2->select('kode_kegiatan')
                            ->from('dev.ref_sub_kegiatan')
                            ->where('kode_sub_kegiatan', $kodeSubKegiatan);
                    });
            });
        }

        if ($kodeSkpd) {
            $query->whereIn('kode_program', function ($q) use ($kodeSkpd) {
                $q->select('rk.kode_program')
                    ->from('dev.ref_kegiatan as rk')
                    ->whereIn('rk.kode_kegiatan', function ($q2) use ($kodeSkpd) {
                        $q2->select('rsk.kode_kegiatan')
                            ->from('dev.ref_sub_kegiatan as rsk')
                            ->whereIn('rsk.kode_sub_kegiatan', function ($q3) use ($kodeSkpd) {
                                $q3->select('kode_sub_kegiatan')
                                    ->from('dev.sipd_penetapan_apbd')
                                    ->where('kode_sub_unit', $kodeSkpd)
                                    ->where('pagu', '>', 0) // sub kegiatan dengan pagu kosong tidak ditampilkan
                                    ->whereNotNull('kode_sub_kegiatan');
                            });
                    });
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_program', 'ilike', "%{$search}%")
                    ->orWhere('nama_program', 'ilike', "%{$search}%");
            });
        }

        $query->orderBy('kode_program', 'asc');

        $data = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return ProgramResource::collection($data);
    }

    /**
     * List kegiatan data with optional SKPD filter.
     */
    public function kegiatans(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');
        $kodeSkpd = $request->query('kode_skpd');
        $kodeProgram = $request->query('kode_program');
        $kodeSubKegiatan = $request->query('kode_sub_kegiatan');
        $ppkCodes = $this->ppkSubKegiatanCodes($request);
        $perPage = (int) $request->query('per_page', 0);

        $query = RefKegiatan::query();

        if ($ppkCodes !== null) {
            $query->whereIn('kode_kegiatan', function ($q) use ($ppkCodes) {
                $q->select('kode_kegiatan')
                    ->from('dev.ref_sub_kegiatan')
                    ->whereIn('kode_sub_kegiatan', $ppkCodes);
            });
        }

        if ($kodeSubKegiatan) {
            $query->whereIn('kode_kegiatan', function ($q) use ($kodeSubKegiatan) {
                $q->select('kode_kegiatan')
                    ->from('dev.ref_sub_kegiatan')
                    ->where('kode_sub_kegiatan', $kodeSubKegiatan);
            });
        }

        if ($kodeSkpd) {
            $query->whereIn('kode_kegiatan', function ($q) use ($kodeSkpd) {
                $q->select('rsk.kode_kegiatan')
                    ->from('dev.ref_sub_kegiatan as rsk')
                    ->whereIn('rsk.kode_sub_kegiatan', function ($q2) use ($kodeSkpd) {
                        $q2->select('kode_sub_kegiatan')
                            ->from('dev.sipd_penetapan_apbd')
                            ->where('kode_sub_unit', $kodeSkpd)
                            ->where('pagu', '>', 0) // sub kegiatan dengan pagu kosong tidak ditampilkan
                            ->whereNotNull('kode_sub_kegiatan');
                    });
            });
        }

        if ($kodeProgram) {
            $query->where('kode_program', $kodeProgram);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_kegiatan', 'ilike', "%{$search}%")
                    ->orWhere('nama_kegiatan', 'ilike', "%{$search}%");
            });
        }

        $query->orderBy('kode_kegiatan', 'asc');

        $data = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return KegiatanResource::collection($data);
    }

    /**
     * List sub kegiatan data with optional SKPD filter.
     */
    public function subKegiatans(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');
        $kodeSkpd = $request->query('kode_skpd');
        $kodeKegiatan = $request->query('kode_kegiatan');
        $ppkCodes = $this->ppkSubKegiatanCodes($request);
        $perPage = (int) $request->query('per_page', 0);

        $query = RefSubKegiatan::query();

        if ($ppkCodes !== null) {
            $query->whereIn('kode_sub_kegiatan', $ppkCodes);
        }

        if ($kodeSkpd) {
            $query->whereIn('kode_sub_kegiatan', function ($q) use ($kodeSkpd) {
                $q->select('kode_sub_kegiatan')
                    ->from('dev.sipd_penetapan_apbd')
                    ->where('kode_sub_unit', $kodeSkpd)
                    ->where('pagu', '>', 0) // sub kegiatan dengan pagu kosong tidak ditampilkan
                    ->whereNotNull('kode_sub_kegiatan');
            });
        }

        if ($kodeKegiatan) {
            $query->where('kode_kegiatan', $kodeKegiatan);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_sub_kegiatan', 'ilike', "%{$search}%")
                    ->orWhere('nama_sub_kegiatan', 'ilike', "%{$search}%");
            });
        }

        $query->orderBy('kode_sub_kegiatan', 'asc');

        $data = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return SubKegiatanResource::collection($data);
    }
}
