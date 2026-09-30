<?php

namespace App\Services\Precheck;

use App\Models\MappingProfile;
use App\Models\PrecheckDetail;
use App\Models\SourceFile;
use App\Services\Import\Xml\GiamDinhHsReader;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;

/**
 * Nạp XML gửi giám định vào mô hình chuẩn của luồng tự kiểm tra.
 *
 * Mỗi bản ghi giữ vị trí nguồn gồm thứ tự `HOSO`, `LOAIHOSO`, `STT` và thứ tự phần tử.
 * Không suy ra `ID_CP` từ `STT`: mẫu XML không có trường này, và việc ghép với dòng
 * phản hồi BHYT là công việc riêng của bước đối chiếu.
 */
final class XmlPrecheckImporter
{
    private const CHUNK = 500;

    /**
     * @return array{encounters: int, details: int, issues: list<string>, header: array<string, mixed>, coverage: array<string, int>}
     */
    public function import(SourceFile $file, string $absolutePath, MappingProfile $profile): array
    {
        $reader = new GiamDinhHsReader($absolutePath);
        $header = $reader->header();
        $definition = $profile->definition;

        $encounterMap = $definition['encounter'] ?? [];
        $detailMaps = $definition['details'] ?? [];

        $encounters = 0;
        $issues = [];
        $coverage = [];
        $detailBuffer = [];
        $detailCount = 0;

        foreach ($reader->hoSo() as $hoSoIndex => $hoSo) {
            $issues = array_merge($issues, $hoSo['issues']);

            $encounterSource = $encounterMap['source'] ?? 'XML1';
            $encounterRecords = $hoSo['files'][$encounterSource] ?? [];

            if ($encounterRecords === []) {
                $issues[] = "HOSO {$hoSoIndex}: không có bản ghi {$encounterSource}, bỏ qua hồ sơ này.";

                continue;
            }

            $encounterId = $this->storeEncounter(
                $file,
                $encounterRecords[0],
                $encounterMap['fields'] ?? [],
                $hoSoIndex,
                $encounterSource
            );

            if ($encounterId === null) {
                $issues[] = "HOSO {$hoSoIndex}: bản ghi {$encounterSource} thiếu MA_LK, không nạp được.";

                continue;
            }

            $encounters++;
            $maLk = $encounterId['ma_lk'];

            foreach ($detailMaps as $map) {
                $source = $map['source'];
                $records = $hoSo['files'][$source] ?? [];
                $coverage[$source] = ($coverage[$source] ?? 0) + count($records);

                foreach ($records as $record) {
                    $detailBuffer[] = $this->detailRow(
                        $file,
                        $encounterId['id'],
                        $maLk,
                        $record,
                        $map,
                        $hoSoIndex
                    );
                    $detailCount++;

                    if (count($detailBuffer) >= self::CHUNK) {
                        DB::table('precheck_details')->insert($detailBuffer);
                        $detailBuffer = [];
                    }
                }
            }
        }

        if ($detailBuffer !== []) {
            DB::table('precheck_details')->insert($detailBuffer);
        }

        if (($header['declared_count'] ?? null) !== null && $header['declared_count'] !== $encounters) {
            $issues[] = sprintf(
                'SOLUONGHOSO khai báo %d nhưng nạp được %d hồ sơ.',
                $header['declared_count'],
                $encounters
            );
        }

        return [
            'encounters' => $encounters,
            'details' => $detailCount,
            'issues' => $issues,
            'header' => $header,
            'coverage' => $coverage,
        ];
    }

