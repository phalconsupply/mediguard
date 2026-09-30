<?php

namespace App\Http\Controllers;

use App\Models\ColumnPreset;
use App\Models\Department;
use App\Models\ErrorCatalog;
use App\Models\SourceSheet;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Màn hình "Chọn cột".
 *
 * Thao tác ở đây chỉ đổi giao diện: không xóa dữ liệu, không đổi cấu trúc tệp
 * xuất trả. Cột ẩn vẫn nằm đủ trong tệp XLSX gửi lại BHYT.
 */
class ColumnPresetController extends Controller
{
    public function index(Request $request): View
    {
        $presets = ColumnPreset::with('creator')->latest('id')->paginate(30);

        $sheetId = $request->integer('sheet');
        $sheet = $sheetId ? SourceSheet::with('columns', 'file')->find($sheetId) : null;

        return view('presets.index', [
            'presets' => $presets,
            'sheet' => $sheet,
            'sheets' => SourceSheet::with('file:id,original_name')
                ->where('is_data_sheet', true)
                ->latest('id')
                ->limit(50)
                ->get(),
            'catalogs' => ErrorCatalog::orderBy('code')->get(),
            'departments' => Department::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scope_type' => ['required', 'in:global,error_code,error_group,department,batch'],
            'scope_value' => ['nullable', 'string', 'max:64'],
            'role' => ['nullable', 'string', 'max:32'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', 'max:320'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($data['scope_type'] !== ColumnPreset::SCOPE_GLOBAL && blank($data['scope_value'] ?? null)) {
            return back()->withErrors(['scope_value' => 'Phạm vi khác toàn viện phải chỉ rõ giá trị áp dụng.']);
        }

        // Một phạm vi chỉ có một cấu hình mặc định; đặt mặc định mới sẽ hạ cấu hình cũ.
        if ($request->boolean('is_default')) {
            ColumnPreset::where('scope_type', $data['scope_type'])
                ->where('scope_value', $data['scope_value'] ?? null)
                ->where('role', $data['role'] ?? null)
                ->update(['is_default' => false]);
        }

        $preset = ColumnPreset::create($data + [
            'is_default' => $request->boolean('is_default'),
            'version' => 1,
            'created_by' => $request->user()->id,
        ]);

        Audit::record($request->user()->id, 'preset.created', $preset, [
            'scope' => $data['scope_type'],
            'columns' => count($data['columns']),
        ]);

        return back()->with('status', 'Đã lưu cấu hình cột.');
    }

    public function destroy(Request $request, ColumnPreset $preset): RedirectResponse
    {
        abort_unless($request->user()->isManager(), 403);

        Audit::record($request->user()->id, 'preset.deleted', $preset, ['name' => $preset->name]);
        $preset->delete();

        return back()->with('status', 'Đã xóa cấu hình cột. Dữ liệu nguồn không bị ảnh hưởng.');
    }
}
