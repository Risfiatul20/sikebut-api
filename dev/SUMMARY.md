# Dokumentasi API SIKEBUT

**Base URL**: `http://<host>:<port>/api/v1`  
**Autentikasi**: Laravel Sanctum (`Authorization: Bearer <access_token>`)  
**Header Wajib**:
```http
Accept: application/json
Content-Type: application/json
```

---

## 1. Autentikasi (`/auth`)

### 1.1 Login
- **Endpoint**: `POST /api/v1/auth/login`
- **Akses**: Public
- **Request Body**:
```json
{
  "username": "ppk_umum",
  "password": "password123",
  "device_name": "postman" // opsional
}
```
- **Response `200 OK` (Role PPK)**:
```json
{
  "message": "Login successful",
  "token_type": "Bearer",
  "access_token": "1|eXamPLeTokEn1234567890abcdef...",
  "user": {
    "id": 12,
    "nama": "Budi Santoso, S.STP",
    "username": "ppk_umum",
    "role": "PPK",
    "kode_skpd": "1.01.0.00.0.00.01.0000",
    "info": {
      "nip": "198505052010011002",
      "pangkat": "Penata Tk. I",
      "golongan": "III/d",
      "jabatan": "Pejabat Pembuat Komitmen",
      "no_hp": "081234567890",
      "email_dinas": "budi@pemda.go.id"
    },
    "created_at": "2026-09-01T08:00:00.000000Z",
    "skpd": {
      "kode_skpd": "1.01.0.00.0.00.01.0000",
      "nama_skpd": "DINAS PENDIDIKAN DAN KEBUDAYAAN",
      "parent_kode_skpd": null
    },
    "sub_kegiatan": [
      {
        "kode_sub_kegiatan": "1.01.02.1.01.0012",
        "kode_kegiatan": "1.01.02.1.01",
        "nama_sub_kegiatan": "Penyediaan Gaji dan Tunjangan ASN",
        "kegiatan": {
          "kode_kegiatan": "1.01.02.1.01",
          "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah",
          "kode_program": "1.01.02"
        },
        "program": {
          "kode_program": "1.01.02",
          "kode_bidang_urusan": "1.01",
          "nama_bidang_urusan": "URUSAN PEMERINTAHAN BIDANG PENDIDIKAN",
          "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA"
        }
      }
    ],
    "programs": [
      {
        "kode_program": "1.01.02",
        "kode_bidang_urusan": "1.01",
        "nama_bidang_urusan": "URUSAN PEMERINTAHAN BIDANG PENDIDIKAN",
        "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA"
      }
    ],
    "kegiatans": [
      {
        "kode_kegiatan": "1.01.02.1.01",
        "kode_program": "1.01.02",
        "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah"
      }
    ]
  }
}
```

### 1.2 User Profile (`/me`)
- **Endpoint**: `GET /api/v1/auth/me`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**:
```json
{
  "user": {
    "id": 12,
    "nama": "Budi Santoso, S.STP",
    "username": "ppk_umum",
    "role": "PPK",
    "kode_skpd": "1.01.0.00.0.00.01.0000",
    "info": {
      "nip": "198505052010011002",
      "pangkat": "Penata Tk. I",
      "golongan": "III/d",
      "jabatan": "Pejabat Pembuat Komitmen"
    },
    "created_at": "2026-09-01T08:00:00.000000Z",
    "skpd": {
      "kode_skpd": "1.01.0.00.0.00.01.0000",
      "nama_skpd": "DINAS PENDIDIKAN DAN KEBUDAYAAN",
      "parent_kode_skpd": null
    },
    "sub_kegiatan": [
      {
        "kode_sub_kegiatan": "1.01.02.1.01.0012",
        "kode_kegiatan": "1.01.02.1.01",
        "nama_sub_kegiatan": "Penyediaan Gaji dan Tunjangan ASN",
        "kegiatan": {
          "kode_kegiatan": "1.01.02.1.01",
          "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah",
          "kode_program": "1.01.02"
        },
        "program": {
          "kode_program": "1.01.02",
          "kode_bidang_urusan": "1.01",
          "nama_bidang_urusan": "URUSAN PEMERINTAHAN BIDANG PENDIDIKAN",
          "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA"
        }
      }
    ],
    "programs": [
      {
        "kode_program": "1.01.02",
        "kode_bidang_urusan": "1.01",
        "nama_bidang_urusan": "URUSAN PEMERINTAHAN BIDANG PENDIDIKAN",
        "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA"
      }
    ],
    "kegiatans": [
      {
        "kode_kegiatan": "1.01.02.1.01",
        "kode_program": "1.01.02",
        "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah"
      }
    ]
  }
}
```