    /**
     * @param  array<string, string>  $record
     * @param  array<string, list<string>>  $fields
     * @return array{id: int, ma_lk: string}|null
     */
    private function storeEncounter(
        SourceFile $file,
        array $record,
        array $fields,
        int $hoSoIndex,
        string $source,
    ): ?array {
        $values = array_fill_keys(array_keys(CanonicalFields::ENCOUNTER), null);

        foreach ($fields as $canonical => $candidates) {
            $values[$canonical] = CanonicalFields::cast(
                CanonicalFields::kindOfEncounterField($canonical),
                $this->pick($record, $candidates)
            );
        }

        $maLk = $values['ma_lk'] ?? null;
        if ($maLk === null || $maLk === '') {
            return null;
        }

        $row = [
            'import_batch_id' => $file->import_batch_id,
            'source_file_id' => $file->id,
            'locator' => json_encode([
                'file' => $file->original_name,
                'hoso_index' => $hoSoIndex,
                'loai_ho_so' => $source,
                'stt' => $record['STT'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'extra' => json_encode($this->extraFields($record, $fields), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($values as $key => $value) {
            $row[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        }

        $id = DB::table('precheck_encounters')->insertGetId($row);

        return ['id' => $id, 'ma_lk' => (string) $maLk];
    }

    /**
     * @param  array<string, string>  $record
     * @param  array{source: string, kind: string, fields: array<string, list<string>>}  $map
     * @return array<string, mixed>
     */
    private function detailRow(
        SourceFile $file,
        int $encounterId,
        string $maLk,
        array $record,
        array $map,
        int $hoSoIndex,
    ): array {
        $fields = $map['fields'];
        $precision = 0;

        // Mỗi loại bảng chi tiết khai báo một tập trường khác nhau, nhưng một lần ghi
        // hàng loạt phải có cùng bộ cột. Bắt đầu từ đủ bộ trường chuẩn, để trống phần
        // nguồn này không có — trống ở đây nghĩa là "không có dữ liệu", và quy tắc sẽ
        // báo không đủ dữ liệu thay vì kết luận đạt.
        $values = array_fill_keys(array_keys(CanonicalFields::DETAIL), null);

        foreach ($fields as $canonical => $candidates) {
            $raw = $this->pick($record, $candidates);
            $kind = CanonicalFields::kindOfDetailField($canonical);

            if ($kind === CanonicalFields::KIND_DATETIME) {
                $parsed = Normalizer::dateTimeWithPrecision($raw);
                $values[$canonical] = $parsed['value']?->format('Y-m-d H:i:s');
                $precision = max($precision, $parsed['precision']);

                continue;
            }

            $values[$canonical] = CanonicalFields::cast($kind, $raw);
        }

        return array_merge([
            'import_batch_id' => $file->import_batch_id,
            'source_file_id' => $file->id,
            'precheck_encounter_id' => $encounterId,
            'ma_lk' => $values['ma_lk'] ?? $maLk,
            'detail_kind' => $map['kind'] ?? PrecheckDetail::KIND_KHAC,
            'loai_ho_so' => $map['source'],
            'time_precision' => $precision,
            'locator' => json_encode([
                'file' => $file->original_name,
                'hoso_index' => $hoSoIndex,
                'loai_ho_so' => $map['source'],
                'stt' => $record['STT'] ?? null,
                'position' => $record['_position'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'extra' => json_encode($this->extraFields($record, $fields), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ], $values);
    }

    /**
     * Giá trị đầu tiên có nội dung trong danh sách tên phần tử ứng viên.
     * Danh sách ứng viên cho phép một trường chuẩn nhận nhiều tên nguồn
     * (`MA_THE_BHYT` và `MA_THE`) mà không phải sửa mã.
     *
     * @param  array<string, string>  $record
     * @param  list<string>  $candidates
     */
    private function pick(array $record, array $candidates): ?string
    {
        foreach ($candidates as $name) {
            $value = $record[strtoupper($name)] ?? null;
            if ($value !== null && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Giữ lại các phần tử chưa ánh xạ để mở rộng quy tắc về sau
     * mà không phải nạp lại tệp.
     *
     * @param  array<string, string>  $record
     * @param  array<string, list<string>>  $fields
     * @return array<string, string>
     */
    private function extraFields(array $record, array $fields): array
    {
        $mapped = [];
        foreach ($fields as $candidates) {
            foreach ($candidates as $name) {
                $mapped[strtoupper($name)] = true;
            }
        }

        return array_filter(
            $record,
            static fn (string $value, string $key) => $key !== '_position'
                && ! isset($mapped[$key])
                && trim($value) !== '',
            ARRAY_FILTER_USE_BOTH
        );
    }
}
