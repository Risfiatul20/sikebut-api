import argparse
import pandas as pd
import numpy as np
import psycopg2
from psycopg2 import extras

# --- KONFIGURASI DATABASE & FILE ---
DB_CONFIG = {
    'dbname': 'sikebut',
    'user': 'sipedal',
    'password': '@Password2026',
    'host': '103.22.250.210',
    'port': '54320',
    'options': '-c search_path=dev'  # Memaksa koneksi masuk ke schema 'dev'
}

FILE_PATH = '13_Provinsi Sumatera Barat_Rekap_Ver4_Penetapan APBD 2026_Terkunci.xlsx'

def clean_string(val):
    """Membersihkan nilai sel menjadi string murni atau None"""
    # Tangkap nilai kosong bawaan Python/Pandas
    if pd.isna(val) or val is None:
        return None
    
    # Ubah ke string lalu hapus spasi awal & akhir
    val_str = str(val).strip()
    
    # Tangkap string gadungan hasil konversi yang menyerupai kosong
    if val_str.lower() in ['nan', 'none', 'null', '']:
        return None
        
    # Jika aslinya float desimal tapi sebenarnya bilangan bulat (misal 1.0 jadi '1')
    if isinstance(val, float) and val.is_integer():
        return str(int(val)).strip()
        
    return val_str

