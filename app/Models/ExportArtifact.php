<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một phiên bản tệp kết quả trả lại BHYT, kèm hash và kết quả kiểm soát chất lượng. */
#[Fillable([
    'import_batch_id', 'source_file_id', 'version', 'stored_path',
    'sha256', 'size_bytes', 'verification', 'released_by',
])]
class ExportArtifact extends Model
{
    protected function casts(): array
    {
        return ['verification' => 'array'];
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(SourceFile::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
