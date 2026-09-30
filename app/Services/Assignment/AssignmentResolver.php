<?php

namespace App\Services\Assignment;

use App\Models\AssignmentRule;
use App\Models\Department;
use App\Models\ErrorCatalog;
use App\Models\ServiceOwner;
use App\Models\SourceRow;
use App\Models\StaffDirectory;

/**
 * Chọn người chịu trách nhiệm cho một dòng phản hồi.
 *
 * Thứ tự chọn do `assignment_rules` của từng mã lỗi quyết định, vì lỗi chỉ định
 * và lỗi thực hiện có đầu mối khác nhau. Khi không xác định được người,
 * vụ việc rơi vào hàng chờ của đầu mối — không bao giờ tự gán người chỉ định
 * làm người thực hiện.
 */
final class AssignmentResolver
{
    /** Thứ tự dự phòng khi mã lỗi chưa có quy tắc riêng. */
    private const DEFAULT_CHAIN = ['ma_bac_si', 'service_owner', 'department'];

    /** Thứ tự cho nhóm lỗi thực hiện/thời gian/kết quả. */
    private const PERFORMER_CHAIN = ['ma_bsi_thuc_hien', 'service_owner', 'department'];

    /** @var array<string, StaffDirectory|null> */
    private array $staffCache = [];

    /** @var array<string, Department|null> */
    private array $departmentCache = [];

    /** @var array<string, ServiceOwner|null> */
    private array $serviceCache = [];

    public function __construct(private readonly ?string $effectiveDate = null) {}

    /**
     * @return array{assignee_id: ?int, department_id: ?int, resolver: string, status: string}
     */
    public function resolve(SourceRow $row, ?AssignmentRule $rule, ?ErrorCatalog $catalog): array
    {
        $chain = $rule?->resolver_chain ?: $this->defaultChain($catalog);

        foreach ($chain as $step) {
            $hit = $this->applyStep($step, $row);
            if ($hit !== null) {
                return $hit;
            }
        }

        // Không tìm được ai: chuyển vào hàng chờ của đầu mối để phân tiếp.
        $fallback = $rule?->fallback_department_id
            ?? $this->outpatientPool()?->id;

        return [
            'assignee_id' => null,
            'department_id' => $fallback,
            'resolver' => 'unresolved',
            'status' => 'unassigned',
        ];
    }

    /** @return array{assignee_id: ?int, department_id: ?int, resolver: string, status: string}|null */
    private function applyStep(string $step, SourceRow $row): ?array
    {
        return match ($step) {
            'ma_bsi_thuc_hien' => $this->byStaffCode($row->ma_bsi_thuc_hien, 'ma_bsi_thuc_hien', requirePerform: true),
            'ma_bac_si' => $this->byStaffCode($row->ma_bac_si, 'ma_bac_si', requireOrder: true),
            'service_owner' => $this->byServiceOwner($row),
            'department' => $this->byDepartmentCode($row),
            'outpatient_pool' => $this->byOutpatientPool(),
            default => null,
        };
    }

    private function byStaffCode(
        ?string $code,
        string $resolver,
        bool $requireOrder = false,
        bool $requirePerform = false,
    ): ?array {
        if ($code === null || $code === '') {
            return null;
        }

        $staff = $this->lookupStaff($code);
        if ($staff === null) {
            return null;
        }
        if ($requireOrder && ! $staff->can_order) {
            return null;
        }
        if ($requirePerform && ! $staff->can_perform) {
            return null;
        }
        if ($staff->user_id === null) {
            // Có mã nhân sự nhưng chưa nối được tài khoản: vẫn giao về khoa quản lý
            // để trưởng khoa phân tiếp, không bỏ trống hoàn toàn.
            return $staff->department_id === null ? null : [
                'assignee_id' => null,
                'department_id' => $staff->department_id,
                'resolver' => $resolver.':department_only',
                'status' => 'unassigned',
            ];
        }

        return [
            'assignee_id' => $staff->user_id,
            'department_id' => $staff->department_id ?? $staff->user?->department_id,
            'resolver' => $resolver,
            'status' => 'assigned',
        ];
    }

    private function byServiceOwner(SourceRow $row): ?array
    {
        $code = $row->ma_cp;
        if ($code === null || $code === '') {
            return null;
        }

        $owner = $this->serviceCache[$code] ??= ServiceOwner::query()
            ->where('service_code', $code)
            ->effectiveOn($this->effectiveDate)
            ->first();

        if ($owner === null) {
            return null;
        }

        return [
            'assignee_id' => null,
            'department_id' => $owner->department_id,
            'resolver' => 'service_owner',
            'status' => 'unassigned',
        ];
    }

    private function byDepartmentCode(SourceRow $row): ?array
    {
        $code = $row->ma_khoa;
        if ($code === null || $code === '') {
            return null;
        }

        $department = $this->lookupDepartment($code);
        if ($department === null) {
            return null;
        }

        // K01 với TEN_KHOA trống là luồng ngoại trú theo nghiệp vụ bệnh viện,
        // không phải dữ liệu lỗi: gom về nhóm ngoại trú để phân tiếp theo người.
        return [
            'assignee_id' => null,
            'department_id' => $department->id,
            'resolver' => $department->is_outpatient_pool ? 'outpatient_pool' : 'ma_khoa',
            'status' => 'unassigned',
        ];
    }

    private function byOutpatientPool(): ?array
    {
        $pool = $this->outpatientPool();
        if ($pool === null) {
            return null;
        }

        return [
            'assignee_id' => null,
            'department_id' => $pool->id,
            'resolver' => 'outpatient_pool',
            'status' => 'unassigned',
        ];
    }

    /**
     * Mã lỗi chưa có quy tắc riêng: suy từ vai trò chịu trách nhiệm trong danh mục.
     * Lỗi chưa phân loại dùng thứ tự mặc định và vẫn phân công được.
     *
     * @return list<string>
     */
    private function defaultChain(?ErrorCatalog $catalog): array
    {
        return match ($catalog?->responsible_role) {
            ErrorCatalog::ROLE_PERFORMER => self::PERFORMER_CHAIN,
            ErrorCatalog::ROLE_BOTH => ['ma_bsi_thuc_hien', 'ma_bac_si', 'service_owner', 'department'],
            default => self::DEFAULT_CHAIN,
        };
    }

    private function lookupStaff(string $code): ?StaffDirectory
    {
        return $this->staffCache[$code] ??= StaffDirectory::query()
            ->with('user:id,department_id')
            ->where('staff_code', $code)
            ->effectiveOn($this->effectiveDate)
            ->orderByDesc('effective_from')
            ->first();
    }

    private function lookupDepartment(string $code): ?Department
    {
        return $this->departmentCache[$code] ??= Department::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();
    }

    private function outpatientPool(): ?Department
    {
        return $this->departmentCache['__pool'] ??= Department::query()
            ->where('is_outpatient_pool', true)
            ->where('is_active', true)
            ->first();
    }
}
