<?php

namespace Tests\Feature;

use App\Models\CheckRule;
use App\Models\CheckRuleVersion;
use App\Models\Finding;
use App\Models\ImportBatch;
use App\Models\MappingProfile;
use App\Models\PrecheckDetail;
use App\Models\PrecheckEncounter;
use App\Models\PrecheckRun;
use App\Models\SourceFile;
use App\Services\Precheck\XmlPrecheckImporter;
use App\Services\Rules\PrecheckRunner;
use Database\Seeders\MappingProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Luồng tự kiểm tra: nạp XML gửi giám định vào mô hình chuẩn rồi chạy quy tắc đã duyệt.
 */
class PrecheckRunTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = __DIR__.'/../Fixtures/giamdinhhs_nested.xml';

    private ImportBatch $batch;

    private SourceFile $file;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('mediguard.storage.disk', 'local');

        $this->seed(MappingProfileSeeder::class);

        $this->batch = ImportBatch::create([
            'code' => 'P1', 'kind' => ImportBatch::KIND_PRECHECK, 'period' => '202607',
            'title' => 'Đợt tự kiểm tra thử', 'status' => 'ingesting',
        ]);

        $relative = 'mediguard/batches/'.$this->batch->id.'/giamdinhhs.xml';
        Storage::disk('local')->put($relative, file_get_contents(self::FIXTURE));

        $this->file = SourceFile::create([
            'import_batch_id' => $this->batch->id,
            'original_name' => 'giamdinhhs.xml',
            'stored_path' => $relative,
            'sha256' => hash_file('sha256', self::FIXTURE),
            'size_bytes' => filesize(self::FIXTURE) ?: 0,
            'format' => 'xml',
            'status' => 'reading',
        ]);
    }

    public function test_it_maps_the_xml_into_the_canonical_model(): void
    {
        $result = $this->import();

        $this->assertSame(2, $result['encounters']);
        $this->assertSame(6, $result['details']);
        $this->assertSame([], $result['issues']);

        $encounter = PrecheckEncounter::where('ma_lk', 'TEST0001')->firstOrFail();
        $this->assertSame('K01', $encounter->ma_khoa);
        $this->assertSame('J44.1', $encounter->ma_benh_chinh);
        $this->assertSame('2026-07-03 08:00:00', $encounter->ngay_vao->format('Y-m-d H:i:s'));
        $this->assertSame(1, $encounter->locator['hoso_index']);
    }

    public function test_each_detail_keeps_its_place_in_the_source(): void
    {
        $this->import();

        $detail = PrecheckDetail::where('ma_lk', 'TEST0001')
            ->where('detail_kind', PrecheckDetail::KIND_DVKT)
            ->where('stt', 1)
            ->firstOrFail();

        $this->assertSame('XML3', $detail->locator['loai_ho_so']);
        $this->assertSame('1', $detail->locator['stt']);
        $this->assertSame(1, $detail->locator['hoso_index']);
        $this->assertSame('NV001', $detail->nguoi_thuc_hien);
        $this->assertSame('MAY01', $detail->ma_may);

        // Mốc trong XML có dạng YYYYMMDDHHmm nên độ chính xác là phút, không phải giây.
        $this->assertSame(2, $detail->time_precision);
    }

    public function test_a_row_using_the_material_code_instead_of_the_service_code_is_kept(): void
    {
        $this->import();

        $detail = PrecheckDetail::where('ma_lk', 'TEST0001')
            ->where('detail_kind', PrecheckDetail::KIND_DVKT)
            ->where('stt', 2)
            ->firstOrFail();

        $this->assertNull($detail->ma_dich_vu);
        $this->assertSame('VT001', $detail->ma_vat_tu);
    }

    public function test_unmapped_elements_are_preserved_for_later_rules(): void
    {
        $this->import();

        $encounter = PrecheckEncounter::where('ma_lk', 'TEST0001')->firstOrFail();

        $this->assertSame('BN0001', $encounter->ma_bn);
        $this->assertArrayHasKey('HO_TEN', $encounter->extra);
    }

    public function test_a_run_separates_the_three_result_states(): void
    {
        $this->import();

        $version = $this->activeRule(
            code: 'R-TEST-KQ-TRUNG-PHUT',
            definition: [
                'subject' => 'detail',
                'filter' => ['all' => [['field' => 'detail_kind', 'op' => 'equals', 'value' => 'dvkt']]],
                'condition' => ['any' => [['field' => 'ngay_kq', 'op' => 'same_minute_as_field', 'value' => 'ngay_th_yl']]],
                'message' => 'Thời lượng thực hiện bằng 0.',
            ],
            required: ['ngay_th_yl', 'ngay_kq'],
        );

        $run = $this->executeRun($version);

        // Ba dòng DVKT: một trùng phút (phát hiện), một đủ dữ liệu và hợp lệ,
        // một thiếu cả hai mốc (không đủ dữ liệu).
        $this->assertSame(1, $run->detected_count);
        $this->assertSame(1, $run->insufficient_count);

        $coverage = $run->coverage['R-TEST-KQ-TRUNG-PHUT'];
        $this->assertSame(3, $coverage['checked']);
        $this->assertSame(1, $coverage['detected']);
        $this->assertSame(1, $coverage['insufficient']);
        // Thuốc và cận lâm sàng nằm ngoài phạm vi, không tính vào độ phủ.
        $this->assertSame(3, $coverage['out_of_scope']);

        $insufficient = Finding::where('result', Finding::RESULT_INSUFFICIENT)->firstOrFail();
        $this->assertSame(['ngay_th_yl', 'ngay_kq'], $insufficient->missing_fields);
    }

    public function test_the_run_records_the_rule_version_it_used(): void
    {
        $this->import();

        $version = $this->activeRule(
            code: 'R-TEST-THIEU-NGUOI-TH',
            definition: [
                'subject' => 'detail',
                'filter' => ['all' => [['field' => 'detail_kind', 'op' => 'equals', 'value' => 'dvkt']]],
                'condition' => ['all' => [['field' => 'nguoi_thuc_hien', 'op' => 'is_empty']]],
                'message' => 'Dịch vụ kỹ thuật không ghi người thực hiện.',
            ],
            required: [],
        );

        $run = $this->executeRun($version);

        $finding = Finding::where('result', Finding::RESULT_DETECTED)->firstOrFail();
        $this->assertSame($version->id, $finding->check_rule_version_id);
        $this->assertSame('R-TEST-THIEU-NGUOI-TH', $finding->rule_code);
        $this->assertSame(1, $run->ruleset_snapshot[0]['version']);

        // Thư viện đổi sau đó không làm đổi kết quả đã lưu.
        $version->update(['definition' => ['subject' => 'detail', 'condition' => ['any' => []]]]);
        $this->assertSame('R-TEST-THIEU-NGUOI-TH', $finding->refresh()->rule_code);
    }

    /** @return array<string, mixed> */
    private function import(): array
    {
        return app(XmlPrecheckImporter::class)->import(
            $this->file,
            Storage::disk('local')->path($this->file->stored_path),
            MappingProfile::where('code', 'GIAMDINHHS_XML')->firstOrFail(),
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $required
     */
    private function activeRule(string $code, array $definition, array $required): CheckRuleVersion
    {
        $rule = CheckRule::create(['code' => $code, 'name' => $code, 'scope' => 'all']);

        return CheckRuleVersion::create([
            'check_rule_id' => $rule->id,
            'version' => 1,
            'definition' => $definition,
            'required_fields' => $required,
            'severity' => 'warning',
            'status' => CheckRuleVersion::STATUS_ACTIVE,
            'test_cases' => ['pass' => 1, 'warn' => 1, 'missing' => 1],
        ]);
    }

    private function executeRun(CheckRuleVersion $version): PrecheckRun
    {
        $run = PrecheckRun::create([
            'import_batch_id' => $this->batch->id,
            'status' => 'queued',
            'ruleset_snapshot' => [[
                'rule_code' => $version->rule->code,
                'rule_version_id' => $version->id,
                'version' => $version->version,
                'severity' => $version->severity,
            ]],
            'input_snapshot' => [['file' => $this->file->original_name, 'sha256' => $this->file->sha256]],
        ]);

        return app(PrecheckRunner::class)->run($run);
    }
}
