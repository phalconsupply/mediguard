<?php

namespace App\Http\Controllers;

use App\Models\ErrorCatalog;
use App\Models\ErrorCatalogVariant;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Thư viện lỗi.
 *
 * Lỗi mới do hệ thống tự ghi nhận khi nạp đợt, ở trạng thái chờ phân loại.
 * Quản lý đặt tên dễ hiểu, nhóm lỗi, vai trò phải giải trình và mức ưu tiên;
 * việc sửa mô tả ở đây không tự thay đổi logic của quy tắc kiểm tra.
 */
class ErrorCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $catalogs = ErrorCatalog::query()
            ->withCount('variants')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($s) use ($request) {
                $term = '%'.$request->input('q').'%';
                $s->where('code', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        return view('catalog.index', compact('catalogs'));
    }

    public function show(ErrorCatalog $catalog): View
    {
        $catalog->load(['variants' => fn ($q) => $q->latest('id'), 'rules.versions']);

        return view('catalog.show', compact('catalog'));
    }

    public function update(Request $request, ErrorCatalog $catalog): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'group' => ['nullable', 'string', 'max:64'],
            'responsible_role' => ['required', 'in:orderer,performer,both,unknown'],
            'status' => ['required', 'in:pending_review,classified,manual_only,retired'],
            'priority' => ['required', 'integer', 'between:1,5'],
            'explanation_template' => ['nullable', 'string', 'max:5000'],
        ]);

        $catalog->update($data);
        Audit::record($request->user()->id, 'catalog.updated', $catalog, $data);

        return back()->with('status', 'Đã cập nhật mục thư viện lỗi.');
    }

    /** Xác nhận một biến thể đã được xem, hoặc gộp vào biến thể chuẩn. */
    public function reviewVariant(Request $request, ErrorCatalogVariant $variant): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate(['status' => ['required', 'in:new,reviewed,merged']]);

        $variant->update($data);
        Audit::record($request->user()->id, 'catalog.variant_reviewed', $variant, $data);

        return back()->with('status', 'Đã cập nhật trạng thái biến thể.');
    }
}
