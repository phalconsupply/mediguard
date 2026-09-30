<?php

namespace App\Jobs;

use App\Models\MappingProfile;
use App\Models\SourceFile;
use App\Services\Import\SourceFileStore;
use App\Services\Precheck\XmlPrecheckImporter;
use App\Support\Audit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Nạp dữ liệu HIS chuẩn bị gửi (XML/XLSX/CSV) vào mô hình chuẩn của luồng tự kiểm tra. */
class IngestPrecheckFile implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public readonly int $sourceFileId,
        public readonly int $mappingProfileId,
        public readonly ?int $actorId = null,
    ) {}

    public function handle(SourceFileStore $store, XmlPrecheckImporter $xmlImporter): void
    {
        $file = SourceFile::with('batch')->findOrFail($this->sourceFileId);
        $profile = MappingProfile::findOrFail($this->mappingProfileId);

        if ($file->format !== $profile->format) {
            $file->update([
                'status' => 'failed',
                'failure_reason' => "Cấu hình ánh xạ dành cho {$profile->format}, không dùng cho tệp {$file->format}.",
            ]);

            return;
        }

        $file->update(['status' => 'reading', 'failure_reason' => null]);

        try {
            $result = match ($file->format) {
                'xml' => $xmlImporter->import($file, $store->absolutePath($file), $profile),
                default => throw new \RuntimeException(
                    "Chưa có bộ nạp tự kiểm tra cho định dạng {$file->format}; cần mẫu HIS tương ứng để chốt ánh xạ."
                ),
            };

            $file->update([
                'status' => 'ready',
                'ingested_at' => now(),
                'detected' => [
                    'mapping_profile' => $profile->code.' v'.$profile->version,
                    'header' => $result['header'],
                    'encounters' => $result['encounters'],
                    'details' => $result['details'],
                    'coverage' => $result['coverage'],
                    // Nhánh không áp dụng có thể rỗng; chỉ những mục dưới đây mới là vấn đề cần xem.
                    'issues' => $result['issues'],
                ],
            ]);

            Audit::record($this->actorId, 'precheck.file_ingested', $file, [
                'encounters' => $result['encounters'],
                'details' => $result['details'],
                'issue_count' => count($result['issues']),
            ]);
        } catch (\Throwable $e) {
            $file->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            Audit::record($this->actorId, 'precheck.file_failed', $file, ['error' => $e->getMessage()]);

            throw $e;
        }
    }
}
