-- DROP SCHEMA dev;

CREATE SCHEMA dev AUTHORIZATION sipedal;

-- DROP SEQUENCE dev.identifikasi_kebutuhan_anggaran_id_seq;

CREATE SEQUENCE dev.identifikasi_kebutuhan_anggaran_id_seq
	INCREMENT BY 1
	MINVALUE 1
	MAXVALUE 9223372036854775807
	START 1
	CACHE 1
	NO CYCLE;
-- DROP SEQUENCE dev.identifikasi_kebutuhan_id_seq;

CREATE SEQUENCE dev.identifikasi_kebutuhan_id_seq
	INCREMENT BY 1
	MINVALUE 1
	MAXVALUE 9223372036854775807
	START 1
	CACHE 1
	NO CYCLE;
-- DROP SEQUENCE dev.sipd_penetapan_apbd_id_seq;

CREATE SEQUENCE dev.sipd_penetapan_apbd_id_seq
	INCREMENT BY 1
	MINVALUE 1
	MAXVALUE 9223372036854775807
	START 1
	CACHE 1
	NO CYCLE;
-- DROP SEQUENCE dev.sipd_penetapan_apbd_lengkap_id_seq;

CREATE SEQUENCE dev.sipd_penetapan_apbd_lengkap_id_seq
	INCREMENT BY 1
	MINVALUE 1
	MAXVALUE 9223372036854775807
	START 1
	CACHE 1
	NO CYCLE;
-- DROP SEQUENCE dev.users_id_seq;

CREATE SEQUENCE dev.users_id_seq
	INCREMENT BY 1
	MINVALUE 1
	MAXVALUE 9223372036854775807
	START 1
	CACHE 1
	NO CYCLE;-- dev.ref_akun definition

-- Drop table

-- DROP TABLE dev.ref_akun;

CREATE TABLE dev.ref_akun (
	kode_akun varchar(50) NOT NULL,
	nama_akun varchar(255) NOT NULL,
	parent_kode_akun varchar(50) NULL,
	level_akun int4 NOT NULL,
	CONSTRAINT ref_akun_pkey PRIMARY KEY (kode_akun)
);


-- dev.ref_skpd definition

-- Drop table

-- DROP TABLE dev.ref_skpd;

CREATE TABLE dev.ref_skpd (
	kode_skpd varchar(50) NOT NULL,
	nama_skpd text NOT NULL,
	parent_kode_skpd varchar(50) NULL,
	CONSTRAINT ref_skpd_pkey PRIMARY KEY (kode_skpd)
);


-- dev.ref_standar_harga definition

-- Drop table

-- DROP TABLE dev.ref_standar_harga;

CREATE TABLE dev.ref_standar_harga (
	kode_standar_harga varchar(50) NOT NULL,
	nama_standar_harga text NOT NULL,
	CONSTRAINT ref_standar_harga_pkey PRIMARY KEY (kode_standar_harga)
);


-- dev.ref_urusan definition

-- Drop table

-- DROP TABLE dev.ref_urusan;

CREATE TABLE dev.ref_urusan (
	kode_urusan varchar(20) NOT NULL,
	nama_urusan text NOT NULL,
	CONSTRAINT ref_urusan_pkey PRIMARY KEY (kode_urusan)
);


-- dev.sipd_penetapan_apbd definition

-- Drop table

-- DROP TABLE dev.sipd_penetapan_apbd;