### 1.3 Logout
- **Endpoint**: `POST /api/v1/auth/logout`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**:
```json
{
  "message": "Logout successful"
}
```

---

## 2. Manajemen Pengguna (`/users`)

### 2.1 List Pengguna
- **Endpoint**: `GET /api/v1/users`
- **Akses**: `auth:sanctum`
- **Query Parameters**:
  - `search` (`string`): Pencarian pada `nama`, `username`, `role`, `nip`, `jabatan`, `no_hp`, `email_dinas`.
  - `role` (`string`): Filter role (`ADMIN`, `PPK`, `OPERATOR`, dll).
  - `kode_skpd` (`string`): Filter SKPD.
  - `sort_by` (`string`): `id` | `nama` | `username` | `role` | `kode_skpd` | `created_at`.
  - `sort_direction` (`string`): `asc` | `desc` (default `desc`).
  - `per_page` (`int`): Default `15`.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "id": 1,
      "nama": "Administrator Utama",
      "username": "admin",
      "role": "ADMIN",
      "kode_skpd": null,
      "info": null,
      "created_at": "2026-09-01T00:00:00.000000Z",
      "skpd": null,
      "sub_kegiatan": []
    }
  ],
  "links": {
    "first": ".../api/v1/users?page=1",
    "last": ".../api/v1/users?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": ".../api/v1/users",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

### 2.2 Tambah Pengguna
- **Endpoint**: `POST /api/v1/users`
- **Akses**: `auth:sanctum`
- **Request Body**:
```json
{
  "nama": "Ahmad Subagio",
  "username": "ahmad_ppk",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "role": "PPK",
  "kode_skpd": "1.01.0.00.0.00.01.0000",
  "info": {
    "nip": "198203042008011005",
    "pangkat": "Pembina",
    "golongan": "IV/a",
    "jabatan": "PPK Bidang Pembinaan",
    "no_hp": "081399887766",
    "email_dinas": "ahmad@pemda.go.id"
  },
  "sub_kegiatan_ids": [
    "1.01.02.1.01.0012",
    "1.01.02.1.01.0013"
  ]
}
```
- **Response `201 Created`**:
```json
{
  "message": "User created successfully",
  "user": {
    "id": 15,
    "nama": "Ahmad Subagio",
    "username": "ahmad_ppk",
    "role": "PPK",
    "kode_skpd": "1.01.0.00.0.00.01.0000",
    "info": {
      "nip": "198203042008011005",
      "pangkat": "Pembina",
      "golongan": "IV/a",
      "jabatan": "PPK Bidang Pembinaan",
      "no_hp": "081399887766",
      "email_dinas": "ahmad@pemda.go.id"
    },
    "created_at": "2026-09-02T00:15:00.000000Z",
    "skpd": {
      "kode_skpd": "1.01.0.00.0.00.01.0000",
      "nama_skpd": "DINAS PENDIDIKAN DAN KEBUDAYAAN",
      "parent_kode_skpd": null
    },
    "sub_kegiatan": [
      {
        "kode_sub_kegiatan": "1.01.02.1.01.0012",
        "kode_kegiatan": "1.01.02.1.01",
        "nama_sub_kegiatan": "Penyediaan Gaji dan Tunjangan ASN",
        "kegiatan": {
          "kode_kegiatan": "1.01.02.1.01",
          "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah",
          "kode_program": "1.01.02"
        },
        "program": {
          "kode_program": "1.01.02",
          "kode_bidang_urusan": "1.01",
          "nama_bidang_urusan": "URUSAN PEMERINTAHAN BIDANG PENDIDIKAN",
          "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA"
        }
      }
    ],
    "programs": [
      {
        "kode_program": "1.01.02",
        "kode_bidang_urusan": "1.01",
        "nama_bidang_urusan": "URUSAN PEMERINTAHAN BIDANG PENDIDIKAN",
        "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA"
      }
    ],
    "kegiatans": [
      {
        "kode_kegiatan": "1.01.02.1.01",
        "kode_program": "1.01.02",
        "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah"
      }
    ]
  }
}
```

### 2.3 Detail Pengguna
- **Endpoint**: `GET /api/v1/users/{id}`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**: Mengembalikan objek `user` seperti format di atas.

### 2.4 Update Pengguna
- **Endpoint**: `PUT /api/v1/users/{id}`
- **Akses**: `auth:sanctum`
- **Request Body**:
```json
{
  "nama": "Ahmad Subagio, M.Si",
  "info": {
    "jabatan": "Kepala Bidang Pembinaan & PPK"
  },
  "sub_kegiatan_ids": [
    "1.01.02.1.01.0012"
  ]
}
```
- **Response `200 OK`**:
```json
{
  "message": "User updated successfully",
  "user": { ... }
}
```

### 2.5 Hapus Pengguna
- **Endpoint**: `DELETE /api/v1/users/{id}`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**:
```json
{
  "message": "User deleted successfully"
}
```

---

## 3. Data Referensi

### 3.1 Referensi SKPD (`/ref-skpd`)
- **Endpoint**: `GET /api/v1/ref-skpd`
- **Akses**: `auth:sanctum`
- **Query Parameters**:
  - `search` (`string`): Kode atau nama SKPD.
  - `is_sub_unit` (`bool`): `true` untuk Sub Unit, `false` untuk Dinas Induk (SKPD).
  - `parent_kode_skpd` (`string`): Filter berdasarkan kode SKPD induk.
  - `per_page` (`int`): `0` (semua) atau integer paging.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "kode_skpd": "1.01.0.00.0.00.01.0000",
      "nama_skpd": "DINAS PENDIDIKAN DAN KEBUDAYAAN",
      "parent_kode_skpd": null,
      "parent": null
    }
  ]
}
```

### 3.2 Referensi Akun Rekening (`/ref-akun`)
- **Endpoint**: `GET /api/v1/ref-akun`
- **Akses**: `auth:sanctum`
- **Fitur Otomatis**: Jika `kode_sub_unit` tidak dikirim, otomatis di-scope ke `kode_skpd` milik user yang sedang login via data penetapan APBD.
- **Query Parameters**:
  - `kode_sub_unit` (`string`): Kode SKPD sub unit.
  - `search` (`string`): Pencarian kode/nama akun.
  - `level` (`int`): Level akun (1 s.d. 6).
  - `parent` (`string`): Kode parent akun.
  - `is_belanja_pengadaan` (`bool`): Filter belanja pengadaan.
  - `is_rkbmd_pengadaan` (`bool`): Filter RKBMD pengadaan.
  - `is_rkbmd_pemeliharaan_rehab` (`bool`): Filter RKBMD pemeliharaan rehab.
  - `is_rkbmd_pemeliharaan_rutin` (`bool`): Filter RKBMD pemeliharaan rutin.
  - `per_page` (`int`): `0` (semua) atau nilai paginasi.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "kode_akun": "5.1.02.01.01.0024",
      "nama_akun": "Belanja Alat/Bahan untuk Kegiatan Kantor- Alat Tulis Kantor",
      "level_akun": 6,
      "parent_kode_akun": "5.1.02.01.01",
      "indikator": {
        "is_belanja_pengadaan": true,
        "is_rkbmd_pengadaan": false,
        "is_rkbmd_pemeliharaan_rehab": false,
        "is_rkbmd_pemeliharaan_rutin": false
      }
    }
  ]
}
```

