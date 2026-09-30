<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đợt nhập, tệp nguồn và dòng nguồn.
 *
 * Khóa bất biến của một dòng phản hồi BHYT là source_sheet_id + row_number.
 * ID_CP / MA_LK chỉ là khóa tra cứu vì một ID_CP có thể nằm ở nhiều tệp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('kind', 32);                 // response | precheck
            $table->string('period', 16)->nullable();   // KY_QT, ví dụ 202607
            $table->string('facility_code', 16)->nullable();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('draft'); // draft|ingesting|mapped|assigned|in_progress|exported|closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();

            $table->index(['kind', 'period']);
        });

        Schema::create('source_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('format', 16);               // xlsx | csv | xml
            $table->string('error_code', 32)->nullable();   // TACH_#### lấy từ tên tệp
            $table->string('status', 32)->default('received'); // received|reading|ready|failed
            $table->text('failure_reason')->nullable();
            $table->json('detected')->nullable();       // thông tin nhận diện: sheet, dòng tiêu đề, biến thể
            $table->timestamp('ingested_at')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'sha256']);
            $table->index('error_code');
        });

        Schema::create('source_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sheet_index')->default(0);
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('header_row_number')->default(1);
            $table->unsignedInteger('column_count')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->boolean('is_data_sheet')->default(false);
            $table->string('column_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->unique(['source_file_id', 'sheet_index']);
        });

        // Giữ nguyên tiêu đề gốc và vị trí cột. Tiêu đề trùng được phân biệt bằng `occurrence`.
        Schema::create('source_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_sheet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');        // 0-based, đúng thứ tự trong tệp gốc
            $table->string('raw_header', 512)->nullable();
            $table->string('normalized_header', 255)->nullable();
            $table->unsignedInteger('occurrence')->default(1);
            $table->string('internal_code', 320)->nullable(); // normalized_header#occurrence
            $table->timestamps();

            $table->unique(['source_sheet_id', 'position']);
            $table->index(['source_sheet_id', 'normalized_header']);
        });

        Schema::create('source_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');      // số dòng trong tệp gốc, 1-based
            $table->string('row_type', 16)->default('cost'); // cost | summary | blank
            $table->json('cells');                      // mảng giá trị ô theo đúng thứ tự cột
            $table->string('id_cp', 64)->nullable();
            $table->string('ma_lk', 64)->nullable();
            $table->string('ma_khoa', 32)->nullable();
            $table->string('ma_bac_si', 64)->nullable();
            $table->string('ma_bsi_thuc_hien', 64)->nullable();
            $table->string('ma_cp', 64)->nullable();
            $table->string('loai_cp', 32)->nullable();
            $table->string('ket_luan', 64)->nullable();
            $table->text('ghi_chu')->nullable();
            $table->decimal('tien_de_nghi_kiem_tra', 18, 2)->nullable();
            $table->decimal('thanh_tien_bh', 18, 2)->nullable();
            $table->decimal('t_bhtt_dtl', 18, 2)->nullable();
            $table->timestamps();

            $table->unique(['source_sheet_id', 'row_number']);
            $table->index(['import_batch_id', 'id_cp']);
            $table->index(['import_batch_id', 'ma_lk']);
            $table->index(['import_batch_id', 'ma_khoa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_rows');
        Schema::dropIfExists('source_columns');
        Schema::dropIfExists('source_sheets');
        Schema::dropIfExists('source_files');
        Schema::dropIfExists('import_batches');
    }
};
