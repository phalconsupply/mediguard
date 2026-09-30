<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ErrorCatalog;
use App\Models\ServiceOwner;
use App\Models\SourceRow;
use App\Models\StaffDirectory;
use App\Models\User;
use App\Services\Assignment\AssignmentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentResolverTest extends TestCase
{
    use RefreshDatabase;

    private Department $imaging;

    private Department $outpatientPool;

    private User $performer;

    private User $orderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imaging = Department::create(['code' => 'K02', 'name' => 'Chẩn đoán hình ảnh', 'kind' => 'paraclinical']);
        $this->outpatientPool = Department::create([
            'code' => 'K01',
            'name' => 'Khám ngoại trú',
            'kind' => 'outpatient',
            'is_outpatient_pool' => true,
        ]);

        $this->performer = User::create([
            'name' => 'Người thực hiện', 'email' => 'th@example.test', 'password' => 'secret-long-password',
            'role' => User::ROLE_STAFF, 'department_id' => $this->imaging->id,
        ]);
        $this->orderer = User::create([
            'name' => 'Người chỉ định', 'email' => 'cd@example.test', 'password' => 'secret-long-password',
            'role' => User::ROLE_STAFF, 'department_id' => $this->imaging->id,
        ]);

        StaffDirectory::create([
            'staff_code' => 'NV001', 'user_id' => $this->performer->id,
            'department_id' => $this->imaging->id, 'can_order' => false, 'can_perform' => true,
        ]);
        StaffDirectory::create([
            'staff_code' => 'BS001', 'user_id' => $this->orderer->id,
            'department_id' => $this->imaging->id, 'can_order' => true, 'can_perform' => false,
        ]);
    }

    public function test_a_performance_error_goes_to_the_person_who_performed_the_service(): void
    {
        $catalog = ErrorCatalog::create([
            'code' => '9065', 'name' => 'Thời gian thực hiện bằng 0',
            'responsible_role' => ErrorCatalog::ROLE_PERFORMER,
        ]);

        $row = $this->row(['ma_bsi_thuc_hien' => 'NV001', 'ma_bac_si' => 'BS001', 'ma_khoa' => 'K01']);

        $placement = (new AssignmentResolver)->resolve($row, null, $catalog);

        $this->assertSame($this->performer->id, $placement['assignee_id']);
        $this->assertSame('ma_bsi_thuc_hien', $placement['resolver']);
        $this->assertSame('assigned', $placement['status']);
    }

    public function test_it_never_falls_back_to_the_orderer_when_the_performer_code_is_missing(): void
    {
        $catalog = ErrorCatalog::create([
            'code' => '9065', 'name' => 'Thời gian thực hiện bằng 0',
            'responsible_role' => ErrorCatalog::ROLE_PERFORMER,
        ]);

        // Với 9065, mã người thực hiện chỉ có ở khoảng một nửa số dòng K01.
        $row = $this->row(['ma_bsi_thuc_hien' => null, 'ma_bac_si' => 'BS001', 'ma_khoa' => 'K01']);

        $placement = (new AssignmentResolver)->resolve($row, null, $catalog);

        $this->assertNull($placement['assignee_id'], 'Không được tự gán người chỉ định làm người thực hiện.');
        $this->assertSame($this->outpatientPool->id, $placement['department_id']);
        $this->assertSame('outpatient_pool', $placement['resolver']);
    }

    public function test_it_uses_the_service_owner_when_the_performer_is_unknown(): void
    {
        ServiceOwner::create(['service_code' => 'DV001', 'department_id' => $this->imaging->id]);

        $catalog = ErrorCatalog::create([
            'code' => '9092', 'name' => 'Thời lượng CĐHA',
            'responsible_role' => ErrorCatalog::ROLE_PERFORMER,
        ]);

        $row = $this->row(['ma_bsi_thuc_hien' => null, 'ma_cp' => 'DV001', 'ma_khoa' => 'K01']);

        $placement = (new AssignmentResolver)->resolve($row, null, $catalog);

        $this->assertNull($placement['assignee_id']);
        $this->assertSame($this->imaging->id, $placement['department_id']);
        $this->assertSame('service_owner', $placement['resolver']);
    }

    public function test_an_ordering_error_goes_to_the_prescriber(): void
    {
        $catalog = ErrorCatalog::create([
            'code' => '3034', 'name' => 'Thuốc chống chỉ định',
            'responsible_role' => ErrorCatalog::ROLE_ORDERER,
        ]);

        $row = $this->row(['ma_bac_si' => 'BS001', 'ma_bsi_thuc_hien' => 'NV001']);

        $placement = (new AssignmentResolver)->resolve($row, null, $catalog);

        $this->assertSame($this->orderer->id, $placement['assignee_id']);
        $this->assertSame('ma_bac_si', $placement['resolver']);
    }

    public function test_a_staff_code_without_an_account_still_reaches_the_managing_department(): void
    {
        StaffDirectory::create([
            'staff_code' => 'NV999', 'user_id' => null,
            'department_id' => $this->imaging->id, 'can_perform' => true,
        ]);

        $catalog = ErrorCatalog::create([
            'code' => '9065', 'name' => 'x', 'responsible_role' => ErrorCatalog::ROLE_PERFORMER,
        ]);

        $placement = (new AssignmentResolver)->resolve(
            $this->row(['ma_bsi_thuc_hien' => 'NV999']),
            null,
            $catalog
        );

        $this->assertNull($placement['assignee_id']);
        $this->assertSame($this->imaging->id, $placement['department_id']);
        $this->assertSame('ma_bsi_thuc_hien:department_only', $placement['resolver']);
    }

    public function test_an_unclassified_error_is_still_routed_somewhere(): void
    {
        $placement = (new AssignmentResolver)->resolve(
            $this->row(['ma_bac_si' => 'BS001']),
            null,
            null
        );

        $this->assertSame($this->orderer->id, $placement['assignee_id']);
    }

    public function test_nothing_known_lands_in_the_outpatient_queue_rather_than_nowhere(): void
    {
        $placement = (new AssignmentResolver)->resolve($this->row([]), null, null);

        $this->assertNull($placement['assignee_id']);
        $this->assertSame($this->outpatientPool->id, $placement['department_id']);
        $this->assertSame('unresolved', $placement['resolver']);
    }

    /** @param array<string, ?string> $attributes */
    private function row(array $attributes): SourceRow
    {
        $row = new SourceRow;

        foreach (['ma_bac_si', 'ma_bsi_thuc_hien', 'ma_cp', 'ma_khoa'] as $field) {
            $row->{$field} = $attributes[$field] ?? null;
        }

        return $row;
    }
}