### 3.3 Referensi Akun Rekening View / Flat Table (`/ref-akun/view`)
- **Endpoint**: `GET /api/v1/ref-akun/view`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "kode_akun_2": "5.1",
      "nama_akun_2": "BELANJA OPERASI",
      "kode_akun_3": "5.1.02",
      "nama_akun_3": "Belanja Barang dan Jasa",
      "kode_akun_4": "5.1.02.01",
      "nama_akun_4": "Belanja Barang",
      "kode_akun_5": "5.1.02.01.01",
      "nama_akun_5": "Belanja Barang Pakai Habis",
      "kode_akun_6": "5.1.02.01.01.0024",
      "nama_akun_6": "Belanja Alat/Bahan untuk Kegiatan Kantor- Alat Tulis Kantor",
      "is_belanja_pengadaan": true,
      "is_rkbmd_pengadaan": false,
      "is_rkbmd_pemeliharaan_rehab": false,
      "is_rkbmd_pemeliharaan_rutin": false
    }
  ]
}
```

### 3.4 Referensi Program (`/ref-program`)
- **Endpoint**: `GET /api/v1/ref-program`
- **Akses**: `auth:sanctum` (Auto-scoping jika role PPK)
- **Query Parameters**: `kode_skpd`, `kode_sub_kegiatan`, `search`, `per_page`.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "kode_program": "1.01.02",
      "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA",
      "kode_bidang_urusan": "1.01",
      "bidang_urusan": {
        "kode_bidang_urusan": "1.01",
        "kode_urusan": "1",
        "nama_bidang_urusan": "URUSAN PEMERINTAHAN BIDANG PENDIDIKAN"
      }
    }
  ]
}
```

