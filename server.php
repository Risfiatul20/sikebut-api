<?php

/**
 * Router untuk PHP built-in server.
 *
 * Dipakai dengan: php -S 127.0.0.1:8000 -t public server.php
 *
 * CATATAN PENTING
 * Sebelumnya backend dijalankan lewat `php artisan serve`. Di Windows + PHP 8.3,
 * Symfony ServeCommand crash saat mem-parsing log server bawaan PHP
 * ("Failed to extract the request port ... 258 Closing" pada ServeCommand.php:446),
 * sehingga proses backend mati sendiri dan frontend menerima 502 Bad Gateway.
 *
 * Dengan menjalankan `php -S` langsung (tanpa wrapper artisan), parsing log yang
 * bermasalah itu tidak lagi dipakai -> backend stabil.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__.'/public'.$uri;

// Biarkan PHP built-in server menyajikan file statis yang benar-benar ada di /public.
if ($uri !== '/' && is_file($publicPath)) {
    return false;
}

require_once __DIR__.'/public/index.php';
