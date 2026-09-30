<?php

namespace App\Jobs;

use App\Models\SourceFile;
use App\Services\Assignment\IssueCaseBuilder;
use App\Services\Import\ResponseFileImporter;
use App\Services\Import\SourceFileStore;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Nạp một tệp phản hồi BHYT rồi sinh vụ việc giải trình.
 *
 * Job theo tệp, không theo dòng: một tệp 80 MB là một job có checkpoint trong DB,
 * chạy lại được mà không sinh dữ liệu trùng nhờ khóa (trang tính, số dòng).
 */
class IngestResponseFile implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /** @param array{sheet_index?: int, header_row_number?: int} $overrides */
    public function __construct(
        public readonly int $sourceFileId,
        public readonly ?int $actorId = null,
        public readonly array $overrides = [],
    ) {}

    public function handle(
        SourceFileStore $store,
        ResponseFileImporter $importer,
        IssueCaseBuilder $builder,
    ): void {
        $file = SourceFile::with('batch')->findOrFail($this->sourceFileId);

        if ($file->status === 'ready') {
            return; // đã nạp xong ở lần chạy trước
        }

        $file->update(['status' => 'reading', 'failure_reason' => null]);

        try {
            $stats = $importer->import($file, $store->absolutePath($file), $this->overrides);
            $assignment = $builder->build($file, $this->actorId);

            Audit::record($this->actorId, 'import.file_ingested', $file, [
                'rows' => $stats['rows'],
                'cost_rows' => $stats['cost_rows'],
                'summary_rows' => $stats['summary_rows'],
                'assignment' => $assignment,
            ]);
        } catch (\Throwable $e) {
            $file->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            Audit::record($this->actorId, 'import.file_failed', $file, ['error' => $e->getMessage()]);

            throw $e;
        }
    }
}
