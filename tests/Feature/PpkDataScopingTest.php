<?php

namespace Tests\Feature;

use App\Models\RefSubKegiatan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PpkDataScopingTest extends TestCase
{
    private const PREFIX = 'ppk_scope_';

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

    private function createUserWithRole(string $role, array $subKegiatanCodes = []): string
    {
        $user = User::create([
            'nama' => 'Scope Tester',
            'username' => self::PREFIX.$role.'_'.uniqid(),
            'password' => Hash::make('secret123'),
            'role' => $role,
        ]);

        foreach ($subKegiatanCodes as $code) {
            DB::table('dev.user_sub_kegiatan')->insert([
                'user_id' => $user->id,
                'kode_sub_kegiatan' => $code,
            ]);
        }

        return $user->createToken('scope_token')->plainTextToken;
    }

    public function test_ppk_user_only_sees_mapped_sub_kegiatan(): void
    {
        $sub = RefSubKegiatan::whereNotNull('kode_kegiatan')->first();

        if (! $sub) {
            $this->markTestSkipped('No ref_sub_kegiatan data found');
        }

        $token = $this->createUserWithRole('PPK', [$sub->kode_sub_kegiatan]);

        $response = $this->withToken($token)->getJson('/api/v1/ref-sub-kegiatan');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('kode_sub_kegiatan')->all();

        $this->assertSame([$sub->kode_sub_kegiatan], $codes);
    }

    public function test_ppk_user_only_sees_kegiatan_of_mapped_sub_kegiatan(): void
    {
        $sub = RefSubKegiatan::whereNotNull('kode_kegiatan')->first();

        if (! $sub) {
            $this->markTestSkipped('No ref_sub_kegiatan data found');
        }

        $token = $this->createUserWithRole('PPK', [$sub->kode_sub_kegiatan]);

        $response = $this->withToken($token)->getJson('/api/v1/ref-kegiatan');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('kode_kegiatan')->all();

        $this->assertNotEmpty($codes);
        $this->assertContains($sub->kode_kegiatan, $codes);
        $this->assertCount(1, $codes);
    }

    public function test_ppk_user_only_sees_program_of_mapped_sub_kegiatan(): void
    {
        $sub = RefSubKegiatan::whereNotNull('kode_kegiatan')->first();

        if (! $sub) {
            $this->markTestSkipped('No ref_sub_kegiatan data found');
        }

        $expectedProgram = DB::table('dev.ref_kegiatan')
            ->where('kode_kegiatan', $sub->kode_kegiatan)
            ->value('kode_program');

        $token = $this->createUserWithRole('PPK', [$sub->kode_sub_kegiatan]);

        $response = $this->withToken($token)->getJson('/api/v1/ref-program');

        $response->assertOk();

        $codes = collect($response->json('data'))->pluck('kode_program')->all();

        $this->assertNotEmpty($codes);
        $this->assertContains($expectedProgram, $codes);
        $this->assertCount(1, $codes);
    }

    public function test_ppk_user_without_mapping_sees_nothing(): void
    {
        $token = $this->createUserWithRole('PPK');

        foreach (['ref-program', 'ref-kegiatan', 'ref-sub-kegiatan'] as $path) {
            $this->withToken($token)
                ->getJson('/api/v1/'.$path)
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_non_ppk_user_is_not_scoped(): void
    {
        $sub = RefSubKegiatan::whereNotNull('kode_kegiatan')->first();

        if (! $sub) {
            $this->markTestSkipped('No ref_sub_kegiatan data found');
        }

        $token = $this->createUserWithRole('operator', [$sub->kode_sub_kegiatan]);

        $response = $this->withToken($token)->getJson('/api/v1/ref-sub-kegiatan');

        $response->assertOk();

        $this->assertGreaterThan(1, count($response->json('data')));
    }
}
