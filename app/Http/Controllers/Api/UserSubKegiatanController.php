<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserSubKegiatanController extends Controller
{
    /**
     * Role yang boleh MENGELOLA mapping (tambah/hapus): Admin, Kepala OPD, Kepala Sub Unit.
     */
    private function canManage(Request $request): bool
    {
        return in_array(strtoupper((string) $request->user()?->role), [
            'ADMIN',
            'KEPALA OPD',
            'KEPALA SUB UNIT',
        ], true);
    }

    /**
     * Kode SKPD lingkup pengelola (bukan Admin → hanya user di SKPD-nya sendiri).
     */
    private function scopeKodeSkpd(Request $request): ?string
    {
        $user = $request->user();
        if (strtoupper((string) $user?->role) === 'ADMIN') {
            return null;
        }

        return $user?->kode_skpd ?: null;
    }

    /**
     * Daftar mapping user ↔ sub kegiatan.
     *
     * GET /api/v1/user-sub-kegiatan
     * - PPK: hanya mapping miliknya sendiri.
     * - Kepala OPD / Kepala Sub Unit: mapping user di SKPD-nya.
     * - Admin / Verifikator: semua.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $kodeSkpd = $this->scopeKodeSkpd($request);

        $q = DB::table('dev.user_sub_kegiatan as usk')
            ->join('dev.users as u', 'u.id', '=', 'usk.user_id')
            ->leftJoin('dev.ref_sub_kegiatan as rsk', 'rsk.kode_sub_kegiatan', '=', 'usk.kode_sub_kegiatan')
            ->leftJoin('dev.ref_skpd as rs', 'rs.kode_skpd', '=', 'u.kode_skpd')
            ->select([
                'usk.id',
                'usk.user_id',
                'u.username',
                'u.nama',
                'u.role',
                'u.kode_skpd',
                'rs.nama_skpd',
                'usk.kode_sub_kegiatan',
                'rsk.nama_sub_kegiatan',
                'usk.created_at',
            ])
            ->orderBy('u.nama')
            ->orderBy('usk.kode_sub_kegiatan');

        // PPK hanya melihat mapping miliknya.
        if (strtoupper((string) $user?->role) === 'PPK') {
            $q->where('usk.user_id', $user->id);
        } elseif ($kodeSkpd) {
            $q->where('u.kode_skpd', $kodeSkpd);
        }

        $rows = $q->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Daftar user PPK yang tersedia untuk di-mapping (di lingkup SKPD pengelola).
     *
     * GET /api/v1/user-sub-kegiatan/ppk-users
     */
    public function ppkUsers(Request $request): JsonResponse
    {
        $kodeSkpd = $this->scopeKodeSkpd($request);

        $q = User::query()
            ->where('role', 'PPK')
            ->select(['id', 'username', 'nama', 'kode_skpd'])
            ->orderBy('nama');

        if ($kodeSkpd) {
            $q->where('kode_skpd', $kodeSkpd);
        }

        return response()->json(['data' => $q->get()]);
    }

    /**
     * Tambah mapping (user_id + kode_sub_kegiatan).
     *
     * POST /api/v1/user-sub-kegiatan
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->canManage($request)) {
            return response()->json(['message' => 'Hanya Admin, Kepala OPD, atau Kepala Sub Unit yang dapat mengelola mapping PPK.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:pgsql.dev.users,id'],
            'kode_sub_kegiatan' => ['required', 'string', 'max:50', 'exists:pgsql.dev.ref_sub_kegiatan,kode_sub_kegiatan'],
        ]);

        $target = User::find($validated['user_id']);
        $kodeSkpd = $this->scopeKodeSkpd($request);

        // Non-Admin hanya boleh memetakan PPK di SKPD-nya sendiri.
        if ($kodeSkpd !== null && $target?->kode_skpd !== $kodeSkpd) {
            return response()->json(['message' => 'PPK tersebut berada di luar lingkup SKPD Anda.'], 403);
        }

        $exists = DB::table('dev.user_sub_kegiatan')
            ->where('user_id', $validated['user_id'])
            ->where('kode_sub_kegiatan', $validated['kode_sub_kegiatan'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'kode_sub_kegiatan' => 'Mapping sub kegiatan tersebut sudah ada untuk user ini.',
            ]);
        }

        DB::table('dev.user_sub_kegiatan')->insert([
            'user_id' => $validated['user_id'],
            'kode_sub_kegiatan' => $validated['kode_sub_kegiatan'],
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Mapping sub kegiatan berhasil ditambahkan'], 201);
    }

    /**
     * Hapus mapping.
     *
     * DELETE /api/v1/user-sub-kegiatan/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->canManage($request)) {
            return response()->json(['message' => 'Hanya Admin, Kepala OPD, atau Kepala Sub Unit yang dapat mengelola mapping PPK.'], 403);
        }

        $row = DB::table('dev.user_sub_kegiatan as usk')
            ->join('dev.users as u', 'u.id', '=', 'usk.user_id')
            ->where('usk.id', $id)
            ->select(['usk.id', 'u.kode_skpd'])
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Mapping tidak ditemukan'], 404);
        }

        $kodeSkpd = $this->scopeKodeSkpd($request);
        if ($kodeSkpd !== null && $row->kode_skpd !== $kodeSkpd) {
            return response()->json(['message' => 'Mapping tersebut berada di luar lingkup SKPD Anda.'], 403);
        }

        DB::table('dev.user_sub_kegiatan')->where('id', $id)->delete();

        return response()->json(['message' => 'Mapping sub kegiatan berhasil dihapus']);
    }
}