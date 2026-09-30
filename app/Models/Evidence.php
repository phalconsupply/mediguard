<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'explanation_id', 'original_name', 'stored_path', 'sha256', 'size_bytes', 'mime', 'uploaded_by',
])]
class Evidence extends Model
{
    public function explanation(): BelongsTo
    {
        return $this->belongsTo(Explanation::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
