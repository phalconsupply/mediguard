<?php

namespace App\Http\Controllers;

use App\Jobs\IngestPrecheckFile;
use App\Jobs\IngestResponseFile;
use App\Models\ImportBatch;
use App\Models\MappingProfile;
use App\Models\SourceFile;
use App\Services\Import\DuplicateSourceFileException;
use App\Services\Import\Readers\CsvTabularReader;
use App\Services\Import\Readers\XlsxTabularReader;
use App\Services\Import\SheetInspector;
use App\Services\Import\SourceFileStore;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    public function __construct(
        private readonly SourceFileStore $store,
        private readonly SheetInspector $inspector,
    ) {}

    public function index(): View
    {
        $batches = ImportBatch::query()
            ->withCount(['files', 'issueCases'])
            ->latest('id')
            ->paginate(20);

        return view('batches.index', compact('batches'));
    }

    public function create(): View
    {
        return view('batches.create', [
            'profiles' => MappingProfile::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:response,precheck'],
            'period' => ['nullable', 'regex:/^\d{6}$/'],
            'facility_code' => ['nullable', 'string', 'max:16'],
        ]);

        $batch = ImportBatch::create($data + [
            'code' => strtoupper($data['kind'][0]).now()->format('YmdHis'),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        Audit::record($request->user()->id, 'batch.created', $batch, $data);

        return redirect()->route('batches.show', $batch)->with('status', 'Đã tạo đợt.');
    }

    public function show(ImportBatch $batch): View
    {
        $batch->load(['files.sheets', 'creator']);

        return view('batches.show', [
            'batch' => $batch,
            'profiles' => MappingProfile::where('is_active', true)
                ->where('format', 'xml')
                ->get(),
        ]);
    }

    /**
     * Nạp tệp. Bước xem trước nhận diện trang tính và dòng tiêu đề trước,
     * để quản lý xác nhận hoặc chọn lại nếu nhận diện sai.
     */
    public function upload(Request $request, ImportBatch $batch): RedirectResponse
    {
        $maxKb = config('mediguard.storage.max_upload_mb') * 1024;

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', "max:{$maxKb}"],
            'mapping_profile_id' => ['nullable', 'exists:mapping_profiles,id'],
        ]);

        $queued = 0;
        $skipped = [];

        foreach ($request->file('files') as $upload) {
            try {
                $file = $this->store->store($batch, $upload);
            } catch (DuplicateSourceFileException $e) {
                $skipped[] = $upload->getClientOriginalName().' (đã nạp trước đó)';

                continue;
            } catch (\InvalidArgumentException $e) {
                $skipped[] = $upload->getClientOriginalName().' ('.$e->getMessage().')';

                continue;
            }

            if ($batch->kind === ImportBatch::KIND_RESPONSE) {
                IngestResponseFile::dispatch($file->id, $request->user()->id);
            } else {
                $profileId = $request->integer('mapping_profile_id');
                if (! $profileId) {
                    $file->update(['status' => 'failed', 'failure_reason' => 'Chưa chọn cấu hình ánh xạ cho đợt tự kiểm tra.']);
                    $skipped[] = $upload->getClientOriginalName().' (chưa chọn cấu hình ánh xạ)';

                    continue;
                }
                IngestPrecheckFile::dispatch($file->id, $profileId, $request->user()->id);
            }

            $queued++;
        }

        $batch->update(['status' => 'ingesting']);
        Audit::record($request->user()->id, 'batch.files_uploaded', $batch, ['queued' => $queued, 'skipped' => $skipped]);

        $message = "Đã nhận {$queued} tệp và đưa vào hàng đợi xử lý.";
        if ($skipped !== []) {
            $message .= ' Bỏ qua: '.implode('; ', $skipped).'.';
        }

        return redirect()->route('batches.show', $batch)->with('status', $message);
    }

    /** Xem trước kết quả nhận diện của một tệp trước khi nạp lại với lựa chọn khác. */
    public function preview(SourceFile $file): View
    {
        $reader = $file->format === 'xlsx'
            ? new XlsxTabularReader($this->store->absolutePath($file))
            : new CsvTabularReader($this->store->absolutePath($file));

        $detected = $file->detected ?? $this->inspector->inspect($reader);

        return view('batches.preview', [
            'file' => $file,
            'detected' => $detected,
            'sheets' => $reader->sheets(),
        ]);
    }

    /** Nạp lại một tệp với trang tính/dòng tiêu đề do quản lý chọn. */
    public function reingest(Request $request, SourceFile $file): RedirectResponse
    {
        $data = $request->validate([
            'sheet_index' => ['required', 'integer', 'min:0'],
            'header_row_number' => ['required', 'integer', 'min:1'],
        ]);

        // Xóa dữ liệu lần nạp trước của đúng tệp này; các tệp khác không bị ảnh hưởng.
        $file->sheets()->delete();
        $file->issueCases()->delete();
        $file->update(['status' => 'received', 'ingested_at' => null, 'failure_reason' => null]);

        IngestResponseFile::dispatch($file->id, $request->user()->id, $data);
        Audit::record($request->user()->id, 'import.reingest_requested', $file, $data);

        return redirect()->route('batches.show', $file->import_batch_id)
            ->with('status', 'Đã đưa tệp vào hàng đợi nạp lại theo lựa chọn mới.');
    }
}
