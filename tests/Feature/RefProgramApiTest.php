<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RefProgramApiTest extends TestCase
{
    protected function tearDown(): void
    {
        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', function ($query) {
                $query->select('id')->from('dev.users')->where('username', 'like', 'program_test_%');
            })->delete();

        User::where('username', 'like', 'program_test_%')->delete();

        parent::tearDown();
    }

    private function createAuthToken(): string
    {
        $user = User::create([
            'nama' => 'Program Tester',
            'username' => 'program_test_user_'.uniqid(),
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        return $user->createToken('test_token')->plainTextToken;
    }

    public function test_can_list_programs(): void
    {
        $token = $this->createAuthToken();

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-program?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['kode_program', 'nama_program', 'kode_bidang_urusan'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_can_list_programs_filtered_by_skpd(): void
    {
        $token = $this->createAuthToken();

        $existingSkpd = DB::table('dev.sipd_penetapan_apbd')
            ->whereNotNull('kode_sub_kegiatan')
            ->first();

        if (! $existingSkpd) {
            $this->markTestSkipped('No sipd_penetapan_apbd data with kode_sub_kegiatan found');
        }

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-program?kode_skpd='.$existingSkpd->kode_sub_unit.'&per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['kode_program', 'nama_program', 'kode_bidang_urusan'],
                ],
            ]);
    }

    public function test_can_list_kegiatans(): void
    {
        $token = $this->createAuthToken();

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-kegiatan?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['kode_kegiatan', 'nama_kegiatan', 'kode_program'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_can_list_kegiatans_filtered_by_skpd(): void
    {
        $token = $this->createAuthToken();

        $existingSkpd = DB::table('dev.sipd_penetapan_apbd')
            ->whereNotNull('kode_sub_kegiatan')
            ->first();

        if (! $existingSkpd) {
            $this->markTestSkipped('No sipd_penetapan_apbd data with kode_sub_kegiatan found');
        }

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-kegiatan?kode_skpd='.$existingSkpd->kode_sub_unit.'&per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['kode_kegiatan', 'nama_kegiatan', 'kode_program'],
                ],
            ]);
    }

    public function test_can_list_sub_kegiatans(): void
    {
        $token = $this->createAuthToken();

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-sub-kegiatan?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['kode_sub_kegiatan', 'nama_sub_kegiatan', 'kode_kegiatan'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_can_list_sub_kegiatans_filtered_by_skpd(): void
    {
        $token = $this->createAuthToken();

        $existingSkpd = DB::table('dev.sipd_penetapan_apbd')
            ->whereNotNull('kode_sub_kegiatan')
            ->first();

        if (! $existingSkpd) {
            $this->markTestSkipped('No sipd_penetapan_apbd data with kode_sub_kegiatan found');
        }

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-sub-kegiatan?kode_skpd='.$existingSkpd->kode_sub_unit.'&per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['kode_sub_kegiatan', 'nama_sub_kegiatan', 'kode_kegiatan'],
                ],
            ]);
    }

    public function test_programs_without_per_page_returns_array(): void
    {
        $token = $this->createAuthToken();

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-program?per_page=0');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['kode_program', 'nama_program', 'kode_bidang_urusan'],
                ],
            ]);
    }

    public function test_kegiatans_can_be_filtered_by_kode_sub_kegiatan(): void
    {
        $token = $this->createAuthToken();

        $subKegiatan = DB::table('dev.ref_sub_kegiatan')
            ->whereNotNull('kode_kegiatan')
            ->first();

        if (! $subKegiatan) {
            $this->markTestSkipped('No ref_sub_kegiatan data found');
        }

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-kegiatan?kode_sub_kegiatan='.$subKegiatan->kode_sub_kegiatan);

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('kode_kegiatan')->all();

        $this->assertNotEmpty($codes);
        $this->assertContains($subKegiatan->kode_kegiatan, $codes);
    }

    public function test_programs_can_be_filtered_by_kode_sub_kegiatan(): void
    {
        $token = $this->createAuthToken();

        $subKegiatan = DB::table('dev.ref_sub_kegiatan')
            ->whereNotNull('kode_kegiatan')
            ->first();

        if (! $subKegiatan) {
            $this->markTestSkipped('No ref_sub_kegiatan data found');
        }

        $expectedProgram = DB::table('dev.ref_kegiatan')
            ->where('kode_kegiatan', $subKegiatan->kode_kegiatan)
            ->value('kode_program');

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-program?kode_sub_kegiatan='.$subKegiatan->kode_sub_kegiatan);

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('kode_program')->all();

        $this->assertNotEmpty($codes);
        $this->assertContains($expectedProgram, $codes);
    }
}
