<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\RefSkpd;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users (datatable-ready with search, filter, pagination).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');
        $role = $request->query('role');
        $kodeSkpd = $request->query('kode_skpd');
        $sortBy = $request->query('sort_by', 'id');
        $sortDirection = strtolower($request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->query('per_page', 15);

        $allowedSorts = ['id', 'nama', 'username', 'role', 'kode_skpd', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $query = User::with(['skpd', 'subKegiatan.kegiatan.program.bidangUrusan']);

        // Filter berdasarkan role user yang sedang login
        $currentUser = $request->user();
        if ($currentUser) {
            $userRole = strtolower(trim((string) $currentUser->role));

            if ($userRole === 'kepala opd') {
                // OPD induk dan seluruh sub unit di bawahnya
                $skpdCodes = RefSkpd::query()
                    ->where('kode_skpd', $currentUser->kode_skpd)
                    ->orWhere('parent_kode_skpd', $currentUser->kode_skpd)
                    ->pluck('kode_skpd')
                    ->all();

                $query->whereIn('kode_skpd', ! empty($skpdCodes) ? $skpdCodes : [$currentUser->kode_skpd]);
            } elseif ($userRole === 'kepala sub unit') {
                // Hanya SKPD sub unit nya sendiri
                $query->where('kode_skpd', $currentUser->kode_skpd);
            }
            // Role Admin/lainnya tidak dibatasi (bisa melihat semua)
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'ilike', "%{$search}%")
                    ->orWhere('username', 'ilike', "%{$search}%")
                    ->orWhere('role', 'ilike', "%{$search}%")
                    ->orWhereRaw("info->>'nip' ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("info->>'jabatan' ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("info->>'no_hp' ILIKE ?", ["%{$search}%"])
                    ->orWhereRaw("info->>'email_dinas' ILIKE ?", ["%{$search}%"]);
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        if ($kodeSkpd) {
            $query->where('kode_skpd', $kodeSkpd);
        }

        $users = $query->orderBy($sortBy, $sortDirection)
            ->paginate($perPage > 0 ? $perPage : 15);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $userData = [
            'nama' => $validated['nama'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'kode_skpd' => $validated['kode_skpd'] ?? null,
            'info' => $validated['info'] ?? null,
        ];

        $user = DB::transaction(function () use ($userData, $validated) {
            $user = User::create($userData);

            if (isset($validated['sub_kegiatan_ids'])) {
                $user->subKegiatan()->sync($validated['sub_kegiatan_ids']);
            }

            return $user;
        });

        $user->load(['skpd', 'subKegiatan.kegiatan.program.bidangUrusan']);

        return response()->json([
            'message' => 'User created successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['skpd', 'subKegiatan.kegiatan.program.bidangUrusan']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $userData = [];
        if (isset($validated['nama'])) {
            $userData['nama'] = $validated['nama'];
        }
        if (isset($validated['username'])) {
            $userData['username'] = $validated['username'];
        }
        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }
        if (isset($validated['role'])) {
            $userData['role'] = $validated['role'];
        }
        if (array_key_exists('kode_skpd', $validated)) {
            $userData['kode_skpd'] = $validated['kode_skpd'];
        }
        if (array_key_exists('info', $validated)) {
            $currentInfo = is_array($user->info) ? $user->info : [];
            $userData['info'] = $validated['info'] !== null ? array_merge($currentInfo, $validated['info']) : null;
        }

        DB::transaction(function () use ($user, $userData, $validated) {
            if (! empty($userData)) {
                $user->update($userData);
            }

            if (array_key_exists('sub_kegiatan_ids', $validated)) {
                $user->subKegiatan()->sync($validated['sub_kegiatan_ids'] ?? []);
            }
        });

        $user->load(['skpd', 'subKegiatan.kegiatan.program.bidangUrusan']);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        DB::transaction(function () use ($user) {
            // Bersihkan data turunan yang berpotensi memblokir hapus (defense-in-depth).
            // dev.identifikasi_kebutuhan & dev.notifications ikut terhapus via FK ON DELETE CASCADE.
            DB::table('dev.identifikasi_kebutuhan_riwayat')->where('user_id', $user->id)->delete();

            $user->delete();
        });

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
