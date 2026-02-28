<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    /**
     * List all conversation partners for the authenticated user, along with
     * a snippet and unread count.
     */
    public function conversations(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $userId = $user->id;

        // query distinct other user ids from sender/receiver
        $ids = DB::table('notifications')
            ->selectRaw("CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_id", [$userId])
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->groupBy('other_id')
            ->pluck('other_id');

        $users = User::whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'photo', 'profile_photo_path']);

        // attach snippet / unread count per conversation using conversation JSON
        $conversations = $users->map(function ($u) use ($userId) {
            $convRow = Notification::conversationWith($userId, $u->id)->first();
            $lastMessage = $convRow ? $convRow->last_message : null;
            // compute unread messages count for this user using model helper
            $unread = $convRow ? $convRow->unreadCountFor($userId) : 0;
            return [
                'user' => [
                    'id' => $u->id,
                    'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'photo' => $u->photo ?? $u->profile_photo_path ?? null,
                ],
                'last_message' => $lastMessage ? $lastMessage['content'] : null,
                'last_at' => $lastMessage ? $lastMessage['created_at'] : null,
                'unread_count' => $unread,
            ];
        });

        // list of all other users (for starting new conversations)
        $allUsers = User::where('id', '<>', $userId)
            ->select('id', 'first_name', 'last_name', 'photo', 'profile_photo_path')
            ->orderBy('first_name')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'photo' => $u->photo ?? $u->profile_photo_path ?? null,
                ];
            });

        return response()->json([
            'conversations' => $conversations,
            'users' => $allUsers,
        ]);
    }

    /**
     * Return the whole conversation between authenticated user and another user.
     * Also mark incoming (receiver) messages as read.
     */
    public function conversation(Request $request, $otherId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $userId = $user->id;
        if (! User::find($otherId)) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $convRow = Notification::conversationWith($userId, $otherId)->first();
        $conversation = [];
        if ($convRow) {
            $conversation = $convRow->conversation ?: [];
            // mark individual messages as read for this user
            $convRow->markMessagesReadFor($userId);
        }

        return response()->json($conversation);
    }

    /**
     * Send a new message from the authenticated user to another.
     */
    public function send(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            $data = $request->validate([
                'receiver_id' => 'required|integer|exists:users,id',
                'content' => 'required|string|max:500',
            ]);

            $recipient = $data['receiver_id'];
            $userId = $user->id;

            // if conversation column missing, fallback to single-message rows
            if (! Schema::hasColumn('notifications', 'conversation')) {
                $notification = Notification::create([
                    'sender_id' => $userId,
                    'receiver_id' => $recipient,
                    'content' => $data['content'],
                    'read' => 0,
                ]);
                return response()->json($notification, 201);
            }

            // otherwise operate on json conversation
            $conversation = Notification::where(function ($q) use ($userId, $recipient) {
                $q->where('sender_id', $userId)->where('receiver_id', $recipient);
            })->orWhere(function ($q) use ($userId, $recipient) {
                $q->where('sender_id', $recipient)->where('receiver_id', $userId);
            })->first();

            $message = [
                'sender_id' => $userId,
                'content' => $data['content'],
                'created_at' => now(),
            ];

            if ($conversation) {
                $conv = $conversation->conversation ?: [];
                $conv[] = $message;
                $conversation->conversation = $conv;
                $conversation->read = ($conversation->receiver_id === $userId) ? 1 : 0;
                $conversation->save();
            } else {
                $conversation = Notification::create([
                    'sender_id' => $userId,
                    'receiver_id' => $recipient,
                    'read' => 0,
                    'conversation' => [$message],
                ]);
            }

            // Broadcast to the recipient of THIS message.
            // Do not use $conversation->receiver_id because that value is
            // historical (first row owner) and can point to the wrong user.
            event(new \App\Events\MessageSent($conversation, (int) $recipient));

            return response()->json($conversation, 201);
        } catch (\Exception $e) {
            // log error for debugging
            \Log::error('Notification send failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Error interno al enviar mensaje'], 500);
        }
    }

    /**
     * List all other users (used to start new conversations).
     */
    public function users(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $users = User::where('id', '!=', $user->id)
            ->get(['id', 'first_name', 'last_name', 'photo', 'profile_photo_path']);
        return response()->json($users);
    }
}
