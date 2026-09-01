<?php

namespace Tests\Feature;

use App\Models\AkunIndikatorRkbmd;
use App\Models\RefAkun;
use App\Models\RefSkpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RefAkunApiTest extends TestCase
{
    protected function tearDown(): void
    {
        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', function ($query) {
                $query->select('id')->from('dev.users')->where('username', 'like', 'akun_test_%');
            })->delete();

        User::where('username', 'like', 'akun_test_%')->delete();
        DB::table('dev.sipd_penetapan_apbd')->where('kode_sub_unit', 'like', 'TEST_SKPD_%')->delete();
        RefSkpd::where('kode_skpd', 'like', 'TEST_SKPD_%')->delete();
        AkunIndikatorRkbmd::where('kode_akun', 'like', 'TEST_AKUN_%')->delete();
        RefAkun::where('kode_akun', 'like', 'TEST_AKUN_%')->delete();

        parent::tearDown();
    }

    private function createAuthToken(?string $kodeSkpd = null): array
    {
        $user = User::create([
            'nama' => 'Akun Tester',
            'username' => 'akun_test_user_'.uniqid(),
            'password' => Hash::make('secret123'),
            'role' => 'operator',
            'kode_skpd' => $kodeSkpd,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        return [$user, $token];
    }

    public function test_can_list_hierarchical_akuns_with_rkbmd_indicators(): void
    {
        [$user, $token] = $this->createAuthToken();

        RefAkun::create([
            'kode_akun' => 'TEST_AKUN_5',
            'nama_akun' => 'Belanja',
            'parent_kode_akun' => null,
            'level_akun' => 1,
        ]);

        RefAkun::create([
            'kode_akun' => 'TEST_AKUN_5.1',
            'nama_akun' => 'Belanja Operasi',
            'parent_kode_akun' => 'TEST_AKUN_5',
            'level_akun' => 2,
        ]);

        AkunIndikatorRkbmd::create([
            'kode_akun' => 'TEST_AKUN_5.1',
            'is_belanja_pengadaan' => true,
            'is_rkbmd_pengadaan' => true,
            'is_rkbmd_pemeliharaan_rehab' => false,
            'is_rkbmd_pemeliharaan_rutin' => true,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-akun?search=TEST_AKUN');

        $response->assertOk()
            ->assertJsonFragment([
                'kode' => 'TEST_AKUN_5',
                'nama' => 'Belanja',
                'level' => 1,
                'parent' => null,
                'b' => false,
                'r' => false,
                'h' => false,
                't' => false,
            ])
            ->assertJsonFragment([
                'kode' => 'TEST_AKUN_5.1',
                'nama' => 'Belanja Operasi',
                'level' => 2,
                'parent' => 'TEST_AKUN_5',
                'b' => true,
                'r' => true,
                'h' => false,
                't' => true,
            ]);
    }

    public function test_can_filter_akuns_by_indicator(): void
    {
        [$user, $token] = $this->createAuthToken();

        RefAkun::create([
            'kode_akun' => 'TEST_AKUN_A',
            'nama_akun' => 'Akun A',
            'parent_kode_akun' => null,
            'level_akun' => 1,
        ]);

        RefAkun::create([
            'kode_akun' => 'TEST_AKUN_B',
            'nama_akun' => 'Akun B',
            'parent_kode_akun' => null,
            'level_akun' => 1,
        ]);

        AkunIndikatorRkbmd::create([
            'kode_akun' => 'TEST_AKUN_A',
            'is_belanja_pengadaan' => true,
            'is_rkbmd_pengadaan' => false,
            'is_rkbmd_pemeliharaan_rehab' => false,
            'is_rkbmd_pemeliharaan_rutin' => false,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-akun?search=TEST_AKUN&belanja_pengadaan=true');

        $response->assertOk()
            ->assertJsonFragment(['kode' => 'TEST_AKUN_A'])
            ->assertJsonMissing(['kode' => 'TEST_AKUN_B']);

        $responseAlias = $this->withToken($token)
            ->getJson('/api/v1/ref-akun?search=TEST_AKUN&b=true');

        $responseAlias->assertOk()
            ->assertJsonFragment(['kode' => 'TEST_AKUN_A'])
            ->assertJsonMissing(['kode' => 'TEST_AKUN_B']);
    }

    public function test_automatically_filters_by_user_kode_skpd_from_sipd_penetapan_apbd(): void
    {
        $skpd = RefSkpd::create([
            'kode_skpd' => 'TEST_SKPD_99',
            'nama_skpd' => 'Dinas Testing APBD',
        ]);

        RefAkun::create([
            'kode_akun' => 'TEST_AKUN_REK_5',
            'nama_akun' => 'Belanja Rekening',
            'parent_kode_akun' => null,
            'level_akun' => 1,
        ]);

        RefAkun::create([
            'kode_akun' => 'TEST_AKUN_REK_5.1',
            'nama_akun' => 'Belanja Rekening Detail',
            'parent_kode_akun' => 'TEST_AKUN_REK_5',
            'level_akun' => 2,
        ]);

        RefAkun::create([
            'kode_akun' => 'TEST_AKUN_OTHER_6',
            'nama_akun' => 'Akun Lain Non APBD SKPD',
            'parent_kode_akun' => null,
            'level_akun' => 1,
        ]);

        DB::table('dev.sipd_penetapan_apbd')->insert([
            'kode_sub_unit' => $skpd->kode_skpd,
            'kode_rekening' => 'TEST_AKUN_REK_5.1',
            'tahun' => 2026,
            'versi' => '1',
            'pagu' => 1000000,
        ]);

        [$user, $token] = $this->createAuthToken($skpd->kode_skpd);

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-akun?search=TEST_AKUN');

        $response->assertOk()
            ->assertJsonFragment(['kode' => 'TEST_AKUN_REK_5'])
            ->assertJsonFragment(['kode' => 'TEST_AKUN_REK_5.1'])
            ->assertJsonMissing(['kode' => 'TEST_AKUN_OTHER_6']);
    }

    public function test_can_query_ref_akun_view_endpoint(): void
    {
        [$user, $token] = $this->createAuthToken();

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-akun/view?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'kode_2',
                        'nama_2',
                        'kode_3',
                        'nama_3',
                        'kode_4',
                        'nama_4',
                        'kode_5',
                        'nama_5',
                        'kode_6',
                        'nama_6',
                        'b',
                        'r',
                        'h',
                        't',
                    ],
                ],
                'links',
                'meta',
            ]);
    }
}
