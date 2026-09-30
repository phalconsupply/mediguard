<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\SourceFile;
use App\Services\Assignment\IssueCaseBuilder;
use App\Services\Import\ResponseFileImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Nạp tệp phản hồi BHYT từ dòng lệnh.
 *
 * Dùng cho bài đo tải và cho việc nạp hàng loạt tệp của một kỳ mà không phải
 * tải lên qua trình duyệt. Chạy đồng bộ, in số liệu đối soát ra màn hình.
 */
class ImportResponseCommand extends Command
{
    protected $signature = 'mediguard:import-response
        {path : Đường dẫn tệp hoặc thư mục chứa XLSX/CSV}
        {--batch= : Mã đợt đã có; bỏ trống thì tạo đợt mới}
        {--period= : Kỳ quyết toán YYYYMM}
        {--facility= : Mã cơ sở KCB}
        {--sheet= : Chỉ số trang tính (0-based) nếu muốn chỉ định}
        {--header-row= : Số dòng tiêu đề nếu muốn chỉ định}';

    protected $description = 'Nạp tệp phản hồi BHYT và sinh vụ việc giải trình.';

    public function handle(ResponseFileImporter $importer, IssueCaseBuilder $builder): int
    {
        $path = $this->argument('path');

        $files = is_dir($path)
            ? array_values(array_filter(
                glob(rtrim($path, '/\\').'/*') ?: [],
                static fn (string $f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['xlsx', 'csv'], true)
            ))
            : [$path];

        if ($files === []) {
            $this->error('Không tìm thấy tệp XLSX hoặc CSV nào.');

            return self::FAILURE;
        }

        $batch = $this->option('batch')
            ? ImportBatch::where('code', $this->option('batch'))->firstOrFail()
            : ImportBatch::create([
                'code' => 'CLI'.now()->format('YmdHis'),
                'kind' => ImportBatch::KIND_RESPONSE,
                'period' => $this->option('period'),
                'facility_code' => $this->option('facility'),
                'title' => 'Nạp từ dòng lệnh '.now()->format('d/m/Y H:i'),
                'status' => 'ingesting',
            ]);

        $this->info("Đợt: {$batch->code}");

        $overrides = array_filter([
            'sheet_index' => $this->option('sheet') !== null ? (int) $this->option('sheet') : null,
            'header_row_number' => $this->option('header-row') !== null ? (int) $this->option('header-row') : null,
        ], static fn ($v) => $v !== null);

        foreach ($files as $absolute) {
            $name = basename($absolute);
            $hash = hash_file('sha256', $absolute);

            if (SourceFile::where('import_batch_id', $batch->id)->where('sha256', $hash)->exists()) {
                $this->warn("Bỏ qua {$name}: đã nạp vào đợt này.");

                continue;
            }

            $format = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
            $relative = sprintf('%s/batches/%d/%s.%s', config('mediguard.storage.root'), $batch->id, $hash, $format);

            Storage::disk(config('mediguard.storage.disk'))->put($relative, fopen($absolute, 'rb'));

            $file = SourceFile::create([
                'import_batch_id' => $batch->id,
                'original_name' => $name,
                'stored_path' => $relative,
                'sha256' => $hash,
                'size_bytes' => filesize($absolute) ?: 0,
                'format' => $format,
                'error_code' => SourceFile::detectErrorCode($name),
                'status' => 'reading',
            ]);

            $started = microtime(true);

            try {
                $stats = $importer->import($file, Storage::disk(config('mediguard.storage.disk'))->path($relative), $overrides);
                $assignment = $builder->build($file);

                $this->line(sprintf(
                    '%s | mã %s | %d dòng (%d chi phí, %d tổng hợp) | %d cột | giao được %d, chờ phân %d | %.1fs',
                    $name,
                    $file->error_code ?? '—',
                    $stats['rows'],
                    $stats['cost_rows'],
                    $stats['summary_rows'],
                    $stats['columns'],
                    $assignment['assigned'],
                    $assignment['unassigned'],
                    microtime(true) - $started,
                ));
            } catch (\Throwable $e) {
                $file->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                $this->error("{$name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
