<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_batch_id',
        'import_no',
        'name',
        'email',
        'phone',
        'province',
        'city',
        'education_level',
        'school',
        'field',
        'participant_no',
        'participant_card_link',
        'telegram',
        'source_sheet',
        'source_year',
        'segment',
        'status',
        'email_opt_out',
        'is_duplicate',
        'meta',
    ];

    protected $casts = [
        'email_opt_out' => 'boolean',
        'is_duplicate' => 'boolean',
        'meta' => 'array',
    ];

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
