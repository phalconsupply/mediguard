<?php

namespace App\Services\Import;

use App\Models\SourceFile;
use RuntimeException;

/** Cùng một tệp đã nạp vào đợt này rồi; nạp lại không được sinh việc trùng. */
final class DuplicateSourceFileException extends RuntimeException
{
    public function __construct(public readonly SourceFile $existing)
    {
        parent::__construct(
            "Tệp này đã được nạp vào đợt (đã lưu với tên \"{$existing->original_name}\", hash {$existing->sha256})."
        );
    }
}
