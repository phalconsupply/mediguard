<?php

namespace App\Http\Controllers;

use App\Jobs\ExportResponseFile;
use App\Models\ExportArtifact;
use App\Models\ImportBatch;
use App\Models\SourceFile;
use App\Services\Export\ExportVerifier;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Xuất trả: một tệp kết quả cho mỗi tệp nguồn đã chọn.
 *
 * Trước khi xuất, màn hình nêu rõ còn bao nhiêu dòng chưa có bản giải trình được duyệt.
 * Tệp chỉ được tạo khi kiểm soát chất lượng không còn lỗi chặn.
 */
class ExportController extends Controller
{
    public function __construct(private readonly ExportVerifier $verifier) {}

    public function index(Request $request, ImportBatch $batch): View
    {
        abort_unless($request->user()->isManager(), 403);

        $files = $batch->files()->with('exports')->get();

        $preflight = $files->mapWithKeys(
            fn (SourceFile $file) => [$file->id => $this->verifier->preflight($file)]
        );

        return view('exports.index', compact('batch', 'files', 'preflight'));
    }

    public function store(Request $request, SourceFile $file): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        ExportResponseFile::dispatch($file->id, $request->user()->id, $request->boolean('force'));

        Audit::record($request->user()->id, 'export.requested', $file, ['force' => $request->boolean('force')]);

        return back()->with('status', 'Đã đưa tệp vào hàng đợi xuất trả.');
    }

    public function download(Request $request, ExportArtifact $artifact): StreamedResponse
    {
        abort_unless($request->user()->isManager(), 403);

        Audit::record($request->user()->id, 'export.downloaded', $artifact);

        return Storage::disk(config('mediguard.storage.disk'))->download(
            $artifact->stored_path,
            basename($artifact->stored_path)
        );
    }
}