def run_import(tahun_param, versi_param):
    print(f"Membaca file {FILE_PATH}...")
    try:
        df = pd.read_excel(FILE_PATH, sheet_name=0)
    except Exception as e:
        print(f"Gagal membaca file Excel: {e}")
        return

    print("Membersihkan dan menormalisasi data...")
    
    # 1. Bersihkan semua kolom kecuali PAGU dan TAHUN
    text_columns = [col for col in df.columns if col not in ['PAGU', 'TAHUN', 'NO']]
    for col in text_columns:
        df[col] = df[col].apply(clean_string)

    # 2. Tangani khusus kolom numerik agar tidak error saat dihitung
    if 'TAHUN' in df.columns:
        df['TAHUN'] = pd.to_numeric(df['TAHUN'], errors='coerce').fillna(0).astype(int)
    
    if 'PAGU' in df.columns:
        df['PAGU'] = pd.to_numeric(df['PAGU'], errors='coerce').fillna(0)

    # 3. SAPU BERSIH: Paksa semua data yang terdeteksi 'Not a Number' (NaN/NaT) 
    # menjadi tipe None bawaan Python. Ini KUNCI UTAMA agar masuk ke DB sebagai NULL.
    df = df.astype(object).where(pd.notna(df), None)

    try:
        conn = psycopg2.connect(**DB_CONFIG)
        conn.autocommit = False
        cursor = conn.cursor()

        print("✅ KONEKSI SUKSES: Terhubung ke schema 'dev'.")
        print("Memasukkan Data Master Referensi...")

        # 1. Master Urusan
        urusan = df[['KODE URUSAN', 'NAMA URUSAN']].drop_duplicates().dropna(subset=['KODE URUSAN'])
        if not urusan.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_urusan (kode_urusan, nama_urusan) VALUES %s
                ON CONFLICT (kode_urusan) DO UPDATE SET nama_urusan = EXCLUDED.nama_urusan
            """, urusan.values.tolist())

        # 2. Master Bidang Urusan
        bidang = df[['KODE BIDANG URUSAN', 'KODE URUSAN', 'NAMA BIDANG URUSAN']].drop_duplicates().dropna(subset=['KODE BIDANG URUSAN'])
        if not bidang.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_bidang_urusan (kode_bidang_urusan, kode_urusan, nama_bidang_urusan) VALUES %s
                ON CONFLICT (kode_bidang_urusan) DO UPDATE SET nama_bidang_urusan = EXCLUDED.nama_bidang_urusan
            """, bidang.values.tolist())

        # 3. Master Program
        program = df[['KODE PROGRAM', 'KODE BIDANG URUSAN', 'NAMA PROGRAM']].drop_duplicates().dropna(subset=['KODE PROGRAM'])
        if not program.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_program (kode_program, kode_bidang_urusan, nama_program) VALUES %s
                ON CONFLICT (kode_program) DO UPDATE SET nama_program = EXCLUDED.nama_program
            """, program.values.tolist())

        # 4. Master Kegiatan
        kegiatan = df[['KODE KEGIATAN', 'KODE PROGRAM', 'NAMA KEGIATAN']].drop_duplicates().dropna(subset=['KODE KEGIATAN'])
        if not kegiatan.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_kegiatan (kode_kegiatan, kode_program, nama_kegiatan) VALUES %s
                ON CONFLICT (kode_kegiatan) DO UPDATE SET nama_kegiatan = EXCLUDED.nama_kegiatan
            """, kegiatan.values.tolist())

        # 5. Master Sub Kegiatan
        sub_keg = df[['KODE SUB KEGIATAN', 'KODE KEGIATAN', 'NAMA SUB KEGIATAN']].drop_duplicates().dropna(subset=['KODE SUB KEGIATAN'])
        if not sub_keg.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_sub_kegiatan (kode_sub_kegiatan, kode_kegiatan, nama_sub_kegiatan) VALUES %s
                ON CONFLICT (kode_sub_kegiatan) DO UPDATE SET nama_sub_kegiatan = EXCLUDED.nama_sub_kegiatan
            """, sub_keg.values.tolist())

        # 6. Master SKPD (Induk)
        skpd = df[['KODE SKPD', 'NAMA SKPD']].drop_duplicates().dropna(subset=['KODE SKPD']).copy()
        skpd['parent'] = None
        # Baris penentuan level dihapus
        
        if not skpd.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_skpd (kode_skpd, nama_skpd, parent_kode_skpd) VALUES %s
                ON CONFLICT (kode_skpd) DO UPDATE SET nama_skpd = EXCLUDED.nama_skpd
            """, skpd.values.tolist())

        # 7. Master SKPD (Sub Unit)
        sub_unit = df[['KODE SUB UNIT', 'NAMA SUB UNIT', 'KODE SKPD']].drop_duplicates().dropna(subset=['KODE SUB UNIT']).copy()
        # Baris penentuan level dihapus
        
        # Susun ulang agar sesuai parameter query: kode, nama, parent
        sub_unit = sub_unit[['KODE SUB UNIT', 'NAMA SUB UNIT', 'KODE SKPD']] 
        if not sub_unit.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_skpd (kode_skpd, nama_skpd, parent_kode_skpd) VALUES %s
                ON CONFLICT (kode_skpd) DO UPDATE SET nama_skpd = EXCLUDED.nama_skpd
            """, sub_unit.values.tolist())

        # 8. Master Standar Harga
        std_harga = df[['KODE STANDAR HARGA', 'NAMA STANDAR HARGA']].drop_duplicates().dropna(subset=['KODE STANDAR HARGA'])
        if not std_harga.empty:
            extras.execute_values(cursor, """
                INSERT INTO ref_standar_harga (kode_standar_harga, nama_standar_harga) VALUES %s
                ON CONFLICT (kode_standar_harga) DO UPDATE SET nama_standar_harga = EXCLUDED.nama_standar_harga
            """, std_harga.values.tolist())

        # --- PROSES DELETE DATA LAMA SEBELUM INSERT ---
        print(f"Menghapus data lama di sipd_penetapan_apbd (Tahun: {tahun_param}, Versi: '{versi_param}')...")
        cursor.execute("DELETE FROM sipd_penetapan_apbd WHERE tahun = %s AND versi = %s", (tahun_param, versi_param))
        print("Data lama berhasil dihapus.")

        print("Memasukkan Data Transaksi Penetapan APBD...")
        
        # 9. Transaksi Penetapan APBD
        apbd_data = df[[
            'KODE DAERAH', 'NAMA DAERAH', 'TAHUN', 'KODE SUB UNIT', 'KODE SUB KEGIATAN', 
            'KODE STANDAR HARGA', 'KODE REKENING', 'KODE SUMBER DANA', 'NAMA SUMBER DANA', 'PAGU'
        ]].copy()
        
        # Tambahkan kolom versi ke urutan terakhir
        apbd_data['versi'] = versi_param

        # --- JURUS PAMUNGKAS: Bersihkan di level Python List ---
        raw_list = apbd_data.values.tolist()
        final_insert_list = []
        
        for row in raw_list:
            clean_row = []
            for val in row:
                # Cek secara paksa apakah itu string 'NaN', kosong, atau NaN dari Pandas
                if pd.isna(val) or str(val).strip().lower() in ['nan', 'none', 'null', '']:
                    clean_row.append(None) # Ini akan diterjemahkan menjadi NULL sejati di PostgreSQL
                else:
                    clean_row.append(val)
            final_insert_list.append(clean_row)
        # -------------------------------------------------------

        insert_query = """
            INSERT INTO sipd_penetapan_apbd (
                kode_daerah, nama_daerah, tahun, kode_sub_unit, kode_sub_kegiatan, 
                kode_standar_harga, kode_rekening, kode_sumber_dana, nama_sumber_dana, pagu, versi
            ) VALUES %s
        """
        
        # Eksekusi dengan final_insert_list yang sudah 100% bersih
        extras.execute_values(cursor, insert_query, final_insert_list)

        # Commit transaksi jika seluruh proses sukses
        conn.commit()
        print(f"🎉 BERHASIL! Semua data versi '{versi_param}' telah di-import.")

    except Exception as e:
        if 'conn' in locals() and conn:
            conn.rollback()
        print(f"❌ GAGAL MENGIMPOR DATA. Transaksi dibatalkan. \nDetail Error:\n{e}")
    finally:
        if 'conn' in locals() and conn:
            cursor.close()
            conn.close()

if __name__ == '__main__':
    # Menyiapkan parser untuk membaca parameter dari terminal
    parser = argparse.ArgumentParser(description="Script Import Data APBD SIPD")
    parser.add_argument('--tahun', type=int, required=True, help="Tahun anggaran (contoh: 2026)")
    parser.add_argument('--versi', type=str, required=True, help="Versi data (contoh: Ver4_Terkunci)")
    
    args = parser.parse_args()
    
    # Menjalankan fungsi utama dengan parameter yang ditangkap
    run_import(args.tahun, args.versi)