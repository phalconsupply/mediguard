<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\Explanation;
use App\Models\IssueCase;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Soạn, gửi duyệt và duyệt nội dung giải trình.
 *
 * Nội dung được lưu thành phiên bản: sửa sau khi bị trả lại tạo bản mới,
 * bản cũ vẫn đọc lại được. Chỉ bản được duyệt mới đi vào tệp xuất trả.
 */
class ExplanationController extends Controller
{
    public function store(Request $request, IssueCase $case): RedirectResponse
    {
        $this->authorize('explain', $case);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:10000'],
            'resolution' => ['required', 'in:'.implode(',', array_keys(Explanation::RESOLUTIONS))],
            'submit' => ['nullable', 'boolean'],
            'evidences' => ['nullable', 'array'],
            'evidences.*' => ['file', 'max:20480'],
        ]);

        $explanation = DB::transaction(function () use ($request, $case, $data) {
            // Bản nháp đang mở thì sửa tiếp; bản đã gửi hoặc đã duyệt thì mở phiên bản mới.
            $current = $case->explanations()->first();
            $reuseDraft = $current !== null
                && in_array($current->status, [Explanation::STATUS_DRAFT, Explanation::STATUS_RETURNED], true)
                && $current->author_id === $request->user()->id;

            $explanation = $reuseDraft ? $current : new Explanation([
                'issue_case_id' => $case->id,
                'version' => ((int) $case->explanations()->max('version')) + 1,
                'author_id' => $request->user()->id,
            ]);

            $explanation->fill([
                'issue_case_id' => $case->id,
                'body' => $data['body'],
                'resolution' => $data['resolution'],
                'status' => $request->boolean('submit') ? Explanation::STATUS_SUBMITTED : Explanation::STATUS_DRAFT,
                'submitted_at' => $request->boolean('submit') ? now() : null,
                'return_reason' => null,
            ])->save();

            $case->update([
                'status' => $request->boolean('submit')
                    ? IssueCase::STATUS_PENDING_APPROVAL
                    : IssueCase::STATUS_IN_PROGRESS,
            ]);

            return $explanation;
        });

        $this->storeEvidences($request, $explanation);

        Audit::record($request->user()->id, $request->boolean('submit') ? 'explanation.submitted' : 'explanation.saved', $explanation, [
            'case_id' => $case->id,
            'version' => $explanation->version,
        ]);

        return back()->with('status', $request->boolean('submit')
            ? 'Đã gửi duyệt bản giải trình.'
            : 'Đã lưu bản nháp.');
    }

    public function approve(Request $request, Explanation $explanation): RedirectResponse
    {
        $case = $explanation->issueCase;
        $this->authorize('approve', $case);

        if ($explanation->author_id === $request->user()->id && ! $request->user()->isManager()) {
            return back()->withErrors(['approve' => 'Người viết không tự duyệt bản giải trình của mình.']);
        }

        if ($explanation->status !== Explanation::STATUS_SUBMITTED) {
            return back()->withErrors(['approve' => 'Chỉ duyệt được bản đã gửi duyệt.']);
        }

        DB::transaction(function () use ($explanation, $case, $request) {
            // Mỗi vụ việc chỉ giữ đúng một bản được duyệt.
            $case->explanations()
                ->where('status', Explanation::STATUS_APPROVED)
                ->update(['status' => Explanation::STATUS_RETURNED, 'return_reason' => 'Thay bằng bản duyệt mới.']);

            $explanation->fill([
                'status' => Explanation::STATUS_APPROVED,
                'approver_id' => $request->user()->id,
                'approved_at' => now(),
            ])->save();

            $case->update(['status' => IssueCase::STATUS_APPROVED]);
        });

        Audit::record($request->user()->id, 'explanation.approved', $explanation, ['case_id' => $case->id]);

        return back()->with('status', 'Đã duyệt bản giải trình.');
    }

    public function returnToAuthor(Request $request, Explanation $explanation): RedirectResponse
    {
        $case = $explanation->issueCase;
        $this->authorize('approve', $case);

        $data = $request->validate(['return_reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($explanation, $case, $data, $request) {
            $explanation->fill([
                'status' => Explanation::STATUS_RETURNED,
                'approver_id' => $request->user()->id,
                'return_reason' => $data['return_reason'],
            ])->save();

            $case->update(['status' => IssueCase::STATUS_RETURNED]);
        });

        Audit::record($request->user()->id, 'explanation.returned', $explanation, $data);

        return back()->with('status', 'Đã trả lại bản giải trình kèm lý do.');
    }

    private function storeEvidences(Request $request, Explanation $explanation): void
    {
        foreach ((array) $request->file('evidences') as $upload) {
            $hash = hash_file('sha256', $upload->getRealPath());
            $relative = sprintf('%s/evidences/%d', config('mediguard.storage.root'), $explanation->id);

            Storage::disk(config('mediguard.storage.disk'))
                ->putFileAs($relative, $upload, $hash.'.'.$upload->getClientOriginalExtension());

            Evidence::create([
                'explanation_id' => $explanation->id,
                'original_name' => $upload->getClientOriginalName(),
                'stored_path' => $relative.'/'.$hash.'.'.$upload->getClientOriginalExtension(),
                'sha256' => $hash,
                'size_bytes' => $upload->getSize() ?: 0,
                'mime' => $upload->getMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }
}
