<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Optional per-employee documents (KTP/KTM/ijazah/dll) — never a required
 * checklist, HRD uploads whichever it has on hand. Access is implicitly
 * HRD-only via the route group's role:hrd middleware, same as every other
 * Manajemen User endpoint.
 */
class EmployeeDocumentController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(EmployeeDocument::TYPES)],
            'label' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $file->store((string) $user->id, 'employee_documents');

        abort_if($path === false, 500, 'Gagal menyimpan dokumen. Coba lagi.');

        EmployeeDocument::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'label' => $validated['label'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen berhasil diunggah.']);

        return back();
    }

    public function show(EmployeeDocument $document): StreamedResponse
    {
        abort_unless(
            Storage::disk('employee_documents')->exists($document->file_path),
            404,
        );

        return Storage::disk('employee_documents')->response(
            $document->file_path,
            $document->file_name,
        );
    }

    public function destroy(EmployeeDocument $document): RedirectResponse
    {
        Storage::disk('employee_documents')->delete($document->file_path);
        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dihapus.']);

        return back();
    }
}
