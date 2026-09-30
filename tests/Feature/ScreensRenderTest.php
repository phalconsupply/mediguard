<?php

namespace Tests\Feature;

use App\Models\CheckRule;
use App\Models\Department;
use App\Models\ErrorCatalog;
use App\Models\ImportBatch;
use App\Models\PrecheckRun;
use App\Models\User;
use Database\Seeders\CheckRuleSeeder;
use Database\Seeders\ErrorCatalogSeeder;
use Database\Seeders\MappingProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mọi màn hình phải dựng được với dữ liệu trống và với dữ liệu đã seed.
 * Đây là lưới an toàn cho các lỗi chỉ lộ ra khi render giao diện.
 */
class ScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ErrorCatalogSeeder::class, MappingProfileSeeder::class, CheckRuleSeeder::class]);

        $this->manager = User::factory()->manager()->create(['email' => 'ql@example.test']);

        Department::create(['code' => 'K01', 'name' => 'Khám ngoại trú', 'kind' => 'outpatient', 'is_outpatient_pool' => true]);
    }

    public function test_the_login_page_renders_for_a_guest(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Đăng nhập');
    }

    public function test_every_management_screen_renders(): void
    {
        $responseBatch = ImportBatch::create([
            'code' => 'R1', 'kind' => ImportBatch::KIND_RESPONSE, 'period' => '202607',
            'title' => 'Đợt phản hồi', 'status' => 'ingesting',
        ]);

        $precheckBatch = ImportBatch::create([
            'code' => 'P1', 'kind' => ImportBatch::KIND_PRECHECK, 'period' => '202607',
            'title' => 'Đợt tự kiểm tra', 'status' => 'ingesting',
        ]);

        $run = PrecheckRun::create([
            'import_batch_id' => $precheckBatch->id,
            'status' => 'completed',
            'ruleset_snapshot' => [],
            'input_snapshot' => [],
            'coverage' => ['R-TEST' => ['checked' => 10, 'detected' => 1, 'insufficient' => 2, 'out_of_scope' => 3]],
        ]);

        $catalog = ErrorCatalog::where('code', '9065')->firstOrFail();
        $rule = CheckRule::firstOrFail();

        $urls = [
            route('dashboard'),
            route('batches.index'),
            route('batches.create'),
            route('batches.show', $responseBatch),
            route('batches.show', $precheckBatch),
            route('exports.index', $responseBatch),
            route('cases.index'),
            route('catalog.index'),
            route('catalog.show', $catalog),
            route('rules.index'),
            route('rules.create'),
            route('rules.show', $rule),
            route('precheck.index'),
            route('precheck.create', $precheckBatch),
            route('precheck.show', $run),
            route('presets.index'),
            route('directory.index'),
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($this->manager)->get($url);
            $this->assertSame(200, $response->getStatusCode(), "Màn hình {$url} không dựng được.");
        }
    }

    public function test_the_case_list_renders_before_any_file_is_loaded(): void
    {
        $this->actingAs($this->manager)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee('Không có công việc nào khớp bộ lọc.');
    }

    public function test_a_viewer_does_not_get_the_management_menu(): void
    {
        $viewer = User::factory()->viewer()->create(['email' => 'xem@example.test']);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('directory.index'));
    }
}
