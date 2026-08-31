<?php

namespace Tests\Feature;

use App\Models\RefSkpd;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RefSkpdApiTest extends TestCase
{
    protected function tearDown(): void
    {
        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', function ($query) {
                $query->select('id')->from('dev.users')->where('username', 'like', 'skpd_test_%');
            })->delete();

        User::where('username', 'like', 'skpd_test_%')->delete();
        RefSkpd::where('kode_skpd', 'like', 'TEST_SKPD_%')->delete();

        parent::tearDown();
    }

    private function createAuthToken(): string
    {
        $user = User::create([
            'nama' => 'SKPD Tester',
            'username' => 'skpd_test_user',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        return $user->createToken('test_token')->plainTextToken;
    }

    public function test_can_list_skpd_with_parent_information_for_sub_units(): void
    {
        $token = $this->createAuthToken();

        $parent = RefSkpd::create([
            'kode_skpd' => 'TEST_SKPD_PARENT',
            'nama_skpd' => 'Dinas Pendidikan dan Kebudayaan',
            'parent_kode_skpd' => null,
        ]);

        $child = RefSkpd::create([
            'kode_skpd' => 'TEST_SKPD_CHILD',
            'nama_skpd' => 'Bidang Pembinaan SMP',
            'parent_kode_skpd' => $parent->kode_skpd,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-skpd?search=TEST_SKPD');

        $response->assertOk()
            ->assertJsonFragment([
                'kode_skpd' => 'TEST_SKPD_PARENT',
                'nama_skpd' => 'Dinas Pendidikan dan Kebudayaan',
                'parent_kode_skpd' => null,
                'is_sub_unit' => false,
            ])
            ->assertJsonFragment([
                'kode_skpd' => 'TEST_SKPD_CHILD',
                'nama_skpd' => 'Bidang Pembinaan SMP',
                'parent_kode_skpd' => 'TEST_SKPD_PARENT',
                'is_sub_unit' => true,
                'parent' => [
                    'kode_skpd' => 'TEST_SKPD_PARENT',
                    'nama_skpd' => 'Dinas Pendidikan dan Kebudayaan',
                ],
            ]);
    }

    public function test_can_filter_sub_units(): void
    {
        $token = $this->createAuthToken();

        $parent = RefSkpd::create([
            'kode_skpd' => 'TEST_SKPD_PARENT_2',
            'nama_skpd' => 'Dinas Kesehatan',
            'parent_kode_skpd' => null,
        ]);

        $child = RefSkpd::create([
            'kode_skpd' => 'TEST_SKPD_CHILD_2',
            'nama_skpd' => 'Puskesmas Maju Jaya',
            'parent_kode_skpd' => $parent->kode_skpd,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/ref-skpd?search=TEST_SKPD_&is_sub_unit=true');

        $response->assertOk()
            ->assertJsonPath('data.0.kode_skpd', 'TEST_SKPD_CHILD_2')
            ->assertJsonPath('data.0.is_sub_unit', true);

        $items = collect($response->json('data'));
        $this->assertFalse($items->contains('kode_skpd', 'TEST_SKPD_PARENT_2'));
    }
}
