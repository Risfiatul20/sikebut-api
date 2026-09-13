<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi milik user yang login (terbaru dulu).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->with('kebutuhan:id,nama_paket,status_review')
            ->latest('created_at');

        if ($request->query('unread_only') === '1') {
            $query->where('is_read', false);
        }

        $limit = (int) $request->query('limit', 50);
        $data = $query->limit(min(max($limit, 1), 200))->get();

        return response()->json([
            'data' => $data->map(fn (Notification $n) => [
                'id' => $n->id,
                'tipe' => $n->tipe,
                'pesan' => $n->pesan,
                'identifikasi_kebutuhan_id' => $n->identifikasi_kebutuhan_id,
                'nama_paket' => $n->kebutuhan?->nama_paket,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * Jumlah notifikasi yang belum dibaca.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['data' => ['unread_count' => $count]]);
    }

    /**
     * Tandai satu notifikasi sebagai dibaca.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai dibaca']);
    }

    /**
     * Tandai semua notifikasi user sebagai dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca']);
    }
}
