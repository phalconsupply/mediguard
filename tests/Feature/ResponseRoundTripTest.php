<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ErrorCatalog;
use App\Models\Explanation;
use App\Models\ImportBatch;
use App\Models\IssueCase;
use App\Models\SourceFile;
use App\Models\SourceRow;
use App\Models\StaffDirectory;
use App\Models\User;
use App\Services\Assignment\IssueCaseBuilder;
use App\Services\Export\ExplanationExporter;
use App\Services\Export\ExportBlockedException;
use App\Services\Import\Readers\XlsxTabularReader;
use App\Services\Import\ResponseFileImporter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Vòng đời đầy đủ của một tệp phản hồi: nạp → sinh vụ việc → giải trình → duyệt → xuất trả.
 *
 * Tệp thử được dựng theo đúng những đặc điểm gây khó trong dữ liệu khảo sát:
 * tiêu đề trùng, tiêu đề có khoảng trắng bao quanh, tiền hai định dạng,
 * dòng "Chấp nhận" và dòng tổng hợp không có ID_CP.
 */
class ResponseRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'ID_CP', 'MA_LK', 'KET_LUAN', 'GHI_CHU', ' TIEN_DE_NGHI_KIEM_TRA ',
        'MA_KHOA', 'MA_BAC_SI', 'MA_BSI_THUC_HIEN', 'MA_CP', 'NGAY_YL', 'NGAY_YL',
    ];

    private const ROWS = [
        // Dòng bị từ chối, có người thực hiện.
        ['1001', 'LK1', 'Từ chối', 'VI PHẠM [Thời gian thực hiện bằng 0]', '502.549,00', 'K02', 'BS001', 'NV001', 'DV001', '2026-07-03 11:15:00', '2026-07-03 11:15'],
        // Dòng bị từ chối, thiếu mã người thực hiện.
        ['1002', 'LK2', 'Từ chối', 'VI PHẠM [Thời gian thực hiện bằng 0]', '120000', 'K01', 'BS001', '', 'DV001', '2026-07-03 12:00:00', ''],
        // Dòng được BHYT chấp nhận: không chặn tiến độ, không cần giải trình.
        ['1003', 'LK3', 'Chấp nhận', '', '90000', 'K02', 'BS001', 'NV001', 'DV002', '2026-07-03 13:00:00', ''],
        // Dòng cảnh báo cũng thuộc diện phản hồi.
        ['1004', 'LK4', 'Cảnh báo', 'Cần xem lại chỉ định', '45000', 'K02', 'BS001', 'NV001', 'DV003', '2026-07-03 14:00:00', ''],
        // Dòng tổng hợp cuối tệp: không có ID_CP, phải giữ nguyên khi xuất lại.
        ['', '', '', 'Tổng cộng', '757549', '', '', '', '', '', ''],
    ];

    private string $workbookPath;

    private ImportBatch $batch;

    private User $manager;

    private User $performer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('mediguard.storage.disk', 'local');

        $imaging = Department::create(['code' => 'K02', 'name' => 'Chẩn đoán hình ảnh', 'kind' => 'paraclinical']);
        Department::create(['code' => 'K01', 'name' => 'Khám ngoại trú', 'kind' => 'outpatient', 'is_outpatient_pool' => true]);

        $this->manager = User::create([
            'name' => 'Quản lý BHYT', 'email' => 'ql@example.test', 'password' => 'secret-long-password',
            'role' => User::ROLE_BHYT_MANAGER,
        ]);
        $this->performer = User::create([
            'name' => 'Kỹ thuật viên', 'email' => 'ktv@example.test', 'password' => 'secret-long-password',
            'role' => User::ROLE_STAFF, 'department_id' => $imaging->id,
        ]);

        StaffDirectory::create([
            'staff_code' => 'NV001', 'user_id' => $this->performer->id,
            'department_id' => $imaging->id, 'can_order' => false, 'can_perform' => true,
        ]);

        ErrorCatalog::create([
            'code' => '9065', 'name' => 'Thời gian thực hiện DVKT bằng 0',
            'group' => 'dvkt', 'responsible_role' => ErrorCatalog::ROLE_PERFORMER,
        ]);

        $this->batch = ImportBatch::create([
            'code' => 'T1', 'kind' => ImportBatch::KIND_RESPONSE, 'period' => '202607',
            'facility_code' => '68001', 'title' => 'Đợt thử', 'status' => 'ingesting',
        ]);

        $this->workbookPath = $this->buildWorkbook();
    }

    protected function tearDown(): void
    {
        @unlink($this->workbookPath);
        parent::tearDown();
    }

    public function test_it_preserves_every_source_cell_and_classifies_each_row(): void
    {
        $file = $this->ingest();
        $sheet = $file->dataSheet();

        $this->assertSame(11, $sheet->column_count);
        $this->assertSame(5, $sheet->row_count);

        $rows = SourceRow::where('source_sheet_id', $sheet->id)->orderBy('row_number')->get();

        // Mảng ô giữ đủ 11 giá trị theo đúng thứ tự cột gốc.
        $this->assertSame(self::ROWS[0], $rows[0]->cells);

        // Số tiền hai định dạng đều đọc được về cùng một giá trị.
        $this->assertSame('502549.00', $rows[0]->tien_de_nghi_kiem_tra);
        $this->assertSame('120000.00', $rows[1]->tien_de_nghi_kiem_tra);

        // Dòng tổng hợp được nhận ra vì không có ID_CP.
        $this->assertSame(SourceRow::TYPE_SUMMARY, $rows[4]->row_type);
        $this->assertSame(SourceRow::TYPE_COST, $rows[0]->row_type);
    }

    public function test_duplicate_headers_keep_their_own_position(): void
    {
        $file = $this->ingest();
        $columns = $file->dataSheet()->columns;

        $this->assertSame('NGAY_YL#1', $columns[9]->internal_code);
        $this->assertSame('NGAY_YL#2', $columns[10]->internal_code);
        $this->assertStringContainsString('cột 11', $columns[10]->label());

        // Tiêu đề có khoảng trắng bao quanh vẫn giữ nguyên bản gốc để xuất lại.
        $this->assertSame(' TIEN_DE_NGHI_KIEM_TRA ', $columns[4]->raw_header);
        $this->assertSame('TIEN_DE_NGHI_KIEM_TRA', $columns[4]->normalized_header);
    }

    public function test_only_rejected_and_warned_rows_require_an_explanation(): void
    {
        $file = $this->ingest();

        $this->assertSame(5, IssueCase::where('source_file_id', $file->id)->count());
        $this->assertSame(2, IssueCase::where('source_file_id', $file->id)->where('requires_explanation', true)
            ->whereIn('conclusion', ['Từ chối'])->count());
        $this->assertSame(3, IssueCase::where('source_file_id', $file->id)->where('requires_explanation', true)->count());

        // Dòng "Chấp nhận" và dòng tổng hợp không chặn tiến độ.
        $this->assertFalse(IssueCase::where('source_file_id', $file->id)->where('conclusion', 'Chấp nhận')->first()->requires_explanation);
    }

    public function test_the_row_with_a_performer_is_assigned_and_the_one_without_waits_in_the_queue(): void
    {
        $file = $this->ingest();

        $assigned = IssueCase::where('source_file_id', $file->id)
            ->whereHas('sourceRow', fn ($q) => $q->where('id_cp', '1001'))->first();
        $queued = IssueCase::where('source_file_id', $file->id)
            ->whereHas('sourceRow', fn ($q) => $q->where('id_cp', '1002'))->first();

        $this->assertSame($this->performer->id, $assigned->assignee_id);
        $this->assertSame(IssueCase::STATUS_ASSIGNED, $assigned->status);

        $this->assertNull($queued->assignee_id);
        $this->assertSame(IssueCase::STATUS_UNASSIGNED, $queued->status);
    }

    public function test_reloading_the_same_file_does_not_create_duplicate_work(): void
    {
        $file = $this->ingest();
        $before = IssueCase::where('import_batch_id', $this->batch->id)->count();

        // Nạp lại cùng hash vào cùng đợt bị chặn ở tầng dữ liệu.
        $this->expectException(QueryException::class);

        try {
            SourceFile::create([
                'import_batch_id' => $this->batch->id,
                'original_name' => 'lan_hai.xlsx',
                'stored_path' => $file->stored_path,
                'sha256' => $file->sha256,
                'size_bytes' => $file->size_bytes,
                'format' => 'xlsx',
                'error_code' => '9065',
                'status' => 'received',
            ]);
        } finally {
            $this->assertSame($before, IssueCase::where('import_batch_id', $this->batch->id)->count());
        }
    }

    public function test_export_is_blocked_until_every_required_row_is_approved(): void
    {
        $file = $this->ingest();

        $this->expectException(ExportBlockedException::class);
        app(ExplanationExporter::class)->export($file, $this->manager->id);
    }

    public function test_the_exported_file_matches_the_source_with_one_extra_column(): void
    {
        $file = $this->ingest();
        $this->approveAll($file);

        $artifact = app(ExplanationExporter::class)->export($file, $this->manager->id);

        $this->assertSame([], $artifact->verification['blocking']);
        $this->assertSame(5, $artifact->verification['checks']['exported_rows']);
        $this->assertSame(12, $artifact->verification['checks']['exported_columns']);
        $this->assertSame(3, $artifact->verification['checks']['explanations_written']);

        $exported = Storage::disk('local')->path($artifact->stored_path);
        $rows = iterator_to_array((new XlsxTabularReader($exported))->rows(0));

        // Dòng tiêu đề: đủ cột gốc, thêm đúng một cột ở cuối.
        $this->assertSame([...self::HEADERS, ExplanationExporter::COLUMN_HEADER], $rows[1]);

        // Giá trị nguồn của từng dòng không đổi.
        foreach (self::ROWS as $index => $sourceRow) {
            $this->assertSame($sourceRow, array_slice($rows[$index + 2], 0, 11));
        }

        // Dòng "Chấp nhận" và dòng tổng hợp để trống theo quy tắc xuất.
        $this->assertSame('', $rows[4][11] ?? '');
        $this->assertSame('', $rows[6][11] ?? '');

        // Dòng bị từ chối mang đúng nội dung đã duyệt.
        $this->assertSame('Giải trình cho dòng 1001', $rows[2][11]);
    }

    public function test_an_explanation_starting_with_a_formula_character_is_neutralised(): void
    {
        $file = $this->ingest();
        $this->approveAll($file, body: fn (IssueCase $case) => '=1+1 nội dung giải trình');

        $artifact = app(ExplanationExporter::class)->export($file, $this->manager->id);
        $exported = Storage::disk('local')->path($artifact->stored_path);
        $rows = iterator_to_array((new XlsxTabularReader($exported))->rows(0));

        $this->assertSame("'=1+1 nội dung giải trình", $rows[2][11]);
    }

    public function test_approving_a_second_explanation_supersedes_the_first(): void
    {
        $file = $this->ingest();
        $this->approveAll($file);

        $case = IssueCase::where('source_file_id', $file->id)->where('requires_explanation', true)->first();

        $second = Explanation::create([
            'issue_case_id' => $case->id,
            'version' => 2,
            'body' => 'Bản thay thế',
            'status' => Explanation::STATUS_SUBMITTED,
            'author_id' => $this->performer->id,
        ]);

        $this->actingAs($this->manager)
            ->post(route('explanations.approve', $second))
            ->assertRedirect();

        $this->assertSame(
            1,
            $case->explanations()->where('status', Explanation::STATUS_APPROVED)->count(),
            'Mỗi vụ việc chỉ được giữ đúng một bản giải trình đã duyệt.'
        );
    }

    private function ingest(): SourceFile
    {
        $relative = 'mediguard/batches/'.$this->batch->id.'/test.xlsx';
        Storage::disk('local')->put($relative, file_get_contents($this->workbookPath));

        $file = SourceFile::create([
            'import_batch_id' => $this->batch->id,
            'original_name' => 'TACH_9065_TEST_68001.xlsx',
            'stored_path' => $relative,
            'sha256' => hash_file('sha256', $this->workbookPath),
            'size_bytes' => filesize($this->workbookPath) ?: 0,
            'format' => 'xlsx',
            'error_code' => '9065',
            'status' => 'reading',
        ]);

        app(ResponseFileImporter::class)->import($file, Storage::disk('local')->path($relative));
        app(IssueCaseBuilder::class)->build($file, $this->manager->id);

        return $file->refresh();
    }

    private function approveAll(SourceFile $file, ?callable $body = null): void
    {
        $cases = IssueCase::where('source_file_id', $file->id)->where('requires_explanation', true)->get();

        foreach ($cases as $case) {
            Explanation::create([
                'issue_case_id' => $case->id,
                'version' => 1,
                'body' => $body ? $body($case) : 'Giải trình cho dòng '.$case->sourceRow->id_cp,
                'resolution' => 'de_nghi_chap_nhan',
                'status' => Explanation::STATUS_APPROVED,
                'author_id' => $this->performer->id,
                'approver_id' => $this->manager->id,
                'approved_at' => now(),
            ]);

            $case->update(['status' => IssueCase::STATUS_APPROVED]);
        }
    }

    private function buildWorkbook(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mg_rt_').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('DuLieuGoc');
        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach (self::ROWS as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }
}
