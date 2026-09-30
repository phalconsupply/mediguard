<?php

namespace App\Jobs;

use App\Models\SourceFile;
use App\Services\Export\ExplanationExporter;
use App\Services\Export\ExportBlockedException;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Xuất lại một tệp phản hồi kèm cột giải trình. Một tệp nguồn -> một tệp kết quả. */
class ExportResponseFile implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly int $sourceFileId,
        public readonly ?int $actorId = null,
        public readonly bool $force = false,
    ) {}

    public function handle(ExplanationExporter $exporter): void
    {
        $file = SourceFile::with('batch')->findOrFail($this->sourceFileId);

        try {
            $artifact = $exporter->export($file, $this->actorId, $this->force);

            Audit::record($this->actorId, 'export.released', $artifact, [
                'source_file' => $file->original_name,
                'version' => $artifact->version,
                'sha256' => $artifact->sha256,
                'warnings' => $artifact->verification['warnings'] ?? [],
            ]);
        } catch (ExportBlockedException $e) {
            Audit::record($this->actorId, 'export.blocked', $file, ['problems' => $e->problems]);

            throw $e;
        }
    }
}
