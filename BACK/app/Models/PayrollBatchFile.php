<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payslip of a batch: who it goes to (matched by DNI in the file name or
 * chosen by RRHH) and whether it was already sent.
 */
class PayrollBatchFile extends Model
{
    use BelongsToCompany;

    protected $fillable = ['batch_id', 'user_id', 'original_name', 'path', 'size', 'match', 'document_id', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
