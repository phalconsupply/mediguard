<?php

namespace App\Services\Presentation;

use App\Models\ColumnPreset;
use App\Models\ErrorCatalog;
use App\Models\SourceColumn;
use App\Models\SourceSheet;
use App\Support\Normalizer;
use Illuminate\Support\Collection;

/**
 * Quyết định danh sách cột hiển thị cho một màn hình công việc.
 *
 * Cấu hình cụ thể hơn thắng: đợt > mã lỗi > khoa > nhóm lỗi > toàn viện.
 * Cấu hình tạm của một lần xem không ghi đè mặc định — phần đó do controller
 * xử lý bằng tham số truy vấn, không lưu xuống đây.
 *
 * Cột thiếu ở kỳ này được bỏ qua và báo trên màn hình; cột mới xuất hiện được
 * liệt kê riêng để quản lý quyết định, thay vì tự bật lên.
 */
final class ColumnPresetResolver
{
    /**
     * @param  list<string>|null  $temporary  lựa chọn tạm của lần xem hiện tại
     * @return array{
     *     columns: list<SourceColumn>,
     *     preset: ?ColumnPreset,
     *     missing: list<string>,
     *     new_columns: list<SourceColumn>
     * }
     */
    public function resolve(
        SourceSheet $sheet,
        ?ErrorCatalog $catalog,
        ?int $departmentId,
        ?int $batchId,
        ?array $temporary = null,
    ): array {
        $available = $sheet->columns()->get();
        $byInternalCode = $available->keyBy('internal_code');

        $wanted = $temporary ?? $this->presetColumns($catalog, $departmentId, $batchId, $preset);
        $preset ??= null;

        $columns = [];
        $missing = [];

        foreach ($wanted as $code) {
            $column = $byInternalCode->get($code) ?? $this->matchByHeader($byInternalCode, $code);

            if ($column === null) {
                $missing[] = $code;

                continue;
            }

            $columns[] = $column;
        }

        if ($columns === []) {
            $columns = $this->fallbackColumns($byInternalCode);
        }

        $chosen = array_map(static fn (SourceColumn $c) => $c->internal_code, $columns);
        $newColumns = $available
            ->reject(fn (SourceColumn $c) => in_array($c->internal_code, $chosen, true))
            ->values()
            ->all();

        return [
            'columns' => $columns,
            'preset' => $preset,
            'missing' => $missing,
            'new_columns' => $newColumns,
        ];
    }

    /**
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private function presetColumns(
        ?ErrorCatalog $catalog,
        ?int $departmentId,
        ?int $batchId,
        ?ColumnPreset &$preset,
    ): array {
        $query = ColumnPreset::query()->where('is_default', true);

        $query->where(function ($q) use ($catalog, $departmentId, $batchId) {
            $q->where('scope_type', ColumnPreset::SCOPE_GLOBAL);

            if ($catalog !== null) {
                $q->orWhere(fn ($s) => $s->where('scope_type', ColumnPreset::SCOPE_ERROR_CODE)->where('scope_value', $catalog->code));
                if ($catalog->group !== null) {
                    $q->orWhere(fn ($s) => $s->where('scope_type', ColumnPreset::SCOPE_ERROR_GROUP)->where('scope_value', $catalog->group));
                }
            }
            if ($departmentId !== null) {
                $q->orWhere(fn ($s) => $s->where('scope_type', ColumnPreset::SCOPE_DEPARTMENT)->where('scope_value', (string) $departmentId));
            }
            if ($batchId !== null) {
                $q->orWhere(fn ($s) => $s->where('scope_type', ColumnPreset::SCOPE_BATCH)->where('scope_value', (string) $batchId));
            }
        });

        $presets = $query->get()->sortByDesc(
            fn (ColumnPreset $p) => ColumnPreset::SCOPE_PRIORITY[$p->scope_type] ?? 0
        );

        $preset = $presets->first();

        if ($preset !== null) {
            return $preset->columns;
        }

        return array_map(
            static fn (string $header) => Normalizer::internalCode(Normalizer::header($header), 1),
            config('mediguard.default_columns')
        );
    }

    /**
     * Cấu hình cũ có thể lưu tên cột trần. Khớp theo tiêu đề chuẩn hóa,
     * lấy lần xuất hiện đầu tiên.
     *
     * @param  Collection<string, SourceColumn>  $byInternalCode
     */
    private function matchByHeader(Collection $byInternalCode, string $code): ?SourceColumn
    {
        if (str_contains($code, '#')) {
            return null;
        }

        $header = Normalizer::header($code);

        return $byInternalCode->first(fn (SourceColumn $c) => $c->normalized_header === $header);
    }

    /**
     * Khi không cấu hình nào khớp tệp này, hiện các cột nhận diện được
     * để màn hình không bị trống.
     *
     * @param  Collection<string, SourceColumn>  $byInternalCode
     * @return list<SourceColumn>
     */
    private function fallbackColumns(Collection $byInternalCode): array
    {
        return $byInternalCode->take(12)->values()->all();
    }
}
