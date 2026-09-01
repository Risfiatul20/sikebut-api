<?php

namespace Tests\Feature;

use App\Models\RefKegiatan;
use App\Models\RefProgram;
use App\Models\RefSkpd;
use App\Models\RefSubKegiatan;
use App\Models\SipdPenetapanApbd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdentifikasiKebutuhanApiTest extends TestCase
{
    private const PREFIX = 'ik_test_';

    protected function tearDown(): void
    {
        User::where('username', 'like', self::PREFIX.'%')->delete();

        parent::tearDown();
    }

    private function createToken(?string $kodeSkpd, string $role = 'operator', array $subs = []): string
    {
        $user = User::create([
            'nama' => 'IK Tester',
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

        return $user->createToken('ik_token')->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $kodeSkpd, string $subKegiatan, string $kegiatan, string $program, int $sipdId): array
    {
        return [
            'nama_paket' => 'IK TEST Paket '.uniqid(),
            'cara_pengadaan' => 'Tender',
            'jenis_pengadaan' => 'Pekerjaan Konstruksi',
            'kode_skpd' => $kodeSkpd,
            'kode_sub_kegiatan' => $subKegiatan,
            'kode_kegiatan' => $kegiatan,
            'kode_program' => $program,
            'waktu_pemanfaatan_awal' => '2026-01-05',
            'waktu_pemanfaatan_akhir' => '2026-01-20',
            'form_data' => ['uraian' => 'Kebutuhan meja kantor', 'volume' => 10],
            'anggaran' => [
                ['id_sipd_penetapan' => $sipdId, 'pagu' => 15000000],
                ['id_sipd_penetapan' => $sipdId, 'pagu' => 5000000.5],
            ],
        ];
    }

    private function resolveRefs(): array
    {
        $sub = RefSubKegiatan::first();

        if (! $sub) {
            $this->markTestSkipped('No ref_sub_kegiatan data found');
        }

        $kegiatan = RefKegiatan::find($sub->kode_kegiatan);
        $skpd = RefSkpd::first();
        $sipd = SipdPenetapanApbd::query()->whereNotNull('kode_sub_kegiatan')->first();

        if (! $kegiatan || ! $skpd || ! $sipd) {
            $this->markTestSkipped('Missing reference data for identifikasi kebutuhan test');
        }

        $program = RefProgram::find($kegiatan->kode_program);

        return [$skpd->kode_skpd, $sub->kode_sub_kegiatan, $kegiatan->kode_kegiatan, $program->kode_program, $sipd->id];
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/identifikasi-kebutuhan')->assertUnauthorized();
    }

    public function test_anggaran_rows_are_required(): void
    {
        [$skpd, $sub, $kegiatan, $program, $sipdId] = $this->resolveRefs();
        $token = $this->createToken($skpd);

        $payload = $this->payload($skpd, $sub, $kegiatan, $program, $sipdId);
        unset($payload['anggaran']);

        $this->withToken($token)
            ->postJson('/api/v1/identifikasi-kebutuhan', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['anggaran']);
    }

    public function test_can_create_with_nested_anggaran(): void
    {
        [$skpd, $sub, $kegiatan, $program, $sipdId] = $this->resolveRefs();
        $token = $this->createToken($skpd);

        $response = $this->withToken($token)
            ->postJson('/api/v1/identifikasi-kebutuhan', $this->payload($skpd, $sub, $kegiatan, $program, $sipdId));

        $response->assertCreated()
            ->assertJsonPath('message', 'Identifikasi kebutuhan created successfully')
            ->assertJsonPath('data.status_review', 'Draft')
            ->assertJsonPath('data.total_pagu', '20000000.50')
            ->assertJsonPath('data.jumlah_anggaran', 2)
            ->assertJsonPath('data.nama_sub_kegiatan', RefSubKegiatan::find($sub)->nama_sub_kegiatan)
            ->assertJsonPath('data.nama_program', RefProgram::find($program)->nama_program)
            ->assertJsonPath('data.anggaran.0.sipd_penetapan.kode_rekening', SipdPenetapanApbd::find($sipdId)->kode_rekening);

        $this->assertSame(2, DB::table('dev.identifikasi_kebutuhan_anggaran')
            ->where('identifikasi_kebutuhan_id', $response->json('data.id'))
            ->count());
    }

    public function test_can_list_with_anggaran_and_total_pagu(): void
    {
        [$skpd, $sub, $kegiatan, $program, $sipdId] = $this->resolveRefs();
        $token = $this->createToken($skpd);

        $this->withToken($token)
            ->postJson('/api/v1/identifikasi-kebutuhan', $this->payload($skpd, $sub, $kegiatan, $program, $sipdId))
            ->assertCreated();

        $response = $this->withToken($token)
            ->getJson('/api/v1/identifikasi-kebutuhan?status_review=Draft');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'nama_paket',
                        'status_review',
                        'kode_skpd',
                        'nama_skpd',
                        'kode_sub_kegiatan',
                        'nama_sub_kegiatan',
                        'kode_kegiatan',
                        'nama_kegiatan',
                        'kode_program',
                        'nama_program',
                        'waktu_pemanfaatan_awal',
                        'form_data',
                        'total_pagu',
                        'jumlah_anggaran',
                        'anggaran' => [
                            '*' => ['id', 'id_sipd_penetapan', 'pagu', 'sipd_penetapan'],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_show_update_and_delete(): void
    {
        [$skpd, $sub, $kegiatan, $program, $sipdId] = $this->resolveRefs();
        $token = $this->createToken($skpd);

        $created = $this->withToken($token)
            ->postJson('/api/v1/identifikasi-kebutuhan', $this->payload($skpd, $sub, $kegiatan, $program, $sipdId))
            ->assertCreated()
            ->json('data');

        $id = $created['id'];

        $this->withToken($token)
            ->getJson('/api/v1/identifikasi-kebutuhan/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $anggaran = $created['anggaran'];

        $updatePayload = [
            'nama_paket' => 'IK TEST Paket Direvisi',
            'status_review' => 'Diajukan',
            'anggaran' => [
                ['id' => $anggaran[0]['id'], 'id_sipd_penetapan' => $sipdId, 'pagu' => 999],
                ['id_sipd_penetapan' => $sipdId, 'pagu' => 1],
            ],
        ];

        $updated = $this->withToken($token)
            ->putJson('/api/v1/identifikasi-kebutuhan/'.$id, $updatePayload)
            ->assertOk()
            ->assertJsonPath('data.nama_paket', 'IK TEST Paket Direvisi')
            ->assertJsonPath('data.status_review', 'Diajukan')
            ->assertJsonPath('data.jumlah_anggaran', 2)
            ->assertJsonPath('data.total_pagu', '1000.00')
            ->json('data');

        $this->assertSame((int) $anggaran[0]['id'], (int) $updated['anggaran'][0]['id']);

        $this->withToken($token)
            ->deleteJson('/api/v1/identifikasi-kebutuhan/'.$id)
            ->assertOk()
            ->assertJson(['message' => 'Identifikasi kebutuhan deleted successfully']);

        $this->assertDatabaseMissing('dev.identifikasi_kebutuhan', ['id' => $id]);
        $this->assertDatabaseMissing('dev.identifikasi_kebutuhan_anggaran', ['identifikasi_kebutuhan_id' => $id]);
    }

    public function test_ppk_cannot_access_record_outside_mapping(): void
    {
        [$skpd, $sub, $kegiatan, $program, $sipdId] = $this->resolveRefs();

        $ownerToken = $this->createToken($skpd);
        $id = $this->withToken($ownerToken)
            ->postJson('/api/v1/identifikasi-kebutuhan', $this->payload($skpd, $sub, $kegiatan, $program, $sipdId))
            ->assertCreated()
            ->json('data.id');

        $otherSub = RefSubKegiatan::where('kode_sub_kegiatan', '!=', $sub)->value('kode_sub_kegiatan');

        if (! $otherSub) {
            $this->markTestSkipped('Need a second sub kegiatan for PPK scoping check');
        }

        $ppkToken = $this->createToken($skpd, 'PPK', [$otherSub]);

        $this->app['auth']->forgetGuards();

        $this->withToken($ppkToken)->getJson('/api/v1/identifikasi-kebutuhan/'.$id)->assertNotFound();
        $this->withToken($ppkToken)->deleteJson('/api/v1/identifikasi-kebutuhan/'.$id)->assertNotFound();

        $list = $this->withToken($ppkToken)->getJson('/api/v1/identifikasi-kebutuhan')->assertOk();
        $this->assertEmpty($list->json('data'));
    }

    public function test_empty_kode_skpd_param_does_not_widen_scope(): void
    {
        [$skpd, $sub, $kegiatan, $program, $sipdId] = $this->resolveRefs();
        $token = $this->createToken($skpd);

        $response = $this->withToken($token)
            ->getJson('/api/v1/identifikasi-kebutuhan?kode_skpd=');

        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertSame($skpd, $item['kode_skpd']);
        }
    }
}
