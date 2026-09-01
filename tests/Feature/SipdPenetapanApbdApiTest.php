<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SipdPenetapanApbdApiTest extends TestCase
{
    private const PREFIX = 'sipd_test_';

    protected function tearDown(): void
    {
        DB::table('dev.user_sub_kegiatan')->whereIn('user_id', function ($query) {
            $query->select('id')->from('dev.users')->where('username', 'like', self::PREFIX.'%');
        })->delete();

        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', function ($query) {
                $query->select('id')->from('dev.users')->where('username', 'like', self::PREFIX.'%');
            })->delete();

        User::where('username', 'like', self::PREFIX.'%')->delete();

        parent::tearDown();
    }

    private function createToken(?string $kodeSkpd, string $role = 'operator', array $subs = []): string
    {
        $user = User::create([
            'nama' => 'SIPD Tester',
            'username' => self::PREFIX.uniqid(),
            'password' => Hash::make('secret123'),
            'role' => $role,
            'kode_skpd' => $kodeSkpd,
        ]);

        foreach ($subs as $sub) {
            DB::table('dev.user_sub_kegiatan')->insert([
                'user_id' => $user->id,
                'kode_sub_kegiatan' => $sub,
            ]);
        }

        return $user->createToken('sipd_token')->plainTextToken;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/sipd-penetapan-apbd')->assertUnauthorized();
    }

    public function test_can_list_with_pagination(): void
    {
        $token = $this->createToken(null);

        $response = $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'kode_daerah',
                        'nama_daerah',
                        'tahun',
                        'kode_sub_unit',
                        'nama_sub_unit',
                        'kode_opd',
                        'nama_opd',
                        'kode_sub_kegiatan',
                        'nama_sub_kegiatan',
                        'kode_kegiatan',
                        'nama_kegiatan',
                        'kode_program',
                        'nama_program',
                        'kode_standar_harga',
                        'nama_standar_harga',
                        'kode_rekening',
                        'nama_rekening',
                        'kode_sumber_dana',
                        'nama_sumber_dana',
                        'pagu',
                        'indikator_rkbmd',
                        'versi',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_auto_scoped_to_login_user_skpd(): void
    {
        $row = DB::table('dev.sipd_penetapan_apbd')->whereNotNull('kode_sub_unit')->first();

        if (! $row) {
            $this->markTestSkipped('No sipd_penetapan_apbd data found');
        }

        $token = $this->createToken($row->kode_sub_unit);

        $response = $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd?per_page=10');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertSame($row->kode_sub_unit, $item['kode_sub_unit']);
        }
    }

    public function test_can_filter_by_tahun(): void
    {
        $row = DB::table('dev.sipd_penetapan_apbd')->first();

        if (! $row) {
            $this->markTestSkipped('No sipd_penetapan_apbd data found');
        }

        $token = $this->createToken($row->kode_sub_unit);

        $response = $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd?tahun='.$row->tahun.'&per_page=5');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertSame((int) $row->tahun, $item['tahun']);
        }
    }

    public function test_ppk_user_only_sees_mapped_sub_kegiatan(): void
    {
        $row = DB::table('dev.sipd_penetapan_apbd')->whereNotNull('kode_sub_kegiatan')->first();

        if (! $row) {
            $this->markTestSkipped('No sipd_penetapan_apbd data found');
        }

        $token = $this->createToken($row->kode_sub_unit, 'PPK', [$row->kode_sub_kegiatan]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('kode_sub_kegiatan')->unique()->values()->all();

        $this->assertSame([$row->kode_sub_kegiatan], $codes);
    }

    public function test_can_show_single_record(): void
    {
        $row = DB::table('dev.sipd_penetapan_apbd')->whereNotNull('kode_sub_kegiatan')->first();

        if (! $row) {
            $this->markTestSkipped('No sipd_penetapan_apbd data found');
        }

        $token = $this->createToken($row->kode_sub_unit, 'PPK', [$row->kode_sub_kegiatan]);

        $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd/'.$row->id)
            ->assertOk()
            ->assertJsonPath('data.id', $row->id);
    }

    public function test_ppk_cannot_show_unmapped_record(): void
    {
        $mapped = DB::table('dev.sipd_penetapan_apbd')->whereNotNull('kode_sub_kegiatan')->first();
        $other = DB::table('dev.sipd_penetapan_apbd')
            ->whereNotNull('kode_sub_kegiatan')
            ->where('kode_sub_kegiatan', '!=', $mapped->kode_sub_kegiatan)
            ->first();

        if (! $other) {
            $this->markTestSkipped('Need at least two distinct sub kegiatan rows');
        }

        $token = $this->createToken($other->kode_sub_unit, 'PPK', [$mapped->kode_sub_kegiatan]);

        $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd/'.$other->id)
            ->assertNotFound();
    }

    public function test_response_exposes_opd_hierarki_and_rkbmd_indikator(): void
    {
        $row = DB::table('dev.sipd_penetapan_apbd')
            ->join('dev.ref_akun', 'dev.ref_akun.kode_akun', '=', 'dev.sipd_penetapan_apbd.kode_rekening')
            ->whereNotNull('dev.sipd_penetapan_apbd.kode_sub_kegiatan')
            ->select('dev.sipd_penetapan_apbd.*')
            ->first();

        if (! $row) {
            $this->markTestSkipped('No sipd row with matching ref_akun and sub kegiatan');
        }

        $token = $this->createToken($row->kode_sub_unit);

        $response = $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd/'.$row->id);

        $response->assertOk()
            ->assertJsonPath('data.kode_rekening', $row->kode_rekening);

        $item = $response->json('data');

        $subUnit = DB::table('dev.ref_skpd')->where('kode_skpd', $row->kode_sub_unit)->first();
        $expectedOpd = ! empty($subUnit?->parent_kode_skpd) ? $subUnit->parent_kode_skpd : $row->kode_sub_unit;

        $this->assertSame($expectedOpd, $item['kode_opd']);
        $this->assertSame($row->kode_sub_kegiatan, $item['kode_sub_kegiatan']);
        $this->assertNotEmpty($item['kode_kegiatan']);
        $this->assertNotEmpty($item['kode_program']);
        $this->assertSame(['b', 'r', 'h', 't'], array_keys($item['indikator_rkbmd']));
    }

    public function test_modal_endpoint_returns_rekapitulasi_hierarchy_and_standar_harga(): void
    {
        $sample = DB::table('dev.ref_sipd_view')
            ->whereNotNull('kode_sub_kegiatan')
            ->whereNotNull('kode_sub_unit')
            ->first();

        if (! $sample) {
            $this->markTestSkipped('No ref_sipd_view data found');
        }

        $token = $this->createToken($sample->kode_sub_unit);

        $response = $this->withToken($token)
            ->getJson("/api/v1/sipd-penetapan-apbd/modal?kode_sub_kegiatan={$sample->kode_sub_kegiatan}");

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'skpd' => [
                        'kode_skpd',
                        'nama_skpd',
                        'total_pagu',
                        'total_pagu_pengadaan',
                        'total_pagu_non_pengadaan',
                        'total_kebutuhan_anggaran',
                        'sisa_pagu_pengadaan',
                    ],
                    'sub_unit' => [
                        'kode_sub_unit',
                        'nama_sub_unit',
                        'total_pagu',
                        'total_pagu_pengadaan',
                        'total_pagu_non_pengadaan',
                        'total_kebutuhan_anggaran',
                        'sisa_pagu_pengadaan',
                    ],
                    'program' => [
                        'kode_program',
                        'nama_program',
                        'total_pagu',
                        'total_pagu_pengadaan',
                        'total_pagu_non_pengadaan',
                        'total_kebutuhan_anggaran',
                        'sisa_pagu_pengadaan',
                    ],
                    'kegiatan' => [
                        'kode_kegiatan',
                        'nama_kegiatan',
                        'total_pagu',
                        'total_pagu_pengadaan',
                        'total_pagu_non_pengadaan',
                        'total_kebutuhan_anggaran',
                        'sisa_pagu_pengadaan',
                    ],
                    'sub_kegiatan' => [
                        'kode_sub_kegiatan',
                        'nama_sub_kegiatan',
                        'total_pagu',
                        'total_pagu_pengadaan',
                        'total_pagu_non_pengadaan',
                        'total_kebutuhan_anggaran',
                        'sisa_pagu_pengadaan',
                    ],
                    'standar_harga' => [
                        '*' => [
                            'id_sipd_penetapan',
                            'kode_rekening',
                            'nama_rekening',
                            'kode_standar_harga',
                            'nama_standar_harga',
                            'kode_sumber_dana',
                            'nama_sumber_dana',
                            'is_belanja_pengadaan',
                            'is_rkbmd_pengadaan',
                            'is_rkbmd_pemeliharaan_rehab',
                            'is_rkbmd_pemeliharaan_rutin',
                            'pagu',
                            'total_kebutuhan_anggaran',
                            'sisa_pagu',
                        ],
                    ],
                ],
            ]);

        $this->assertSame($sample->kode_sub_kegiatan, $response->json('data.sub_kegiatan.kode_sub_kegiatan'));
        $this->assertSame($sample->kode_sub_unit, $response->json('data.sub_unit.kode_sub_unit'));
    }

    public function test_modal_endpoint_requires_sub_kegiatan_param(): void
    {
        $token = $this->createToken('1.01.0.00.0.00.01.0000');

        $this->withToken($token)
            ->getJson('/api/v1/sipd-penetapan-apbd/modal')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Parameter kode_sub_kegiatan (atau subkegiatan) wajib diisi.');
    }
}