### 3.5 Referensi Kegiatan (`/ref-kegiatan`)
- **Endpoint**: `GET /api/v1/ref-kegiatan`
- **Akses**: `auth:sanctum` (Auto-scoping jika role PPK)
- **Query Parameters**: `kode_skpd`, `kode_program`, `kode_sub_kegiatan`, `search`, `per_page`.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "kode_kegiatan": "1.01.02.1.01",
      "kode_program": "1.01.02",
      "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah"
    }
  ]
}
```

### 3.6 Referensi Sub Kegiatan (`/ref-sub-kegiatan`)
- **Endpoint**: `GET /api/v1/ref-sub-kegiatan`
- **Akses**: `auth:sanctum` (Auto-scoping jika role PPK)
- **Query Parameters**: `kode_skpd`, `kode_kegiatan`, `search`, `per_page`.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "kode_sub_kegiatan": "1.01.02.1.01.0012",
      "kode_kegiatan": "1.01.02.1.01",
      "nama_sub_kegiatan": "Penyediaan Gaji dan Tunjangan ASN"
    }
  ]
}
```

---

## 4. SIPD Penetapan APBD (`/sipd-penetapan-apbd`)

### 4.1 Modal Rekapitulasi & Standar Harga SIPD
- **Endpoint**: `GET /api/v1/sipd-penetapan-apbd/modal`
- **Akses**: `auth:sanctum` (Auto-scoping & proteksi 403 untuk PPK)
- **Query Parameters**:
  - `kode_sub_kegiatan` (*Wajib*): Kode sub kegiatan (contoh: `1.01.02.1.01.0012`).
  - `kode_sub_unit` (*Opsional*): Kode sub unit SKPD (default `kode_skpd` user login).
  - `tahun` (*Opsional*): Tahun anggaran (contoh: `2026`).
- **Response `200 OK`**:
```json
{
  "status": "success",
  "data": {
    "skpd": {
      "kode_skpd": "1.01.0.00.0.00.01.0000",
      "nama_skpd": "DINAS PENDIDIKAN DAN KEBUDAYAAN",
      "total_pagu": "15000000000.00",
      "total_pagu_pengadaan": "5000000000.00",
      "total_pagu_non_pengadaan": "10000000000.00",
      "total_kebutuhan_anggaran": "1200000000.00",
      "sisa_pagu_pengadaan": "3800000000.00"
    },
    "sub_unit": {
      "kode_sub_unit": "1.01.0.00.0.00.01.0001",
      "nama_sub_unit": "SMP NEGERI 1",
      "total_pagu": "2500000000.00",
      "total_pagu_pengadaan": "1000000000.00",
      "total_pagu_non_pengadaan": "1500000000.00",
      "total_kebutuhan_anggaran": "200000000.00",
      "sisa_pagu_pengadaan": "800000000.00"
    },
    "program": {
      "kode_program": "1.01.02",
      "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA",
      "total_pagu": "1200000000.00",
      "total_pagu_pengadaan": "400000000.00",
      "total_pagu_non_pengadaan": "800000000.00",
      "total_kebutuhan_anggaran": "50000000.00",
      "sisa_pagu_pengadaan": "350000000.00"
    },
    "kegiatan": {
      "kode_kegiatan": "1.01.02.1.01",
      "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah",
      "total_pagu": "500000000.00",
      "total_pagu_pengadaan": "150000000.00",
      "total_pagu_non_pengadaan": "350000000.00",
      "total_kebutuhan_anggaran": "30000000.00",
      "sisa_pagu_pengadaan": "120000000.00"
    },
    "sub_kegiatan": {
      "kode_sub_kegiatan": "1.01.02.1.01.0012",
      "nama_sub_kegiatan": "Penyediaan Gaji dan Tunjangan ASN",
      "total_pagu": "200000000.00",
      "total_pagu_pengadaan": "50000000.00",
      "total_pagu_non_pengadaan": "150000000.00",
      "total_kebutuhan_anggaran": "10000000.00",
      "sisa_pagu_pengadaan": "40000000.00"
    },
    "standar_harga": [
      {
        "id_sipd_penetapan": 451,
        "kode_rekening": "5.1.02.01.01.0024",
        "nama_rekening": "Belanja Alat/Bahan untuk Kegiatan Kantor- Alat Tulis Kantor",
        "kode_standar_harga": "SSH-2026-001",
        "nama_standar_harga": "Kertas HVS A4 80gr Sinar Dunia",
        "kode_sumber_dana": "1.1.01.01",
        "nama_sumber_dana": "PENDAPATAN ASLI DAERAH (PAD)",
        "is_belanja_pengadaan": true,
        "is_rkbmd_pengadaan": false,
        "is_rkbmd_pemeliharaan_rehab": false,
        "is_rkbmd_pemeliharaan_rutin": false,
        "pagu": "25000000.00",
        "total_kebutuhan_anggaran": "5000000.00",
        "sisa_pagu": "20000000.00"
      }
    ]
  }
}
```

