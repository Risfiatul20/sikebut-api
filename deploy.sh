#!/bin/bash
set -e

# ==============================================================================
# Script Deployment Otomatis SIKEBUT API ke Docker Production
# ==============================================================================

echo "=========================================="
echo "🚀 Memulai proses deployment SIKEBUT API..."
echo "=========================================="

# 1. Pull perubahan kode terbaru dari Git branch saat ini
echo "📥 Menarik kode terbaru dari Git..."
git pull

# 2. Build ulang image container aplikasi Laravel
echo "🔨 Membangun image Docker (sikebut-api)..."
docker compose build sikebut-api

# 3. Jalankan container secara background (rolling update service)
echo "🔄 Merestart service sikebut-api, queue, scheduler..."
docker compose up -d sikebut-api queue scheduler nginx

# 4. Tunggu sesaat hingga container PHP-FPM sehat dan siap menerima perintah
echo "⏳ Memeriksa kesiapan container aplikasi..."
sleep 3

# 5. Jalankan migrasi database jika ada perubahan struktur tabel
# echo "🗄️ Menjalankan migrasi database..."
# docker exec -t sikebut-api-app php artisan migrate --force

# 6. Optimasi cache Laravel untuk production
echo "⚡ Mengoptimalkan cache Laravel..."
docker exec -t sikebut-api-app php artisan config:cache
docker exec -t sikebut-api-app php artisan route:cache
docker exec -t sikebut-api-app php artisan view:cache

# 7. Restart worker queue agar memuat kode PHP terbaru
echo "📬 Merestart worker antrean (queue)..."
docker exec -t sikebut-api-app php artisan queue:restart

# 8. Bersihkan image Docker lama yang sudah tidak terpakai
echo "🧹 Membersihkan image Docker yang tidak terpakai..."
docker image prune -f

echo "=========================================="
echo "✅ Deployment selesai dan berhasil dijalankan!"
echo "=========================================="
