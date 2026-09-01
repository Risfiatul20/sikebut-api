<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IdentifikasiKebutuhan\StoreIdentifikasiKebutuhanRequest;
use App\Http\Requests\IdentifikasiKebutuhan\UpdateIdentifikasiKebutuhanRequest;
use App\Http\Resources\IdentifikasiKebutuhanResource;
use App\Models\IdentifikasiKebutuhan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class IdentifikasiKebutuhanController extends Controller
{
    /**
     * @var list<string>
     */
    private const RELATIONS = [
        'anggaran.standarHarga',
        'anggaran.sipdPenetapan',
        'pembuat',
        'skpd',
        'program',
        'kegiatan',
        'subKegiatan',
    ];

    /**
     * Scope to the caller's SKPD, and to mapped sub kegiatan when role is PPK.
     *
     * @param  bool  $respectQuery  allow kode_skpd override, only safe for listing
     * @return Builder<IdentifikasiKebutuhan>
     */
    private function scopedQuery(Request $request, bool $respectQuery = true): Builder
    {
        $user = $request->user();
        $param = (string) ($respectQuery ? ($request->query('kode_skpd') ?? '') : '');
        $kodeSkpd = $param !== '' ? $param : $user?->kode_skpd;
        $ppkCodes = $user?->ppkSubKegiatanCodes();

        $query = IdentifikasiKebutuhan::query();

        if ($kodeSkpd) {
            $query->where('kode_skpd', $kodeSkpd);
        }

        if ($ppkCodes !== null) {
            $query->whereIn('kode_sub_kegiatan', $ppkCodes);
        }

        return $query;
    }

    /**
     * List identifikasi kebutuhan with nested anggaran.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->scopedQuery($request)->with(self::RELATIONS);

        $search = $request->query('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_paket', 'ilike', "%{$search}%")
                    ->orWhere('kode_sub_kegiatan', 'ilike', "%{$search}%");
            });
        }

        foreach (['status_review', 'cara_pengadaan', 'jenis_pengadaan', 'kode_program', 'kode_kegiatan', 'kode_sub_kegiatan'] as $field) {
            $value = $request->query($field);
            if ($value) {
                $query->where($field, $value);
            }
        }

        $requestedSort = $request->query('sort_by');
        $allowedSorts = ['id', 'nama_paket', 'status_review', 'created_at', 'updated_at'];
        $sortBy = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'id';
        $sortDirection = strtolower((string) $request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->query('per_page', 15);

        $query->orderBy($sortBy, $sortDirection);

        $data = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return IdentifikasiKebutuhanResource::collection($data);
    }

    /**
     * Persist a new identifikasi kebutuhan together with its anggaran rows.
     */
    public function store(StoreIdentifikasiKebutuhanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kebutuhan = DB::transaction(function () use ($validated, $request) {
            $kebutuhan = IdentifikasiKebutuhan::create([
                ...collect($validated)->except('anggaran')->all(),
                'user_id' => $request->user()->id,
                'status_review' => $validated['status_review'] ?? 'Draft',
            ]);

            foreach ($validated['anggaran'] as $anggaran) {
                $kebutuhan->anggaran()->create($anggaran);
            }

            return $kebutuhan;
        });

        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Identifikasi kebutuhan created successfully',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ], 201);
    }

    /**
     * Show one identifikasi kebutuhan with its anggaran rows.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $kebutuhan = $this->scopedQuery($request, false)->with(self::RELATIONS)->findOrFail($id);

        return response()->json([
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Update the header and reconcile the anggaran collection.
     */
    public function update(UpdateIdentifikasiKebutuhanRequest $request, int $id): JsonResponse
    {
        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($kebutuhan, $validated) {
            $header = collect($validated)->except('anggaran')->all();

            if ($header !== []) {
                $kebutuhan->update($header);
            }

            if (array_key_exists('anggaran', $validated)) {
                $owned = $kebutuhan->anggaran()->pluck('id')->all();
                $keep = [];

                foreach ($validated['anggaran'] as $anggaran) {
                    $row = collect($anggaran)->except('id')->all();

                    if (isset($anggaran['id']) && in_array((int) $anggaran['id'], $owned, true)) {
                        $kebutuhan->anggaran()->whereKey((int) $anggaran['id'])->update($row);
                        $keep[] = (int) $anggaran['id'];

                        continue;
                    }

                    $keep[] = $kebutuhan->anggaran()->create($row)->id;
                }

                $kebutuhan->anggaran()->whereNotIn('id', $keep)->delete();
            }
        });

        $kebutuhan->load(self::RELATIONS);

        return response()->json([
            'message' => 'Identifikasi kebutuhan updated successfully',
            'data' => new IdentifikasiKebutuhanResource($kebutuhan),
        ]);
    }

    /**
     * Remove an identifikasi kebutuhan; anggaran rows cascade with it.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $kebutuhan = $this->scopedQuery($request, false)->findOrFail($id);
        $kebutuhan->delete();

        return response()->json([
            'message' => 'Identifikasi kebutuhan deleted successfully',
        ]);
    }
}