### 4.2 List Penetapan APBD
- **Endpoint**: `GET /api/v1/sipd-penetapan-apbd`
- **Akses**: `auth:sanctum`
- **Query Parameters**: `tahun`, `kode_sub_kegiatan`, `kode_rekening`, `kode_sumber_dana`, `kode_standar_harga`, `versi`, `search`, `sort_by`, `sort_direction`, `per_page`.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "id": 451,
      "kode_daerah": "3201",
      "nama_daerah": "KABUPATEN BOGOR",
      "tahun": 2026,
      "kode_sub_unit": "1.01.0.00.0.00.01.0001",
      "nama_sub_unit": "SMP NEGERI 1",
      "kode_opd": "1.01.0.00.0.00.01.0000",
      "nama_opd": "DINAS PENDIDIKAN DAN KEBUDAYAAN",
      "kode_sub_kegiatan": "1.01.02.1.01.0012",
      "nama_sub_kegiatan": "Penyediaan Gaji dan Tunjangan ASN",
      "kode_kegiatan": "1.01.02.1.01",
      "nama_kegiatan": "Administrasi Kepegawaian Perangkat Daerah",
      "kode_program": "1.01.02",
      "nama_program": "PROGRAM PENUNJANG URUSAN PEMERINTAHAN DAERAH KABUPATEN/KOTA",
      "kode_standar_harga": "SSH-2026-001",
      "nama_standar_harga": "Kertas HVS A4 80gr Sinar Dunia",
      "kode_rekening": "5.1.02.01.01.0024",
      "nama_rekening": "Belanja Alat/Bahan untuk Kegiatan Kantor- Alat Tulis Kantor",
      "kode_sumber_dana": "1.1.01.01",
      "nama_sumber_dana": "PENDAPATAN ASLI DAERAH (PAD)",
      "pagu": "25000000.00",
      "indikator_rkb": {
        "is_belanja_pengadaan": true,
        "is_rkbmd_pengadaan": false,
        "is_rkbmd_pemeliharaan_rehab": false,
        "is_rkbmd_pemeliharaan_rutin": false
      }
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 4.3 Detail Penetapan APBD
- **Endpoint**: `GET /api/v1/sipd-penetapan-apbd/{id}`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**: Mengembalikan item tunggal format `data: { ... }`.

---

## 5. Identifikasi Kebutuhan (`/identifikasi-kebutuhan`)

