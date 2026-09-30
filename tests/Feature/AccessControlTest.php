<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Explanation;
use App\Models\ImportBatch;
use App\Models\IssueCase;
use App\Models\SourceFile;
use App\Models\SourceRow;
use App\Models\SourceSheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phân quyền phải chặn ở truy vấn và ở hành động, không chỉ ẩn nút trên giao diện.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Department $imaging;

    private Department $lab;

    private User $manager;

    private User $imagingStaff;

    private User $labStaff;

    private User $imagingHead;

    private IssueCase $imagingCase;

    private IssueCase $labCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imaging = Department::create(['code' => 'K02', 'name' => 'CĐHA', 'kind' => 'paraclinical']);
        $this->lab = Department::create(['code' => 'K05', 'name' => 'Xét nghiệm', 'kind' => 'paraclinical']);

        $this->manager = $this->user('ql@example.test', User::ROLE_BHYT_MANAGER, null);
        $this->imagingStaff = $this->user('cdha@example.test', User::ROLE_STAFF, $this->imaging->id);
        $this->labStaff = $this->user('xn@example.test', User::ROLE_STAFF, $this->lab->id);
        $this->imagingHead = $this->user('truongkhoa@example.test', User::ROLE_DEPT_HEAD, $this->imaging->id);

        $batch = ImportBatch::create([
            'code' => 'B1', 'kind' => ImportBatch::KIND_RESPONSE, 'period' => '202607',
            'title' => 'Đợt thử', 'status' => 'assigned',
        ]);

        $this->imagingCase = $this->case($batch, $this->imaging->id, $this->imagingStaff->id, 1);
        $this->labCase = $this->case($batch, $this->lab->id, $this->labStaff->id, 2);
    }

    public function test_a_manager_sees_every_case_in_the_batch(): void
    {
        $visible = IssueCase::query()->visibleTo($this->manager)->pluck('id');

        $this->assertEqualsCanonicalizing(
            [$this->imagingCase->id, $this->labCase->id],
            $visible->all()
        );
    }

    public function test_staff_only_see_their_own_department(): void
    {
        $visible = IssueCase::query()->visibleTo($this->labStaff)->pluck('id');

        $this->assertSame([$this->labCase->id], $visible->all());
    }

    public function test_opening_another_departments_case_is_refused(): void
    {
        $this->actingAs($this->labStaff)
            ->get(route('cases.show', $this->imagingCase))
            ->assertForbidden();
    }

    public function test_writing_an_explanation_for_another_departments_case_is_refused(): void
    {
        $this->actingAs($this->labStaff)
            ->post(route('explanations.store', $this->imagingCase), [
                'body' => 'Nội dung giải trình thử nghiệm đủ dài.',
                'resolution' => 'de_nghi_chap_nhan',
            ])
            ->assertForbidden();
    }

    public function test_the_author_cannot_approve_their_own_explanation(): void
    {
        $explanation = Explanation::create([
            'issue_case_id' => $this->imagingCase->id,
            'version' => 1,
            'body' => 'Nội dung giải trình của trưởng khoa.',
            'status' => Explanation::STATUS_SUBMITTED,
            'author_id' => $this->imagingHead->id,
        ]);

        $this->actingAs($this->imagingHead)
            ->post(route('explanations.approve', $explanation))
            ->assertSessionHasErrors('approve');

        $this->assertSame(Explanation::STATUS_SUBMITTED, $explanation->refresh()->status);
    }

    public function test_a_head_of_department_approves_within_their_own_department(): void
    {
        $explanation = Explanation::create([
            'issue_case_id' => $this->imagingCase->id,
            'version' => 1,
            'body' => 'Nội dung giải trình của nhân viên.',
            'status' => Explanation::STATUS_SUBMITTED,
            'author_id' => $this->imagingStaff->id,
        ]);

        $this->actingAs($this->imagingHead)
            ->post(route('explanations.approve', $explanation))
            ->assertRedirect();

        $this->assertSame(Explanation::STATUS_APPROVED, $explanation->refresh()->status);
        $this->assertSame(IssueCase::STATUS_APPROVED, $this->imagingCase->refresh()->status);
    }

    public function test_a_closed_period_stops_further_explanation(): void
    {
        $this->imagingCase->batch->update(['closed_at' => now()]);

        $this->actingAs($this->imagingStaff)
            ->post(route('explanations.store', $this->imagingCase), [
                'body' => 'Nội dung giải trình thử nghiệm đủ dài.',
                'resolution' => 'de_nghi_chap_nhan',
            ])
            ->assertForbidden();
    }

    public function test_only_managers_reach_the_export_screen(): void
    {
        $batch = $this->imagingCase->batch;

        $this->actingAs($this->imagingStaff)->get(route('exports.index', $batch))->assertForbidden();
        $this->actingAs($this->manager)->get(route('exports.index', $batch))->assertOk();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('cases.index'))->assertRedirect(route('login'));
    }

    private function user(string $email, string $role, ?int $departmentId): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'secret-long-password',
            'role' => $role,
            'department_id' => $departmentId,
        ]);
    }

    private function case(ImportBatch $batch, int $departmentId, int $assigneeId, int $rowNumber): IssueCase
    {
        $file = SourceFile::firstOrCreate(
            ['import_batch_id' => $batch->id, 'sha256' => str_repeat('a', 64)],
            [
                'original_name' => 'TACH_9065_TEST.xlsx',
                'stored_path' => 'mediguard/test.xlsx',
                'size_bytes' => 1,
                'format' => 'xlsx',
                'error_code' => '9065',
                'status' => 'ready',
            ]
        );

        $sheet = SourceSheet::firstOrCreate(
            ['source_file_id' => $file->id, 'sheet_index' => 0],
            ['sheet_name' => 'DuLieuGoc', 'header_row_number' => 1, 'column_count' => 2, 'row_count' => 2, 'is_data_sheet' => true]
        );

        $row = SourceRow::create([
            'source_sheet_id' => $sheet->id,
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber + 1,
            'row_type' => SourceRow::TYPE_COST,
            'cells' => ['100'.$rowNumber, 'Từ chối'],
            'id_cp' => '100'.$rowNumber,
            'ket_luan' => 'Từ chối',
        ]);

        return IssueCase::create([
            'import_batch_id' => $batch->id,
            'source_file_id' => $file->id,
            'source_row_id' => $row->id,
            'error_code' => '9065',
            'conclusion' => 'Từ chối',
            'requires_explanation' => true,
            'status' => IssueCase::STATUS_ASSIGNED,
            'assignee_id' => $assigneeId,
            'department_id' => $departmentId,
        ]);
    }
}
