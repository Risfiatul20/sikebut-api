Struktur data dan kontrak API yang perlu disediakan oleh backend (Laravel) untuk mendukung halaman **Laporan Rekapitulasi Berjenjang** adalah sebagai berikut:

---

### 1. Spesifikasi Endpoint

- **URL**: `GET /api/v1/laporan/rekap`
- **Autentikasi**: `Bearer Token` (`auth:sanctum`)
- **Query Parameters**:
  | Parameter | Tipe | Status | Keterangan |
  | :--- | :--- | :--- | :--- |
  | `tahun` | `integer` | Opsional | Tahun anggaran penetapan APBD (default: tahun berjalan, misal `2026`). |
  | `kode_skpd` | `string` | Opsional | Filter untuk hanya menampilkan OPD tertentu. Jika user login bukan Admin (misal Kepala OPD/PPK), otomatis dibatasi ke SKPD user. |
  | `versi` | `integer` | Opsional | Filter versi APBD penetapan tertentu (default: versi aktif terbaru). |

---

### 2. Format Response JSON (`200 OK`)

```json
{
  "status": "success",
  "tahun": 2026,
  "summary": {
    "total_pagu": 468000000000.00,
    "total_belanja_non_pengadaan": 193000000000.00,
    "total_belanja_pengadaan": 275000000000.00,
    "identifikasi": {
      "jumlah": { "paket": 85, "pagu": 245750000000.00 },
      "penyedia": { "paket": 74, "pagu": 226250000000.00 },
      "swakelola": { "paket": 11, "pagu": 19500000000.00 }
    },
    "keterisian": 89.36
  },
  "data": [
    {
      "id": "opd-1.01.0.00.0.00.01.0000",
      "no": "1",
      "kode": "1.01.0.00.0.00.01.0000",
      "nama": "DINAS PENDIDIKAN DAN KEBUDAYAAN",
      "level": 1,
      "type": "opd",
      "pagu": 150000000000.00,
      "belanja_non_pengadaan": 105000000000.00,
      "belanja_pengadaan": 45000000000.00,
      "identifikasi": {
        "jumlah": { "paket": 18, "pagu": 38250000000.00 },
        "penyedia": { "paket": 14, "pagu": 32250000000.00 },
        "swakelola": { "paket": 4, "pagu": 6000000000.00 }
      },
      "keterisian": 85.00,
      "children": [
        {
          "id": "sub-1.01.0.00.0.00.01.0001",
          "no": "1.1",
          "kode": "1.01.0.00.0.00.01.0001",
          "nama": "Bidang Pembinaan Sekolah Menengah Pertama (SMP)",
          "level": 2,
          "type": "sub_unit",
          "pagu": 45000000000.00,
          "belanja_non_pengadaan": 25000000000.00,
          "belanja_pengadaan": 20000000000.00,
          "identifikasi": {
            "jumlah": { "paket": 10, "pagu": 18000000000.00 },
            "penyedia": { "paket": 8, "pagu": 15500000000.00 },
            "swakelola": { "paket": 2, "pagu": 2500000000.00 }
          },
          "keterisian": 90.00,
          "children": [
            {
              "id": "prog-1.01.02",
              "no": "1.1.1",
              "kode": "1.01.02",
              "nama": "PROGRAM PENGELOLAAN PENDIDIKAN",
              "level": 3,
              "type": "program",
              "pagu": 45000000000.00,
              "belanja_non_pengadaan": 25000000000.00,
              "belanja_pengadaan": 20000000000.00,
              "identifikasi": {
                "jumlah": { "paket": 10, "pagu": 18000000000.00 },
                "penyedia": { "paket": 8, "pagu": 15500000000.00 },
                "swakelola": { "paket": 2, "pagu": 2500000000.00 }
              },
              "keterisian": 90.00,
              "children": [
                {
                  "id": "keg-1.01.02.1.01",
                  "no": "1.1.1.1",
                  "kode": "1.01.02.1.01",
                  "nama": "Pengelolaan Pendidikan Sekolah Menengah Pertama",
                  "level": 4,
                  "type": "kegiatan",
                  "pagu": 25000000000.00,
                  "belanja_non_pengadaan": 12000000000.00,
                  "belanja_pengadaan": 13000000000.00,
                  "identifikasi": {
                    "jumlah": { "paket": 6, "pagu": 11700000000.00 },
                    "penyedia": { "paket": 5, "pagu": 10200000000.00 },
                    "swakelola": { "paket": 1, "pagu": 1500000000.00 }
                  },
                  "keterisian": 90.00,
                  "children": [
                    {
                      "id": "subkeg-1.01.02.1.01.0036",
                      "no": "1.1.1.1.1",
                      "kode": "1.01.02.1.01.0036",
                      "nama": "Pengadaan Mebel Sekolah SMP Negeri",
                      "level": 5,
                      "type": "sub_kegiatan",
                      "pagu": 5000000000.00,
                      "belanja_non_pengadaan": 500000000.00,
                      "belanja_pengadaan": 4500000000.00,
                      "identifikasi": {
                        "jumlah": { "paket": 3, "pagu": 4500000000.00 },
                        "penyedia": { "paket": 3, "pagu": 4500000000.00 },
                        "swakelola": { "paket": 0, "pagu": 0.00 }
                      },
                      "keterisian": 100.00
                    }
                  ]
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

---

### 3. Penjelasan Field & Aturan Kalkulasi (Mapping Logic)

| Field | Tipe Data | Sumber Data di DB / Rumus Kalkulasi |
| :--- | :--- | :--- |
| `id` | `string` | Unique identifier (contoh: `opd-1.01...`, `subkeg-1.01.02.1.01.0036`). |
| `no` | `string` | Penomoran hierarki (1, 1.1, 1.1.1, 1.1.1.1, 1.1.1.1.1). |
| `kode` | `string` | Kode unik entitas pada tingkatan tersebut (`kode_skpd`, `kode_program`, dsb). |
| `nama` | `string` | Nama resmi entitas (`nama_skpd`, `nama_program`, dsb). |
| `level` | `integer` | Tingkat kedalaman (`1` = OPD, `2` = Sub Unit, `3` = Program, `4` = Kegiatan, `5` = Sub Kegiatan). |
| `type` | `string` | `"opd"` \| `"sub_unit"` \| `"program"` \| `"kegiatan"` \| `"sub_kegiatan"`. |
| `pagu` | `numeric` | `SUM(dev.sipd_penetapan_apbd.pagu)` di bawah hierarki bersangkutan. |
| `belanja_non_pengadaan` | `numeric` | `SUM(pagu)` di mana `dev.akun_indikator_rkbmd.is_belanja_pengadaan = false`. |
| `belanja_pengadaan` | `numeric` | `SUM(pagu)` di mana `dev.akun_indikator_rkbmd.is_belanja_pengadaan = true`. |
| `identifikasi.jumlah.paket` | `integer` | `COUNT(DISTINCT dev.identifikasi_kebutuhan.id)` yang telah dientri. |
| `identifikasi.jumlah.pagu` | `numeric` | `SUM(dev.identifikasi_kebutuhan_anggaran.pagu)` yang telah dientri. |
| `identifikasi.penyedia.paket` | `integer` | `COUNT(id)` di mana `cara_pengadaan = 'Penyedia'`. |
| `identifikasi.penyedia.pagu` | `numeric` | `SUM(anggaran.pagu)` di mana `cara_pengadaan = 'Penyedia'`. |
| `identifikasi.swakelola.paket`| `integer` | `COUNT(id)` di mana `cara_pengadaan = 'Swakelola'`. |
| `identifikasi.swakelola.pagu` | `numeric` | `SUM(anggaran.pagu)` di mana `cara_pengadaan = 'Swakelola'`. |
| `keterisian` | `numeric (2 desimal)` | `(identifikasi.jumlah.pagu / belanja_pengadaan) * 100`. Jika `belanja_pengadaan == 0`, bernilai `0.00`. |
| `children` | `array` | Sub-tingkatan di bawahnya (Level 1 berisi Level 2, dst. Level 5 tidak memiliki children). |

---

### 4. Alur Agregasi di Controller Laravel

Backend disarankan melakukan kalkulasi dengan konsep **Bottom-Up Rollup**:
1. Query agregasi dihitung terlebih dahulu di level terendah (**Level 5: Sub Kegiatan**).
2. Nilai Level 4 (**Kegiatan**) adalah akumulasi *sum* dari seluruh Sub Kegiatan di bawahnya.
3. Nilai Level 3 (**Program**) adalah akumulasi *sum* dari seluruh Kegiatan di bawahnya.
4. Nilai Level 2 (**Sub Unit**) adalah akumulasi *sum* dari seluruh Program di bawahnya.
5. Nilai Level 1 (**OPD**) adalah akumulasi *sum* dari seluruh Sub Unit di bawahnya.
6. Nilai `summary` root adalah akumulasi *sum* dari seluruh Level 1 (OPD).