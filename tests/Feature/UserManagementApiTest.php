<?php

namespace Tests\Feature;

use App\Models\RefSkpd;
use App\Models\RefSubKegiatan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::table('dev.user_sub_kegiatan')->whereIn('user_id', function ($query) {
            $query->select('id')->from('dev.users')->where('username', 'like', 'crud_test_%');
        })->delete();

        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', function ($query) {
                $query->select('id')->from('dev.users')->where('username', 'like', 'crud_test_%');
            })->delete();

        User::where('username', 'like', 'crud_test_%')->delete();

        parent::tearDown();
    }

    private function createAdminUser(): array
    {
        $admin = User::create([
            'nama' => 'Admin Test',
            'username' => 'crud_test_admin',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $token = $admin->createToken('admin_token')->plainTextToken;

        return [$admin, $token];
    }

    public function test_can_list_users_with_pagination_search_and_filters(): void
    {
        [$admin, $token] = $this->createAdminUser();

        $skpd = RefSkpd::first();
        $subKegiatan = RefSubKegiatan::first();

        $ppk = User::create([
            'nama' => 'Budi PPK',
            'username' => 'crud_test_ppk',
            'password' => Hash::make('password123'),
            'role' => 'PPK',
            'kode_skpd' => $skpd?->kode_skpd,
            'info' => [
                'nip' => '198001012010011002',
                'pangkat' => 'Penata',
                'golongan' => 'III/c',
                'jabatan' => 'Pejabat Pembuat Komitmen',
            ],
        ]);

        if ($subKegiatan) {
            $ppk->subKegiatan()->attach($subKegiatan->kode_sub_kegiatan);
        }

        $response = $this->withToken($token)
            ->getJson('/api/v1/users?search=Budi&role=PPK');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'nama',
                        'username',
                        'role',
                        'kode_skpd',
                        'info' => ['nip', 'pangkat', 'golongan', 'jabatan'],
                        'skpd',
                        'sub_kegiatan',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonFragment(['username' => 'crud_test_ppk']);
    }

    public function test_can_create_user_with_info_and_sub_kegiatan_mapping(): void
    {
        [$admin, $token] = $this->createAdminUser();

        $skpd = RefSkpd::first();
        $subKegiatans = RefSubKegiatan::limit(2)->get();
        $subKegiatanCodes = $subKegiatans->pluck('kode_sub_kegiatan')->toArray();

        $payload = [
            'nama' => 'Ahmad PPK',
            'username' => 'crud_test_ppk_create',
            'password' => 'secret123',
            'role' => 'PPK',
            'kode_skpd' => $skpd?->kode_skpd,
            'info' => [
                'nip' => '198505052011011003',
                'pangkat' => 'Penata Muda',
                'golongan' => 'III/a',
                'jabatan' => 'PPK Bagian Umum',
            ],
            'sub_kegiatan_ids' => $subKegiatanCodes,
        ];

        $response = $this->withToken($token)
            ->postJson('/api/v1/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('message', 'User created successfully')
            ->assertJsonPath('user.username', 'crud_test_ppk_create')
            ->assertJsonPath('user.info.nip', '198505052011011003')
            ->assertJsonPath('user.info.jabatan', 'PPK Bagian Umum');

        $this->assertDatabaseHas('dev.users', [
            'username' => 'crud_test_ppk_create',
            'role' => 'PPK',
        ]);

        $createdUser = User::where('username', 'crud_test_ppk_create')->first();
        $this->assertNotNull($createdUser);
        $this->assertCount(count($subKegiatanCodes), $createdUser->subKegiatan);
    }

    public function test_can_show_user_detail(): void
    {
        [$admin, $token] = $this->createAdminUser();

        $user = User::create([
            'nama' => 'User Show Test',
            'username' => 'crud_test_show',
            'password' => Hash::make('secret123'),
            'role' => 'operator',
            'info' => [
                'nip' => '199202022015011001',
                'jabatan' => 'Staff IT',
            ],
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.username', 'crud_test_show')
            ->assertJsonPath('user.info.nip', '199202022015011001');
    }

    public function test_can_update_user_and_sync_sub_kegiatan(): void
    {
        [$admin, $token] = $this->createAdminUser();

        $subKegiatans = RefSubKegiatan::limit(2)->get();

        $user = User::create([
            'nama' => 'Original Name',
            'username' => 'crud_test_update',
            'password' => Hash::make('secret123'),
            'role' => 'PPK',
            'info' => [
                'nip' => '199001012012011001',
                'pangkat' => 'Pengatur',
            ],
        ]);

        if ($subKegiatans->isNotEmpty()) {
            $user->subKegiatan()->attach($subKegiatans->first()->kode_sub_kegiatan);
        }

        $newSubCodes = $subKegiatans->count() > 1 ? [$subKegiatans->last()->kode_sub_kegiatan] : [];

        $updatePayload = [
            'nama' => 'Updated Name',
            'role' => 'PPK',
            'info' => [
                'jabatan' => 'PPK Kepala Bidang',
            ],
            'sub_kegiatan_ids' => $newSubCodes,
        ];

        $response = $this->withToken($token)
            ->putJson("/api/v1/users/{$user->id}", $updatePayload);

        $response->assertOk()
            ->assertJsonPath('message', 'User updated successfully')
            ->assertJsonPath('user.nama', 'Updated Name')
            ->assertJsonPath('user.info.pangkat', 'Pengatur')
            ->assertJsonPath('user.info.jabatan', 'PPK Kepala Bidang');

        $user->refresh();
        $this->assertSame('Updated Name', $user->nama);
        if ($subKegiatans->count() > 1) {
            $this->assertTrue($user->subKegiatan->pluck('kode_sub_kegiatan')->contains($subKegiatans->last()->kode_sub_kegiatan));
        }
    }

    public function test_can_delete_user(): void
    {
        [$admin, $token] = $this->createAdminUser();

        $user = User::create([
            'nama' => 'User to Delete',
            'username' => 'crud_test_delete',
            'password' => Hash::make('secret123'),
            'role' => 'operator',
        ]);

        $response = $this->withToken($token)
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJson(['message' => 'User deleted successfully']);

        $this->assertDatabaseMissing('dev.users', [
            'id' => $user->id,
        ]);
    }
}
