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
	CONSTRAINT fk_sub_kegiatan FOREIGN KEY (kode_sub_kegiatan) REFERENCES dev.ref_sub_kegiatan(kode_sub_kegiatan),
	CONSTRAINT fk_users_skpd FOREIGN KEY (kode_skpd) REFERENCES dev.ref_skpd(kode_skpd) ON DELETE CASCADE ON UPDATE CASCADE
);


-- dev.identifikasi_kebutuhan definition

-- Drop table

-- DROP TABLE dev.identifikasi_kebutuhan;

CREATE TABLE dev.identifikasi_kebutuhan (
	id bigserial NOT NULL,
	user_id int8 NOT NULL,
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