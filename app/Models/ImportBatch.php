<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'file_name',
        'stored_path',
        'rows_scanned',
        'contacts_created',
        'contacts_updated',
        'skipped',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
