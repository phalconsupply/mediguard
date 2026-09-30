<?php

namespace App\Services\Export;

use RuntimeException;

/** Việc xuất trả bị chặn vì kiểm soát chất lượng chưa đạt. */
final class ExportBlockedException extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('Không thể xuất tệp: '.implode(' ', $problems));
    }
}
