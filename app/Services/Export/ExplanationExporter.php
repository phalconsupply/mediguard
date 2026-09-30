<?php

namespace App\Services\Export;

use App\Models\ExportArtifact;
use App\Models\IssueCase;
use App\Models\SourceFile;
use App\Models\SourceRow;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Xuất lại mỗi tệp phản hồi BHYT với đúng một cột mới ở cuối trang dữ liệu.
 *
 * Nội dung cột là bản giải trình đã duyệt. Dòng tổng hợp và dòng đã được BHYT
 * đánh dấu "Chấp nhận" để trống theo quy tắc xuất. Tệp chỉ được công bố khi
 * bước kiểm soát chất lượng không còn lỗi chặn.
 */
final class ExplanationExporter
{
    public const COLUMN_HEADER = 'ĐÃ GIẢI TRÌNH';

    public function __construct(
        private readonly XlsxColumnAppender $xlsx,
        private readonly CsvColumnAppender $csv,
        private readonly ExportVerifier $verifier,
    ) {}

    /**
     * @param  bool  $force  bỏ qua cảnh báo không chặn; lỗi chặn vẫn dừng việc xuất
     */
    public function export(SourceFile $file, ?int $actorId = null, bool $force = false): ExportArtifact
    {
        $sheet = $file->dataSheet();
        if ($sheet === null) {
            throw new \RuntimeException('Tệp chưa có trang dữ liệu đã nạp.');
        }

        $preflight = $this->verifier->preflight($file);
        if ($preflight['blocking'] !== [] && ! $force) {
            throw new ExportBlockedException($preflight['blocking']);
        }

        $explanations = $this->approvedExplanationsByRowNumber($file);
        $disk = Storage::disk(config('mediguard.storage.disk'));
        $sourceAbsolute = $this->localPath($file->stored_path);

        $version = ((int) $file->exports()->max('version')) + 1;
        $relativeTarget = sprintf(
            '%s/exports/%d/v%d_%s',
            config('mediguard.storage.root'),
            $file->id,
            $version,
            $this->outputName($file)
        );
        $targetAbsolute = $this->prepareTargetPath($relativeTarget);

        $valueForRow = static fn (int $rowNumber): ?string => isset($explanations[$rowNumber])
            ? Normalizer::safeSpreadsheetText($explanations[$rowNumber])
            : null;

        $result = match ($file->format) {
            'xlsx' => $this->xlsx->append(
                $sourceAbsolute,
                $targetAbsolute,
                $sheet->sheet_index,
                $sheet->header_row_number,
                $sheet->column_count,
                self::COLUMN_HEADER,
                $valueForRow,
            ),
            'csv' => $this->csv->append(
                $sourceAbsolute,
                $targetAbsolute,
                $sheet->header_row_number,
                self::COLUMN_HEADER,
                $valueForRow,
            ),
            default => throw new \RuntimeException("Không xuất được định dạng {$file->format}."),
        };

        $verification = $this->verifier->verify($file, $sheet, $targetAbsolute, $result, $explanations, $preflight);

        if ($verification['blocking'] !== [] && ! $force) {
            @unlink($targetAbsolute);
            throw new ExportBlockedException($verification['blocking']);
        }

        $artifact = ExportArtifact::create([
            'import_batch_id' => $file->import_batch_id,
            'source_file_id' => $file->id,
            'version' => $version,
            'stored_path' => $relativeTarget,
            'sha256' => hash_file('sha256', $targetAbsolute),
            'size_bytes' => filesize($targetAbsolute) ?: 0,
            'verification' => $verification,
            'released_by' => $actorId,
        ]);

        $this->markExported($file, array_keys($explanations));

        unset($disk);

        return $artifact;
    }

    /**
     * Bản giải trình đã duyệt, tra theo số dòng gốc.
     *
     * @return array<int, string>
     */
    private function approvedExplanationsByRowNumber(SourceFile $file): array
    {
        return DB::table('issue_cases as c')
            ->join('source_rows as r', 'r.id', '=', 'c.source_row_id')
            ->join('explanations as e', function ($join) {
                $join->on('e.issue_case_id', '=', 'c.id')
                    ->where('e.status', '=', 'approved');
            })
            ->where('c.source_file_id', $file->id)
            ->where('c.requires_explanation', true)
            ->orderBy('e.version')
            ->pluck('e.body', 'r.row_number')
            ->all();
    }

    /** @param list<int> $rowNumbers */
    private function markExported(SourceFile $file, array $rowNumbers): void
    {
        if ($rowNumbers === []) {
            return;
        }

        IssueCase::query()
            ->where('source_file_id', $file->id)
            ->where('status', IssueCase::STATUS_APPROVED)
            ->whereIn('source_row_id', SourceRow::query()
                ->where('source_sheet_id', $file->dataSheet()?->id)
                ->whereIn('row_number', $rowNumbers)
                ->select('id'))
            ->update(['status' => IssueCase::STATUS_EXPORTED, 'updated_at' => now()]);
    }

    private function outputName(SourceFile $file): string
    {
        $info = pathinfo($file->original_name);
        $base = $info['filename'] ?? 'ket_qua';
        $extension = $info['extension'] ?? $file->format;

        return sprintf('%s_DA_GIAI_TRINH.%s', $base, $extension);
    }

    private function localPath(string $relative): string
    {
        return Storage::disk(config('mediguard.storage.disk'))->path($relative);
    }

    private function prepareTargetPath(string $relative): string
    {
        $absolute = $this->localPath($relative);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Không tạo được thư mục xuất {$directory}.");
        }

        return $absolute;
    }
}
