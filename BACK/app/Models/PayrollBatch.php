<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One month of payslips uploaded by RRHH (Nóminas module): draft until sent.
 */
class PayrollBatch extends Model
{
    use BelongsToCompany;

    public const DRAFT = 'draft';
    public const SENT = 'sent';

    protected $fillable = ['period', 'status', 'created_by', 'sent_by', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function files(): HasMany
    {
        return $this->hasMany(PayrollBatchFile::class, 'batch_id');
    }
}
