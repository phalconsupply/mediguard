<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Luồng tự kiểm tra trước khi gửi giám định.
 *
 * XML GIAMDINHHS, XLSX và CSV của HIS được đưa về cùng mô hình chuẩn
 * (encounters + details) để dùng chung một bộ quy tắc.
 * Vị trí bản ghi trong nguồn luôn được giữ để người xử lý tìm lại đúng dòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapping_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name');
            $table->string('format', 16);               // xml | xlsx | csv
            $table->unsignedInteger('version')->default(1);
            $table->json('definition');                 // ánh xạ cột/nhánh -> trường chuẩn
            $table->json('reader_options')->nullable(); // mã hóa, dấu phân cách, dòng tiêu đề, định dạng số/ngày
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        Schema::create('precheck_encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_file_id')->constrained()->cascadeOnDelete();
            $table->string('ma_lk', 64);
            $table->string('ma_bn', 64)->nullable();
            $table->string('ma_the', 64)->nullable();
            $table->string('ma_khoa', 32)->nullable();
            $table->string('ma_benh_chinh', 32)->nullable();
            $table->string('ma_benh_kt', 255)->nullable();
            $table->string('ma_loai_kcb', 16)->nullable();
            $table->dateTime('ngay_vao')->nullable();
            $table->dateTime('ngay_ra')->nullable();
            $table->decimal('t_tongchi', 18, 2)->nullable();
            $table->decimal('t_bhtt', 18, 2)->nullable();
            $table->json('locator');                    // hoso_index, loai_ho_so, stt / sheet+row / csv line
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['source_file_id', 'ma_lk']);
            $table->index(['import_batch_id', 'ma_lk']);
        });

        Schema::create('precheck_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('precheck_encounter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ma_lk', 64);
            $table->string('detail_kind', 16);          // thuoc | dvkt | cls | dien_bien | khac
            $table->string('loai_ho_so', 16)->nullable(); // XML2..XML15
            $table->unsignedInteger('stt')->nullable();
            $table->string('ma_dich_vu', 64)->nullable();
            $table->string('ma_vat_tu', 64)->nullable();
            $table->string('ma_thuoc', 64)->nullable();
            $table->string('ten', 512)->nullable();
            $table->string('ma_chi_so', 64)->nullable();
            $table->string('gia_tri', 255)->nullable();
            $table->string('ma_bac_si', 64)->nullable();
            $table->string('nguoi_thuc_hien', 255)->nullable();
            $table->string('ma_may', 64)->nullable();
            $table->string('ma_khoa', 32)->nullable();
            $table->decimal('so_luong', 18, 4)->nullable();
            $table->decimal('thanh_tien', 18, 2)->nullable();
            $table->dateTime('ngay_yl')->nullable();
            $table->dateTime('ngay_th_yl')->nullable();
            $table->dateTime('ngay_kq')->nullable();
            $table->unsignedTinyInteger('time_precision')->default(0); // 0 không rõ, 1 phút, 2 giây
            $table->json('locator');
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'ma_lk', 'detail_kind']);
            $table->index(['import_batch_id', 'detail_kind', 'ma_dich_vu']);
            $table->index(['import_batch_id', 'nguoi_thuc_hien']);
        });

        Schema::create('precheck_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('queued'); // queued|running|completed|failed
            $table->json('ruleset_snapshot');                // id + version của từng quy tắc đã chạy
            $table->json('input_snapshot');                  // hash tệp, định dạng, mapping profile
            $table->unsignedBigInteger('encounters_total')->default(0);
            $table->unsignedBigInteger('details_total')->default(0);
            $table->unsignedBigInteger('detected_count')->default(0);
            $table->unsignedBigInteger('insufficient_count')->default(0);
            $table->json('coverage')->nullable();            // độ phủ theo từng quy tắc
            $table->text('failure_reason')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('precheck_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_rule_version_id')->constrained()->cascadeOnDelete();
            $table->string('rule_code', 64);
            $table->string('result', 32);               // detected | not_detected | insufficient_data
            $table->string('ma_lk', 64)->nullable();
            $table->foreignId('precheck_detail_id')->nullable()->constrained()->nullOnDelete();
            $table->json('locator')->nullable();
            $table->json('values')->nullable();          // giá trị gây cảnh báo
            $table->string('reason', 1024)->nullable();
            $table->json('missing_fields')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('handling_status', 32)->default('open'); // open|fixed|accepted_risk|not_applicable
            $table->string('handling_note', 1024)->nullable();
            $table->timestamps();

            $table->index(['precheck_run_id', 'result']);
            $table->index(['precheck_run_id', 'rule_code']);
        });

        // Đối chiếu phát hiện tự kiểm tra với phản hồi BHYT kỳ sau.
        Schema::create('precheck_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('precheck_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('response_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issue_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 32);              // true_positive|false_positive|missed|not_checkable|ambiguous
            $table->string('note', 1024)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['precheck_run_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precheck_reconciliations');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('precheck_runs');
        Schema::dropIfExists('precheck_details');
        Schema::dropIfExists('precheck_encounters');
        Schema::dropIfExists('mapping_profiles');
    }
};