CREATE TABLE dev.sipd_penetapan_apbd (
	id bigserial NOT NULL,
	kode_daerah varchar(10) NULL,
	nama_daerah varchar(255) NULL,
	tahun int4 NOT NULL,
	kode_sub_unit varchar(50) NOT NULL,
	kode_sub_kegiatan varchar(50) NULL,
	kode_standar_harga varchar(50) NULL,
	kode_rekening varchar(50) NULL,
	kode_sumber_dana varchar(50) NULL,
	nama_sumber_dana text NULL,
	pagu numeric(20, 2) DEFAULT 0 NULL,
	versi varchar(100) NOT NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT sipd_penetapan_apbd_pkey PRIMARY KEY (id)
);
ALTER TABLE dev.sipd_penetapan_apbd ADD CONSTRAINT sipd_penetapan_apbd_ref_skpd_fk FOREIGN KEY (kode_sub_unit) REFERENCES dev.ref_skpd(kode_skpd);
ALTER TABLE dev.sipd_penetapan_apbd ADD CONSTRAINT sipd_penetapan_apbd_ref_sub_kegiatan_fk FOREIGN KEY (kode_sub_kegiatan) REFERENCES dev.ref_sub_kegiatan(kode_sub_kegiatan);
ALTER TABLE dev.sipd_penetapan_apbd ADD CONSTRAINT sipd_penetapan_apbd_ref_standar_harga_fk FOREIGN KEY (kode_standar_harga) REFERENCES dev.ref_standar_harga(kode_standar_harga);

-- dev.sipd_penetapan_apbd_lengkap definition

-- Drop table

-- DROP TABLE dev.sipd_penetapan_apbd_lengkap;

CREATE TABLE dev.sipd_penetapan_apbd_lengkap (
	id bigserial NOT NULL,
	kode_daerah varchar(10) NULL,
	nama_daerah varchar(255) NULL,
	tahun int4 NULL,
	kode_urusan varchar(20) NULL,
	nama_urusan text NULL,
	kode_skpd varchar(50) NULL,
	nama_skpd text NULL,
	kode_sub_unit varchar(50) NULL,
	nama_sub_unit text NULL,
	kode_bidang_urusan varchar(50) NULL,
	nama_bidang_urusan text NULL,
	kode_program varchar(50) NULL,
	nama_program text NULL,
	kode_kegiatan varchar(50) NULL,
	nama_kegiatan text NULL,
	kode_sub_kegiatan varchar(50) NULL,
	nama_sub_kegiatan text NULL,
	kode_sumber_dana varchar(50) NULL,
	nama_sumber_dana text NULL,
	kode_rekening varchar(50) NULL,
	nama_rekening text NULL,
	kode_standar_harga varchar(50) NULL,
	nama_standar_harga text NULL,
	pagu numeric(20, 2) NULL,
	versi int2 NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT sipd_penetapan_apbd_lengkap_pkey PRIMARY KEY (id)
);


-- dev.akun_indikator_rkbmd definition

-- Drop table

-- DROP TABLE dev.akun_indikator_rkbmd;

CREATE TABLE dev.akun_indikator_rkbmd (
	kode_akun varchar(50) NOT NULL,
	is_belanja_pengadaan bool DEFAULT false NULL,
	is_rkbmd_pengadaan bool DEFAULT false NULL,
	is_rkbmd_pemeliharaan_rehab bool DEFAULT false NULL,
	is_rkbmd_pemeliharaan_rutin bool DEFAULT false NULL,
	CONSTRAINT akun_indikator_rkbmd_pkey PRIMARY KEY (kode_akun),
	CONSTRAINT fk_akun_indikator FOREIGN KEY (kode_akun) REFERENCES dev.ref_akun(kode_akun) ON DELETE CASCADE ON UPDATE CASCADE
);


-- dev.ref_bidang_urusan definition

-- Drop table

-- DROP TABLE dev.ref_bidang_urusan;

CREATE TABLE dev.ref_bidang_urusan (
	kode_bidang_urusan varchar(50) NOT NULL,
	kode_urusan varchar(20) NOT NULL,
	nama_bidang_urusan text NOT NULL,
	CONSTRAINT ref_bidang_urusan_pkey PRIMARY KEY (kode_bidang_urusan),
	CONSTRAINT fk_urusan FOREIGN KEY (kode_urusan) REFERENCES dev.ref_urusan(kode_urusan) ON DELETE CASCADE ON UPDATE CASCADE
);


-- dev.ref_program definition

-- Drop table

-- DROP TABLE dev.ref_program;

CREATE TABLE dev.ref_program (
	kode_program varchar(50) NOT NULL,
	kode_bidang_urusan varchar(50) NOT NULL,
	nama_program text NOT NULL,
	CONSTRAINT ref_program_pkey PRIMARY KEY (kode_program),
	CONSTRAINT fk_bidang_urusan FOREIGN KEY (kode_bidang_urusan) REFERENCES dev.ref_bidang_urusan(kode_bidang_urusan) ON DELETE CASCADE ON UPDATE CASCADE
);


