<?php

namespace Database\Seeders;

use App\Models\CheckRule;
use App\Models\CheckRuleVersion;
use App\Models\ErrorCatalog;
use Illuminate\Database\Seeder;

/**
 * Quy tắc tự kiểm tra mẫu.
 *
 * Tất cả được tạo ở trạng thái **Đang phân tích**, không phải "Đang áp dụng":
 * tên tệp và câu ghi chú của BHYT mới là gợi ý xây dựng quy tắc, chưa đủ để
 * khẳng định điều kiện chính xác cho mọi hồ sơ. Bộ phận BHYT cùng khoa chuyên môn
 * phải thẩm định, bổ sung ca kiểm thử rồi mới duyệt và áp dụng.
 */
class CheckRuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->rule(
            code: 'R-9065-THOI-GIAN-BANG-0',
            name: 'DVKT có thời gian thực hiện bằng 0',
            errorCode: '9065',
            definition: [
                'subject' => 'detail',
                'filter' => ['all' => [['field' => 'detail_kind', 'op' => 'equals', 'value' => 'dvkt']]],
                'condition' => ['any' => [
                    ['field' => 'ngay_kq', 'op' => 'same_minute_as_field', 'value' => 'ngay_th_yl'],
                ]],
                'message' => 'Giờ thực hiện và giờ có kết quả trùng phút, thời lượng thực hiện bằng 0.',
            ],
            required: ['ngay_th_yl', 'ngay_kq'],
            note: 'Mẫu XML3 có 48/52 dòng mang NGAY_KQ; 4 dòng còn lại phải trả về không đủ dữ liệu.'
        );

        $this->rule(
            code: 'R-5066-KQ-NGOAI-DOT',
            name: 'Giờ có kết quả nằm ngoài thời gian điều trị',
            errorCode: '5066',
            definition: [
                'subject' => 'detail',
                'condition' => ['any' => [
                    ['field' => 'ngay_kq', 'op' => 'outside_encounter_window'],
                ]],
                'message' => 'Thời điểm có kết quả nằm ngoài khoảng vào viện — ra viện của hồ sơ.',
            ],
            required: ['ngay_kq'],
            note: 'Cần NGAY_VAO và NGAY_RA của hồ sơ; thiếu một trong hai thì báo không đủ dữ liệu.'
        );

        $this->rule(
            code: 'R-5071-YL-NGOAI-DOT',
            name: 'Giờ y lệnh nằm ngoài thời gian điều trị',
            errorCode: '5071',
            definition: [
                'subject' => 'detail',
                'condition' => ['any' => [
                    ['field' => 'ngay_yl', 'op' => 'outside_encounter_window'],
                ]],
                'message' => 'Thời điểm y lệnh nằm ngoài khoảng vào viện — ra viện của hồ sơ.',
            ],
            required: ['ngay_yl'],
            note: 'Độ chính xác mốc thời gian khác nhau giữa nguồn; không suy ra giây bị thiếu là 00.'
        );

        $this->rule(
            code: 'R-3020-THUOC-TRUOC-CLS',
            name: 'Thuốc được kê trước khi có kết quả cận lâm sàng',
            errorCode: '3020',
            definition: [
                'subject' => 'detail',
                'filter' => ['all' => [['field' => 'detail_kind', 'op' => 'equals', 'value' => 'thuoc']]],
                'condition' => ['any' => [
                    ['field' => 'ngay_yl', 'op' => 'before_field', 'value' => 'ngay_th_yl'],
                ]],
                'message' => 'Giờ y lệnh thuốc sớm hơn giờ thực hiện y lệnh ghi nhận được.',
            ],
            required: ['ngay_yl', 'ngay_th_yl'],
            note: 'Quy tắc đầy đủ cần nối thuốc với chỉ định cận lâm sàng cùng hồ sơ; bản này mới kiểm tra trình tự trong cùng dòng.'
        );

        $this->rule(
            code: 'R-9092-CDHA-THIEU-NGUOI-TH',
            name: 'Dịch vụ CĐHA không ghi người thực hiện',
            errorCode: '9092',
            definition: [
                'subject' => 'detail',
                'filter' => ['all' => [['field' => 'detail_kind', 'op' => 'equals', 'value' => 'dvkt']]],
                'condition' => ['all' => [
                    ['field' => 'nguoi_thuc_hien', 'op' => 'is_empty'],
                    ['field' => 'ma_dich_vu', 'op' => 'is_present'],
                ]],
                'message' => 'Dòng dịch vụ kỹ thuật không có người thực hiện.',
            ],
            required: [],
            note: 'Không đặt trường bắt buộc vì đây chính là quy tắc kiểm tra sự vắng mặt của trường đó.'
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $required
     */
    private function rule(string $code, string $name, string $errorCode, array $definition, array $required, string $note): void
    {
        $rule = CheckRule::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $note,
                'error_catalog_id' => ErrorCatalog::where('code', $errorCode)->value('id'),
                'scope' => 'all',
            ]
        );

        CheckRuleVersion::updateOrCreate(
            ['check_rule_id' => $rule->id, 'version' => 1],
            [
                'definition' => $definition,
                'required_fields' => $required,
                'severity' => 'warning',
                // Chưa duyệt, chưa áp dụng: cần thẩm định chuyên môn và bộ ca kiểm thử trước.
                'status' => CheckRuleVersion::STATUS_ANALYSING,
                'test_cases' => null,
            ]
        );
    }
}