### 5.1 List Identifikasi Kebutuhan
- **Endpoint**: `GET /api/v1/identifikasi-kebutuhan`
- **Akses**: `auth:sanctum` (Auto-scoping ke SKPD dan Sub Kegiatan PPK)
- **Query Parameters**: `search`, `status_review`, `cara_pengadaan`, `jenis_pengadaan`, `kode_program`, `kode_kegiatan`, `kode_sub_kegiatan`, `sort_by`, `sort_direction`, `per_page`.
- **Response `200 OK`**:
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 12,
      "kode_skpd": "1.01.0.00.0.00.01.0000",
      "kode_program": "1.01.02",
      "kode_kegiatan": "1.01.02.1.01",
      "kode_sub_kegiatan": "1.01.02.1.01.0012",
      "nama_paket": "Pengadaan ATK dan Kertas Kantor",
      "jenis_pengadaan": "Barang",
      "cara_pengadaan": "Penyedia",
      "status_review": "Draft",
      "total_pagu": "25000000.00",
      "anggaran": [
        {
          "id": 1,
          "identifikasi_kebutuhan_id": 1,
          "id_sipd_penetapan": 451,
          "kode_standar_harga": "SSH-2026-001",
          "pagu": "25000000.00",
          "standar_harga": {
            "kode_standar_harga": "SSH-2026-001",
            "nama_standar_harga": "Kertas HVS A4 80gr Sinar Dunia"
          }
        }
      ],
      "pembuat": {
        "id": 12,
        "nama": "Budi Santoso, S.STP",
        "username": "ppk_umum"
      },
      "created_at": "2026-09-02T00:10:00.000000Z",
      "updated_at": "2026-09-02T00:10:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### 5.2 Tambah Identifikasi Kebutuhan
- **Endpoint**: `POST /api/v1/identifikasi-kebutuhan`
- **Akses**: `auth:sanctum`
- **Request Body**:
```json
{
  "kode_skpd": "1.01.0.00.0.00.01.0000",
  "kode_program": "1.01.02",
  "kode_kegiatan": "1.01.02.1.01",
  "kode_sub_kegiatan": "1.01.02.1.01.0012",
  "nama_paket": "Pengadaan ATK Kantor Triwulan I",
  "jenis_pengadaan": "Barang",
  "cara_pengadaan": "Penyedia",
  "status_review": "Draft",
  "anggaran": [
    {
      "id_sipd_penetapan": 451,
      "kode_standar_harga": "SSH-2026-001",
      "pagu": 15000000
    }
  ]
}
```
- **Response `201 Created`**:
```json
{
  "message": "Identifikasi kebutuhan created successfully",
  "data": {
    "id": 2,
    "user_id": 12,
    "kode_skpd": "1.01.0.00.0.00.01.0000",
    "kode_program": "1.01.02",
    "kode_kegiatan": "1.01.02.1.01",
    "kode_sub_kegiatan": "1.01.02.1.01.0012",
    "nama_paket": "Pengadaan ATK Kantor Triwulan I",
    "jenis_pengadaan": "Barang",
    "cara_pengadaan": "Penyedia",
    "status_review": "Draft",
    "total_pagu": "15000000.00",
    "anggaran": [
      {
        "id": 3,
        "identifikasi_kebutuhan_id": 2,
        "id_sipd_penetapan": 451,
        "kode_standar_harga": "SSH-2026-001",
        "pagu": "15000000.00"
      }
    ],
    "created_at": "2026-09-02T00:20:00.000000Z",
    "updated_at": "2026-09-02T00:20:00.000000Z"
  }
}
```

### 5.3 Detail Identifikasi Kebutuhan
- **Endpoint**: `GET /api/v1/identifikasi-kebutuhan/{id}`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**: Mengembalikan item tunggal format `data: { ... }`.

### 5.4 Update Identifikasi Kebutuhan
- **Endpoint**: `PUT /api/v1/identifikasi-kebutuhan/{id}`
- **Akses**: `auth:sanctum`
- **Request Body**:
```json
{
  "nama_paket": "Pengadaan ATK Kantor Triwulan I (Revisi)",
  "status_review": "Disetujui",
  "anggaran": [
    {
      "id": 3,
      "id_sipd_penetapan": 451,
      "kode_standar_harga": "SSH-2026-001",
      "pagu": 18000000
    }
  ]
}
```
- **Response `200 OK`**:
```json
{
  "message": "Identifikasi kebutuhan updated successfully",
  "data": { ... }
}
```

### 5.5 Hapus Identifikasi Kebutuhan
- **Endpoint**: `DELETE /api/v1/identifikasi-kebutuhan/{id}`
- **Akses**: `auth:sanctum`
- **Response `200 OK`**:
```json
{
  "message": "Identifikasi kebutuhan deleted successfully"
}
```