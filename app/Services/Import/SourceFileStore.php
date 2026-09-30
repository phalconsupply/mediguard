<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\SourceFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Ghi tệp đã nạp vào kho bất biến và tạo bản ghi tệp nguồn.
 *
 * Tệp được lưu theo hash nên nạp lại đúng tệp cũ không sinh việc trùng:
 * ràng buộc duy nhất (đợt, hash) chặn ngay ở tầng dữ liệu.
 */
final class SourceFileStore
{
    /** Định dạng nhận được, suy từ phần mở rộng của tên tệp gốc. */
    private const FORMATS = ['xlsx' => 'xlsx', 'xls' => 'xlsx', 'csv' => 'csv', 'xml' => 'xml'];

    public function store(ImportBatch $batch, UploadedFile $upload): SourceFile
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        $format = self::FORMATS[$extension] ?? null;

        if ($format === null) {
            throw new \InvalidArgumentException("Không nhận tệp .{$extension}. Chỉ nhận XLSX, CSV hoặc XML.");
        }

        $allowed = $batch->kind === ImportBatch::KIND_RESPONSE
            ? config('mediguard.import.allowed_response_formats')
            : config('mediguard.import.allowed_precheck_formats');

        if (! in_array($format, $allowed, true)) {
            throw new \InvalidArgumentException("Đợt {$batch->kind} không nhận định dạng {$format}.");
        }

        $hash = hash_file('sha256', $upload->getRealPath());

        $existing = SourceFile::where('import_batch_id', $batch->id)->where('sha256', $hash)->first();
        if ($existing !== null) {
            throw new DuplicateSourceFileException($existing);
        }

        $relative = sprintf(
            '%s/batches/%d/%s.%s',
            config('mediguard.storage.root'),
            $batch->id,
            $hash,
            $format
        );

        Storage::disk(config('mediguard.storage.disk'))->putFileAs(
            dirname($relative),
            $upload,
            basename($relative)
        );

        return SourceFile::create([
            'import_batch_id' => $batch->id,
            'original_name' => $upload->getClientOriginalName(),
            'stored_path' => $relative,
            'sha256' => $hash,
            'size_bytes' => $upload->getSize() ?: 0,
            'format' => $format,
            'error_code' => $batch->kind === ImportBatch::KIND_RESPONSE
                ? SourceFile::detectErrorCode($upload->getClientOriginalName())
                : null,
            'status' => 'received',
        ]);
    }

    public function absolutePath(SourceFile $file): string
    {
        return Storage::disk(config('mediguard.storage.disk'))->path($file->stored_path);
    }
}