-- dev.ref_kegiatan definition

-- Drop table

-- DROP TABLE dev.ref_kegiatan;

CREATE TABLE dev.ref_kegiatan (
	kode_kegiatan varchar(50) NOT NULL,
	kode_program varchar(50) NOT NULL,
	nama_kegiatan text NOT NULL,
	CONSTRAINT ref_kegiatan_pkey PRIMARY KEY (kode_kegiatan),
	CONSTRAINT fk_program FOREIGN KEY (kode_program) REFERENCES dev.ref_program(kode_program) ON DELETE CASCADE ON UPDATE CASCADE
);


-- dev.ref_sub_kegiatan definition

-- Drop table

-- DROP TABLE dev.ref_sub_kegiatan;

CREATE TABLE dev.ref_sub_kegiatan (
	kode_sub_kegiatan varchar(50) NOT NULL,
	kode_kegiatan varchar(50) NOT NULL,
	nama_sub_kegiatan text NOT NULL,
	CONSTRAINT ref_sub_kegiatan_pkey PRIMARY KEY (kode_sub_kegiatan),
	CONSTRAINT fk_kegiatan FOREIGN KEY (kode_kegiatan) REFERENCES dev.ref_kegiatan(kode_kegiatan) ON DELETE CASCADE ON UPDATE CASCADE
);


-- dev.users definition

-- Drop table

-- DROP TABLE dev.users;

