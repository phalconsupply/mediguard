<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'source_file_id', 'sheet_index', 'sheet_name', 'header_row_number',
    'column_count', 'row_count', 'is_data_sheet', 'column_fingerprint',
])]
class SourceSheet extends Model
{
    protected function casts(): array
    {
        return ['is_data_sheet' => 'boolean'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class, 'source_file_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(SourceColumn::class)->orderBy('position');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SourceRow::class);
    }
}
