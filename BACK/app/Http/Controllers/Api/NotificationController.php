<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use App\Models\ChatAttachment;
use App\Services\PlanLimits;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    /** Chat attachments: 10 MB, images / PDF / Word / Excel / text. */
    public const ATTACHMENT_MAX_KB = 10240;

    public const ATTACHMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];

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
        $ids = Notification::table()
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
                // attachment-only messages show the file in the preview
                'last_message' => $lastMessage
                    ? (($lastMessage['content'] ?? '') !== ''
                        ? $lastMessage['content']
                        : (isset($lastMessage['attachment'])
                            ? (($lastMessage['attachment']['kind'] ?? '') === 'image' ? '📷 Imagen' : '📎 ' . ($lastMessage['attachment']['name'] ?? 'Archivo'))
                            : ''))
                    : null,
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
            $changed = $convRow->markMessagesReadFor($userId);
            if ($changed) {
                // Notify the other participant that their messages were read.
                event(new \App\Events\MessageRead($convRow, (int) $otherId, (int) $userId));
            }
        }

        return response()->json($conversation);
    }

    /**
     * Send a new message from the authenticated user to another.
     */
    /**
     * Download / show a chat attachment (only the two participants).
     * ?inline=1 serves images inline so the chat can preview them.
     */
    public function attachment(Request $request, int $id)
    {
        $user = $request->user();
        $attachment = ChatAttachment::find($id);
        if (! $attachment || ! $attachment->isParticipant($user)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }
        if (! Storage::disk('local')->exists($attachment->path)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        if ($request->boolean('inline') && $attachment->isImage()) {
            return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function send(Request $request, PlanLimits $limits)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        // validate outside the try/catch below: a ValidationException must
        // reach the client as 422, not be swallowed into a 500.
        $data = $request->validate([
            'receiver_id' => ['required', 'integer', TenantRule::exists('users', 'id', true)],
            // text, a file, or both
            'content' => 'nullable|string|max:500|required_without:file',
            'file' => 'nullable|file|max:' . self::ATTACHMENT_MAX_KB . '|mimes:' . implode(',', self::ATTACHMENT_EXTENSIONS),
        ], [
            'content.required_without' => 'Escribe un mensaje o adjunta un archivo.',
            'file.max' => 'El archivo no puede superar los 10 MB.',
            'file.mimes' => 'Tipo de archivo no permitido. Se aceptan imágenes, PDF, Word, Excel y texto.',
        ]);
        $data['content'] = trim((string) ($data['content'] ?? ''));

        $file = $request->file('file');
        if ($file && $user->company && ($limitError = $limits->canStore($user->company, (int) $file->getSize()))) {
            return response()->json(['message' => $limitError], 422);
        }

        $attachment = null;
        try {
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

            if ($file) {
                $stored = $file->storeAs(
                    "chat/{$user->company_id}/{$userId}",
                    Str::random(32) . '.' . strtolower($file->getClientOriginalExtension()),
                    'local',
                );
                $attachment = ChatAttachment::create([
                    'notification_id' => $conversation?->id,
                    'uploader_id' => $userId,
                    'recipient_id' => (int) $recipient,
                    'path' => $stored,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
                $message['attachment'] = $attachment->toMessagePayload();
            }

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
            if ($attachment && ! $attachment->notification_id) {
                $attachment->update(['notification_id' => $conversation->id]);
            }

            // Broadcast to the recipient of THIS message.
            // Do not use $conversation->receiver_id because that value is
            // historical (first row owner) and can point to the wrong user.
            event(new \App\Events\MessageSent($conversation, (int) $recipient));

            return response()->json($conversation, 201);
        } catch (\Exception $e) {
            // don't keep a file that no message points to
            if ($attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            }
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
