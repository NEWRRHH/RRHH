<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * File or image sent in a chat conversation (see NotificationController).
 */
class ChatAttachment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'notification_id', 'uploader_id', 'recipient_id',
        'path', 'original_name', 'mime_type', 'size',
    ];

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /** Only the two people of the conversation may see it. */
    public function isParticipant(User $user): bool
    {
        return in_array($user->id, [(int) $this->uploader_id, (int) $this->recipient_id], true);
    }

    /** Reference stored inside the message JSON. */
    public function toMessagePayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime' => $this->mime_type,
            'size' => (int) $this->size,
            'kind' => $this->isImage() ? 'image' : 'file',
        ];
    }
}