CREATE TABLE dev.users (
	id bigserial NOT NULL,
	kode_skpd varchar(50) NULL,
	kode_sub_kegiatan varchar(50) NULL,
	nama varchar(255) NOT NULL,
	username varchar(255) NOT NULL,
	"password" varchar(255) NOT NULL,
	"role" varchar(50) NOT NULL,
	info jsonb NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT users_pkey PRIMARY KEY (id),
	CONSTRAINT users_username_key UNIQUE (username),
	CONSTRAINT fk_users_skpd FOREIGN KEY (kode_skpd) REFERENCES dev.ref_skpd(kode_skpd) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 1. Buat tabel pivot user_sub_kegiatan
CREATE TABLE dev.user_sub_kegiatan (
	id bigserial NOT NULL,
	user_id int8 NOT NULL,
	kode_sub_kegiatan varchar(50) NOT NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT user_sub_kegiatan_pkey PRIMARY KEY (id),
	CONSTRAINT user_sub_kegiatan_unique UNIQUE (user_id, kode_sub_kegiatan),
	CONSTRAINT fk_user_sub_kegiatan_user FOREIGN KEY (user_id) 
		REFERENCES dev.users(id) 
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_user_sub_kegiatan_sub_kegiatan FOREIGN KEY (kode_sub_kegiatan) 
		REFERENCES dev.ref_sub_kegiatan(kode_sub_kegiatan) 
		ON DELETE CASCADE ON UPDATE CASCADE
);


-- dev.identifikasi_kebutuhan definition

-- Drop table

-- DROP TABLE dev.identifikasi_kebutuhan;

CREATE TABLE dev.identifikasi_kebutuhan (
	id bigserial NOT NULL,
	user_id int8 NOT NULL,
	tahun int4 NULL,
	kode_klpd varchar(50) NULL,
	kode_skpd varchar(50) NOT NULL,
	kode_program varchar(50) NULL,
	kode_kegiatan varchar(50) NULL,
	kode_sub_kegiatan varchar(50) NULL,
	cara_pengadaan varchar(50) NOT NULL,
	jenis_pengadaan varchar(50) NULL,
	nama_paket varchar(255) NOT NULL,
	waktu_pemanfaatan_awal date NULL,
	waktu_pemanfaatan_akhir date NULL,
	waktu_pemilihan_awal date NULL,
	waktu_pemilihan_akhir date NULL,
	waktu_pelaksanaan_kontrak_awal date NULL,
	waktu_pelaksanaan_kontrak_akhir date NULL,
	waktu_pelaksanaan_pekerjaan_awal date NULL,
	waktu_pelaksanaan_pekerjaan_akhir date NULL,
	status_review varchar(50) DEFAULT 'Draft'::character varying NULL,
	form_data jsonb NOT NULL,
	catatan_reviewer_detail jsonb NULL,
	catatan_reviewer text NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	updated_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT identifikasi_kebutuhan_pkey PRIMARY KEY (id),
	CONSTRAINT fk_identifikasi_user FOREIGN KEY (user_id) REFERENCES dev.users(id) ON DELETE CASCADE,
	CONSTRAINT fk_ref_kegiatan FOREIGN KEY (kode_kegiatan) REFERENCES dev.ref_kegiatan(kode_kegiatan),
	CONSTRAINT fk_ref_program FOREIGN KEY (kode_program) REFERENCES dev.ref_program(kode_program),
	CONSTRAINT fk_ref_skpd FOREIGN KEY (kode_skpd) REFERENCES dev.ref_skpd(kode_skpd),
	CONSTRAINT fk_ref_sub_kegiatan FOREIGN KEY (kode_sub_kegiatan) REFERENCES dev.ref_sub_kegiatan(kode_sub_kegiatan)
);


-- dev.identifikasi_kebutuhan_anggaran definition

-- Drop table

-- DROP TABLE dev.identifikasi_kebutuhan_anggaran;

CREATE TABLE dev.identifikasi_kebutuhan_anggaran (
	id bigserial NOT NULL,
	identifikasi_kebutuhan_id int8 NOT NULL,
	id_sipd_penetapan int8 NOT NULL,
	kode_standar_harga varchar(50) NULL,
	pagu numeric(20, 2) DEFAULT 0 NOT NULL,
	perubahan_standar jsonb NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT identifikasi_kebutuhan_anggaran_pkey PRIMARY KEY (id),
	CONSTRAINT fk_pivot_identifikasi FOREIGN KEY (identifikasi_kebutuhan_id) REFERENCES dev.identifikasi_kebutuhan(id) ON DELETE CASCADE,
	CONSTRAINT fk_ref_standar_harga FOREIGN KEY (kode_standar_harga) REFERENCES dev.ref_standar_harga(kode_standar_harga)
);

-- dev.identifikasi_kebutuhan_rkbmd definition

-- Drop table

-- DROP TABLE dev.identifikasi_kebutuhan_rkbmd;

CREATE TABLE dev.identifikasi_kebutuhan_rkbmd (
	id bigserial NOT NULL,
	identifikasi_kebutuhan_id int8 NOT NULL,
	kode_standar varchar(50) NULL,
	kode_rekening varchar(50) NULL,
	id_pengadaan int8 NULL,
	jenis_rkbmd varchar(20) NULL,
	jumlah int4 NULL,
	created_at timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
	CONSTRAINT identifikasi_kebutuhan_rkbmd_pkey PRIMARY KEY (id),
	CONSTRAINT identifikasi_kebutuhan_rkbmd_identifikasi_kebutuhan_id_fkey FOREIGN KEY (identifikasi_kebutuhan_id) REFERENCES dev.identifikasi_kebutuhan(id) ON DELETE CASCADE
);

CREATE INDEX idx_ikr_identifikasi ON dev.identifikasi_kebutuhan_rkbmd USING btree (identifikasi_kebutuhan_id);

-- dev.ref_sipd_view source

CREATE OR REPLACE VIEW dev.ref_sipd_view
AS SELECT spa.kode_daerah,
    spa.nama_daerah,
    spa.tahun,
    COALESCE(parent_skpd.kode_skpd, sub_unit.kode_skpd) AS kode_skpd,
    COALESCE(parent_skpd.nama_skpd, sub_unit.nama_skpd) AS nama_skpd,
    sub_unit.kode_skpd AS kode_sub_unit,
    sub_unit.nama_skpd AS nama_sub_unit,
    urus.kode_urusan,
    urus.nama_urusan,
    bidur.kode_bidang_urusan,
    bidur.nama_bidang_urusan,
    prog.kode_program,
    prog.nama_program,
    keg.kode_kegiatan,
    keg.nama_kegiatan,
    sub_keg.kode_sub_kegiatan,
    sub_keg.nama_sub_kegiatan,
    spa.kode_sumber_dana,
    spa.nama_sumber_dana,
    spa.kode_rekening,
    akun.nama_akun AS nama_rekening,
    spa.kode_standar_harga,
    standar.nama_standar_harga,
    spa.pagu,
    ind.is_belanja_pengadaan,
    ind.is_rkbmd_pengadaan,
    ind.is_rkbmd_pemeliharaan_rehab,
    ind.is_rkbmd_pemeliharaan_rutin,
    spa.versi
   FROM dev.sipd_penetapan_apbd spa
     JOIN dev.ref_skpd sub_unit ON spa.kode_sub_unit::text = sub_unit.kode_skpd::text
     LEFT JOIN dev.ref_skpd parent_skpd ON sub_unit.parent_kode_skpd::text = parent_skpd.kode_skpd::text
     LEFT JOIN dev.ref_sub_kegiatan sub_keg ON spa.kode_sub_kegiatan::text = sub_keg.kode_sub_kegiatan::text
     LEFT JOIN dev.ref_kegiatan keg ON sub_keg.kode_kegiatan::text = keg.kode_kegiatan::text
     LEFT JOIN dev.ref_program prog ON keg.kode_program::text = prog.kode_program::text
     LEFT JOIN dev.ref_bidang_urusan bidur ON prog.kode_bidang_urusan::text = bidur.kode_bidang_urusan::text
     LEFT JOIN dev.ref_urusan urus ON bidur.kode_urusan::text = urus.kode_urusan::text
     LEFT JOIN dev.ref_standar_harga standar ON standar.kode_standar_harga::text = spa.kode_standar_harga::text
     LEFT JOIN dev.ref_akun akun ON spa.kode_rekening::text = akun.kode_akun::text
     LEFT JOIN dev.akun_indikator_rkbmd ind ON spa.kode_rekening::text = ind.kode_akun::text;
-- ============================================================
-- WA GATEWAY (notifikasi WhatsApp)
-- ============================================================
CREATE TABLE IF NOT EXISTS dev.wa_devices (
    id bigserial NOT NULL,
    device_id varchar(50) NOT NULL,
    nama varchar(100) NULL,
    nomor varchar(20) NULL,
    status varchar(20) NOT NULL DEFAULT 'disconnected',
    is_active boolean NOT NULL DEFAULT true,
    priority int NOT NULL DEFAULT 0,
    last_heartbeat timestamp NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
    CONSTRAINT wa_devices_pkey PRIMARY KEY (id),
    CONSTRAINT wa_devices_device_id_key UNIQUE (device_id)
);

CREATE TABLE IF NOT EXISTS dev.wa_messages (
    id bigserial NOT NULL,
    user_id bigint NULL,
    nomor_tujuan varchar(20) NOT NULL,
    pesan text NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending',
    device_id varchar(50) NULL,
    error text NULL,
    identifikasi_kebutuhan_id bigint NULL,
    message_id varchar(64) NULL,
    sent_at timestamp NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
    CONSTRAINT wa_messages_pkey PRIMARY KEY (id),
    CONSTRAINT fk_wa_messages_user FOREIGN KEY (user_id) REFERENCES dev.users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wa_messages_kebutuhan FOREIGN KEY (identifikasi_kebutuhan_id) REFERENCES dev.identifikasi_kebutuhan(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_wa_messages_status ON dev.wa_messages(status);
CREATE INDEX IF NOT EXISTS idx_wa_messages_created ON dev.wa_messages(created_at DESC);

-- ============================================================================
-- TABEL MASTER KONDISI RKBMD (ditambahkan 2026-09-14)
-- Sumber data: rekap RKBMD Biro PBJ (import tabel_kebutuhan).
-- Dipakai oleh RkbmdPemeliharaanController untuk mengisi OTOMATIS kolom
-- kondisi barang pada form Pemeliharaan:
--   Baik          -> kondisidesember_b
--   Rusak Ringan  -> kondisidesember_rr
--   Rusak Berat   -> kondisidesember_rb
-- Catatan: sebelumnya tabel ini hanya ada di database live (1.865 baris)
-- dan belum tercatat di berkas skema ini, sehingga pemasangan ulang akan
-- gagal pada endpoint kondisi pemeliharaan. Jangan dihapus.
-- ============================================================================
CREATE TABLE IF NOT EXISTS dev.rkbmd_kebutuhan (
    id_kebutuhan bigint NOT NULL,
    kode_fikasi character varying(50),
    nama_barang text,
    kondisidesember_b integer,
    kondisidesember_rr integer,
    kondisidesember_rb integer,
    pengadaan_tahun_berjalan integer,
    id_user bigint,
    log timestamp without time zone,
    id_status integer,
    satuan character varying(50),
    id_instansi bigint,
    kebutuhanideal_jumlah integer,
    kebutuhanideal_penjelasan text,
    status_barang text,
    periode integer,
    modif_by bigint,
    modif_date timestamp without time zone,
    rencanapenghapusan_b integer,
    rencanapenghapusan_rr integer,
    rencanapenghapusan_rb integer,
    rencanapemindahtanganan_b integer,
    rencanapemindahtanganan_rr integer,
    rencanapemindahtanganan_rb integer,
    rencanapemanfaatan_b integer,
    rencanapemanfaatan_rr integer,
    rencanapemanfaatan_rb integer,
    tdesember integer,
    tpenghapusan integer,
    tpemanfaatan integer,
    tpemindahtanganan integer,
    rencanapemeliharaan_b integer,
    rencanapemeliharaan_rr integer,
    rencanapemeliharaan_rb integer,
    td integer,
    kebutuhan_maksimum integer,
    jumpengadaan integer,
    jumpemeliharaan integer,
    nm_status character varying(100),
    nm_instansi character varying(255),
    catatan_notulen text,
    status_barang_ds integer,
    status_barang_pp integer,
    usulan character varying(50),
    CONSTRAINT rkbmd_kebutuhan_pkey PRIMARY KEY (id_kebutuhan)
);

-- ============================================================================
-- TABEL PELENGKAP (ditambahkan 2026-09-14)
-- Sebelumnya kelima tabel ini HANYA ada di database live, tidak tercatat di
-- berkas skema — akibatnya pemasangan ulang akan gagal pada: riwayat/timeline
-- paket, notifikasi, status import, dan daftar RKBMD pengadaan/pemeliharaan.
-- Jangan dihapus.
-- ============================================================================
CREATE SEQUENCE IF NOT EXISTS dev.identifikasi_kebutuhan_riwayat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE SEQUENCE IF NOT EXISTS dev.notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE IF NOT EXISTS dev.identifikasi_kebutuhan_riwayat (
    id bigint NOT NULL DEFAULT nextval('dev.identifikasi_kebutuhan_riwayat_id_seq'::regclass),
    identifikasi_kebutuhan_id bigint NOT NULL,
    user_id bigint NOT NULL,
    status_dari character varying(50),
    status_ke character varying(50) NOT NULL,
    catatan text,
    created_at timestamp(0) without time zone DEFAULT now() NOT NULL,
    CONSTRAINT identifikasi_kebutuhan_riwayat_pkey PRIMARY KEY (id),
    CONSTRAINT identifikasi_kebutuhan_riwayat_identifikasi_kebutuhan_id_fkey FOREIGN KEY (identifikasi_kebutuhan_id) REFERENCES dev.identifikasi_kebutuhan(id) ON DELETE CASCADE,
    CONSTRAINT identifikasi_kebutuhan_riwayat_user_id_fkey FOREIGN KEY (user_id) REFERENCES dev.users(id) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dev.import_statuses (
    id uuid NOT NULL,
    user_id bigint,
    file_name character varying(255),
    status character varying(50),
    total_rows integer DEFAULT 0,
    processed_rows integer DEFAULT 0,
    error_message text,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    CONSTRAINT import_statuses_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS dev.notifications (
    id bigint NOT NULL DEFAULT nextval('dev.notifications_id_seq'::regclass),
    user_id bigint NOT NULL,
    tipe character varying(50) NOT NULL,
    pesan text NOT NULL,
    identifikasi_kebutuhan_id bigint,
    is_read boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone DEFAULT now() NOT NULL,
    CONSTRAINT notifications_pkey PRIMARY KEY (id),
    CONSTRAINT notifications_identifikasi_kebutuhan_id_fkey FOREIGN KEY (identifikasi_kebutuhan_id) REFERENCES dev.identifikasi_kebutuhan(id) ON DELETE CASCADE,
    CONSTRAINT notifications_user_id_fkey FOREIGN KEY (user_id) REFERENCES dev.users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dev.rkbmd_pengadaan (
    id_pengadaan bigint NOT NULL,
    id_instansi bigint,
    id_renja bigint,
    kode_fikasi character varying(50),
    nama_barang text,
    jumlah_barang integer,
    satuan character varying(50),
    jumlah_maksimum integer,
    keterangan text,
    id_status integer,
    periode integer,
    nm_status character varying(50),
    cara_pemenuhan character varying(100),
    target character varying(50),
    nama_giat_nama_giat text,
    nama_sub_giat_nama_sub_giat text,
    id_kebutuhan bigint,
    id_sub bigint,
    nomekelatur character varying(50),
    outputbaru character varying(50),
    id_status_kebutuhan integer,
    catatan_notulen text,
    kode_program character varying(50),
    kode_giat character varying(50),
    kode_sub_giat character varying(50),
    status_barang_ds integer,
    status_barang_pp integer,
    nama_program text,
    nama_skpd text,
    nama_sub_skpd text,
    kode_skpd character varying(50),
    kode_sub_skpd character varying(50),
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT rkbmd_pengadaan_pkey PRIMARY KEY (id_pengadaan)
);

CREATE TABLE IF NOT EXISTS dev.rkbmd_pemeliharaan (
    id_pemeliharaan bigint NOT NULL,
    id_instansi bigint,
    id_renja bigint,
    kode_fikasi character varying(50),
    nama_barang text,
    jumlah_barang integer,
    status_barang integer,
    satuan character varying(50),
    kondisi_b integer,
    kondisi_rr integer,
    kondisi_rb integer,
    nama_pemeliharaan text,
    jumlah_pemeliharaan integer,
    satuan_pemeliharaan character varying(50),
    keterangan text,
    id_status integer,
    periode integer,
    nm_status character varying(50),
    target character varying(50),
    nama_giat_nama_giat text,
    id_kebutuhan bigint,
    id_status_kebutuhan integer,
    catatan_notulen text,
    kode_program character varying(50),
    kode_kegiatan character varying(50),
    kode_sub_kegiatan character varying(50),
    id_sub_update bigint,
    nomekelatur_update character varying(50),
    nama_sub_giat_nama_sub_giat text,
    status_barang_ds integer,
    status_barang_pp integer,
    nama_program text,
    nama_skpd text,
    kode_skpd character varying(50),
    nama_sub_skpd text,
    kode_sub_skpd character varying(50),
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT rkbmd_pemeliharaan_pkey PRIMARY KEY (id_pemeliharaan)
);

CREATE INDEX IF NOT EXISTS idx_ik_riwayat_paket ON dev.identifikasi_kebutuhan_riwayat USING btree (identifikasi_kebutuhan_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON dev.notifications USING btree (user_id, is_read, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_rkbmd_id_instansi ON dev.rkbmd_pengadaan USING btree (id_instansi);
CREATE INDEX IF NOT EXISTS idx_rkbmd_kode_skpd ON dev.rkbmd_pengadaan USING btree (kode_skpd);
CREATE INDEX IF NOT EXISTS idx_rkbmd_periode ON dev.rkbmd_pengadaan USING btree (periode);
CREATE INDEX IF NOT EXISTS idx_pemeliharaan_id_instansi ON dev.rkbmd_pemeliharaan USING btree (id_instansi);
CREATE INDEX IF NOT EXISTS idx_pemeliharaan_kode_skpd ON dev.rkbmd_pemeliharaan USING btree (kode_skpd);
CREATE INDEX IF NOT EXISTS idx_pemeliharaan_periode ON dev.rkbmd_pemeliharaan USING btree (periode);
