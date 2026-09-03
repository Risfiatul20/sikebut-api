<?php

namespace Tests\Feature;

use App\Models\RefSkpd;
use App\Models\RefSubKegiatan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::table('dev.user_sub_kegiatan')->whereIn('user_id', function ($query) {
            $query->select('id')->from('dev.users')->where('username', 'like', 'test_user_%');
        })->delete();

        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', function ($query) {
                $query->select('id')->from('dev.users')->where('username', 'like', 'test_user_%');
            })->delete();

        User::where('username', 'like', 'test_user_%')->delete();

        parent::tearDown();
    }

    public function test_user_can_login_with_valid_credentials_and_retrieve_token(): void
    {
        $skpd = RefSkpd::first();
        $subKegiatan = RefSubKegiatan::first();

        $user = User::create([
            'nama' => 'Test User',
            'username' => 'test_user_login',
            'password' => Hash::make('secret123'),
            'role' => 'operator',
            'kode_skpd' => $skpd?->kode_skpd,
            'info' => ['nip' => '199001012020011001'],
        ]);

        if ($subKegiatan) {
            $user->subKegiatan()->attach($subKegiatan->kode_sub_kegiatan);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'test_user_login',
            'password' => 'secret123',
            'device_name' => 'postman',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token_type',
                'access_token',
                'user' => [
                    'id',
                    'nama',
                    'username',
                    'role',
                    'kode_skpd',
                    'info',
                    'skpd' => ['kode_skpd', 'nama_skpd'],
                    'sub_kegiatan' => [
                        '*' => ['kode_sub_kegiatan', 'nama_sub_kegiatan'],
                    ],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::create([
            'nama' => 'Test User',
            'username' => 'test_user_invalid',
            'password' => Hash::make('secret123'),
            'role' => 'operator',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'test_user_invalid',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_authenticated_user_can_access_me_and_logout(): void
    {
        $user = User::create([
            'nama' => 'Test User Profile',
            'username' => 'test_user_me',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $meResponse = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('user.username', 'test_user_me')
            ->assertJsonPath('user.role', 'admin');

        $logoutResponse = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJson(['message' => 'Logout successful']);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $afterLogoutResponse = $this->withToken($token)
            ->getJson('/api/v1/auth/me');

        $afterLogoutResponse->assertUnauthorized();
    }

    public function test_ppk_user_login_includes_kegiatan_and_program_details(): void
    {
        $skpd = RefSkpd::first();
        $subKegiatan = RefSubKegiatan::with('kegiatan.program.bidangUrusan')->first();

        $user = User::create([
            'nama' => 'PPK Test User',
            'username' => 'test_user_ppk_login',
            'password' => Hash::make('secret123'),
            'role' => 'PPK',
            'kode_skpd' => $skpd?->kode_skpd,
            'info' => ['nip' => '198501012010011002'],
        ]);

        if ($subKegiatan) {
            $user->subKegiatan()->attach($subKegiatan->kode_sub_kegiatan);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'test_user_ppk_login',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'PPK');

        if ($subKegiatan && $subKegiatan->kegiatan && $subKegiatan->kegiatan->program) {
            $response->assertJsonStructure([
                'user' => [
                    'programs' => [
                        '*' => ['kode_program', 'kode_bidang_urusan', 'nama_bidang_urusan', 'nama_program'],
                    ],
                    'kegiatans' => [
                        '*' => ['kode_kegiatan', 'kode_program', 'nama_kegiatan'],
                    ],
                    'sub_kegiatan' => [
                        '*' => [
                            'kode_sub_kegiatan',
                            'kode_kegiatan',
                            'nama_sub_kegiatan',
                            'kegiatan' => ['kode_kegiatan', 'nama_kegiatan', 'kode_program'],
                            'program' => ['kode_program', 'kode_bidang_urusan', 'nama_bidang_urusan', 'nama_program'],
                        ],
                    ],
                ],
            ]);

            $response->assertJsonPath('user.sub_kegiatan.0.kegiatan.kode_kegiatan', $subKegiatan->kegiatan->kode_kegiatan);
            $response->assertJsonPath('user.sub_kegiatan.0.program.kode_program', $subKegiatan->kegiatan->program->kode_program);
            $response->assertJsonPath('user.sub_kegiatan.0.program.kode_bidang_urusan', $subKegiatan->kegiatan->program->kode_bidang_urusan);
        }
    }

    public function test_ppk_user_me_includes_kegiatan_and_program_details(): void
    {
        $skpd = RefSkpd::first();
        $subKegiatan = RefSubKegiatan::with('kegiatan.program.bidangUrusan')->first();

        $user = User::create([
            'nama' => 'PPK Test Me',
            'username' => 'test_user_ppk_me',
            'password' => Hash::make('secret123'),
            'role' => 'PPK',
            'kode_skpd' => $skpd?->kode_skpd,
        ]);

        if ($subKegiatan) {
            $user->subKegiatan()->attach($subKegiatan->kode_sub_kegiatan);
        }

        $token = $user->createToken('ppk_token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.role', 'PPK');

        if ($subKegiatan && $subKegiatan->kegiatan && $subKegiatan->kegiatan->program) {
            $response->assertJsonPath('user.sub_kegiatan.0.kegiatan.kode_kegiatan', $subKegiatan->kegiatan->kode_kegiatan);
            $response->assertJsonPath('user.sub_kegiatan.0.program.kode_program', $subKegiatan->kegiatan->program->kode_program);
            $response->assertJsonPath('user.sub_kegiatan.0.program.kode_bidang_urusan', $subKegiatan->kegiatan->program->kode_bidang_urusan);
        }
    }
}
