<?php

namespace Database\Seeders;

use App\Models\AssignmentRule;
use App\Models\ErrorCatalog;
use Illuminate\Database\Seeder;

/**
 * Danh mục lỗi và hướng giao việc ban đầu, theo bảng khảo sát trong đề xuất.
 *
 * Đây là điểm khởi đầu để hệ thống chạy được ngay, **chưa phải danh mục đã được
 * bệnh viện phê duyệt**. Quản lý phải rà lại tên, nhóm, vai trò chịu trách nhiệm
 * và thứ tự chọn người trước khi dùng cho kỳ thật.
 */
class ErrorCatalogSeeder extends Seeder
{
    /**
     * Mã lỗi => [tên, nhóm, vai trò chịu trách nhiệm, ưu tiên]
     *
     * Vai trò lấy theo bản chất lỗi: lỗi chỉ định/kê đơn thuộc người chỉ định,
     * lỗi thời gian thực hiện/kết quả thuộc người thực hiện.
     */
    private const CATALOG = [
        '2109' => ['Diosmin không phù hợp chẩn đoán', 'thuoc', ErrorCatalog::ROLE_ORDERER, 3],
        '2123' => ['Chỉ định điện tim thường', 'chi_dinh', ErrorCatalog::ROLE_ORDERER, 3],
        '3008' => ['Bác sĩ chỉ định thuốc khi đang thực hiện DVKT cho người khác', 'thoi_gian', ErrorCatalog::ROLE_BOTH, 2],
        '3014' => ['Cấp thuốc chồng giữa các đợt điều trị', 'thuoc', ErrorCatalog::ROLE_ORDERER, 2],
        '3020' => ['Chỉ định thuốc trước xét nghiệm/CĐHA/TDCN', 'thoi_gian', ErrorCatalog::ROLE_ORDERER, 2],
        '3022' => ['Chỉ định thuốc trước khám bệnh', 'thoi_gian', ErrorCatalog::ROLE_ORDERER, 3],
        '3027' => ['Azithromycin vượt liều', 'thuoc', ErrorCatalog::ROLE_ORDERER, 2],
        '3034' => ['Thuốc chống chỉ định', 'thuoc', ErrorCatalog::ROLE_ORDERER, 1],
        '3130' => ['Chỉ định xét nghiệm acid uric', 'chi_dinh', ErrorCatalog::ROLE_ORDERER, 3],
        '5066' => ['Giờ có kết quả ngoài thời gian điều trị', 'thoi_gian', ErrorCatalog::ROLE_PERFORMER, 2],
        '5069' => ['Giờ thực hiện ngoài thời gian điều trị', 'thoi_gian', ErrorCatalog::ROLE_PERFORMER, 2],
        '5071' => ['Giờ y lệnh ngoài thời gian điều trị', 'thoi_gian', ErrorCatalog::ROLE_ORDERER, 3],
        '5073' => ['Cùng nhân viên, cùng dịch vụ, cùng phút', 'thoi_gian', ErrorCatalog::ROLE_PERFORMER, 2],
        '9006' => ['Chỉ định urê/creatinin theo chẩn đoán', 'chi_dinh', ErrorCatalog::ROLE_ORDERER, 3],
        '9024' => ['Chỉ định glucose máu theo chẩn đoán', 'chi_dinh', ErrorCatalog::ROLE_ORDERER, 3],
        '9031' => ['Chỉ định điện tim theo mã bệnh', 'chi_dinh', ErrorCatalog::ROLE_ORDERER, 3],
        '9065' => ['Thời gian thực hiện DVKT bằng 0', 'dvkt', ErrorCatalog::ROLE_PERFORMER, 1],
        '9092' => ['Thời lượng thực hiện CĐHA dưới mức nêu trong dữ liệu', 'dvkt', ErrorCatalog::ROLE_PERFORMER, 1],
        '9281' => ['Trimetazidin và chẩn đoán đau thắt ngực', 'thuoc', ErrorCatalog::ROLE_ORDERER, 3],
    ];

    /** Thứ tự chọn người theo vai trò chịu trách nhiệm. */
    private const CHAINS = [
        ErrorCatalog::ROLE_ORDERER => ['ma_bac_si', 'department', 'outpatient_pool'],
        ErrorCatalog::ROLE_PERFORMER => ['ma_bsi_thuc_hien', 'service_owner', 'department', 'outpatient_pool'],
        ErrorCatalog::ROLE_BOTH => ['ma_bsi_thuc_hien', 'ma_bac_si', 'department', 'outpatient_pool'],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $code => [$name, $group, $role, $priority]) {
            ErrorCatalog::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'group' => $group,
                    'responsible_role' => $role,
                    // Danh mục gợi ý, vẫn phải được bệnh viện rà lại trước khi coi là đã phân loại.
                    'status' => ErrorCatalog::STATUS_PENDING,
                    'priority' => $priority,
                    'default_columns' => config('mediguard.columns_by_group')[$group] ?? [],
                ]
            );

            AssignmentRule::updateOrCreate(
                ['error_code' => $code, 'effective_from' => null],
                [
                    'resolver_chain' => self::CHAINS[$role],
                    'outpatient_mode' => AssignmentRule::MODE_PER_PERSON,
                    'due_days' => config('mediguard.workflow.default_due_days'),
                ]
            );
        }
    }
}
