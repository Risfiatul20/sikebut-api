<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute hanya untuk peran pengguna tertentu.
 *
 * Ini adalah cerminan sisi server dari `sikebut-app/lib/permissions.ts`
 * (`hasAction`), sehingga apa yang disembunyikan menu sidebar juga tertutup
 * pada API-nya — bukan hanya di tampilan.
 *
 * Aturan:
 *  - `Admin` SELALU diizinkan (sama seperti `hasAction` di frontend).
 *  - Perbandingan peran tidak peka huruf besar/kecil dan spasi tepi.
 *
 * Pemakaian di rute:
 *   ->middleware('role:Admin')
 *   ->middleware('role:Admin,Kepala OPD,Kepala Sub Unit')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $current = strtolower(trim((string) $user->role));

        if ($current === 'admin') {
            return $next($request);
        }

        $allowed = array_filter(array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roles
        ), static fn (string $role): bool => $role !== '');

        if (! in_array($current, $allowed, true)) {
            return response()->json([
                'message' => 'Akses ditolak. Peran Anda tidak memiliki izin untuk tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
