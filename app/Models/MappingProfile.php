<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cấu hình ánh xạ có phiên bản cho từng kiểu xuất HIS.
 * Mọi định dạng đều đưa về cùng bộ trường chuẩn để dùng chung thư viện quy tắc.
 */
#[Fillable(['code', 'name', 'format', 'version', 'definition', 'reader_options', 'is_active'])]
class MappingProfile extends Model
{
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'reader_options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function label(): string
    {
        return sprintf('%s v%d (%s)', $this->name, $this->version, strtoupper($this->format));
    }
}
