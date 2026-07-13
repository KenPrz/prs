<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentConfigController extends Controller
{
    public function index(Request $request): Response
    {
        // Self-heal: exactly one row per code-defined document type. Deleting
        // a type's template row would break PDF rendering and sealing, so the
        // rows are system-managed and this page is replace-template-only.
        foreach (Document::types() as $resourceClass => $meta) {
            Document::firstOrCreate(
                ['resource_class' => $resourceClass],
                ['blade_file' => $meta['blade_file']],
            );
        }

        $documents = Document::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->whereLike('resource_class', "%{$search}%");
            })
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $documents->getCollection()->transform(
            fn (Document $document) => array_merge($document->toArray(), [
                'resource_label' => $document->resourceLabel(),
            ]),
        );

        return Inertia::render('admin/config/documents/index', [
            'documents' => $documents,
        ]);
    }

    public function edit(Document $document): Response
    {
        $docxMedia = $document->getFirstMedia('docx_template');

        return Inertia::render('admin/config/documents/edit', [
            'document' => array_merge($document->toArray(), [
                'docx_template_name' => $docxMedia?->file_name,
                'docx_template_url' => $docxMedia ? route('admin.config.documents.template', $document) : null,
            ]),
            'resourceClassOptions' => $this->resourceClassOptions(),
        ]);
    }

    /**
     * Download the bound DOCX template. Media lives on the private disk, so this
     * authorized route is the only way it is web-served.
     */
    public function downloadTemplate(Document $document): BinaryFileResponse
    {
        $docxMedia = $document->getFirstMedia('docx_template');

        abort_unless($docxMedia !== null, 404);

        return response()->download($docxMedia->getPath(), $docxMedia->file_name);
    }

    /**
     * Replace (or remove) the DOCX template only — the document type itself
     * is a code contract and cannot be changed.
     */
    public function update(Request $request, Document $document): RedirectResponse
    {
        $request->validate([
            'docx_template' => ['nullable', 'file', 'mimes:docx,vnd.openxmlformats-officedocument.wordprocessingml.document', 'max:10240'],
            'remove_docx_template' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_docx_template')) {
            $document->clearMediaCollection('docx_template');
        }

        if ($request->hasFile('docx_template')) {
            $document->addMediaFromRequest('docx_template')
                ->toMediaCollection('docx_template');
        }

        $document->touch();

        return to_route('admin.config.documents.index')
            ->with('success', 'Document template updated successfully.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function resourceClassOptions(): array
    {
        return collect(Document::types())
            ->map(fn (array $meta, string $class) => ['value' => $class, 'label' => $meta['label']])
            ->values()
            ->all();
    }
}
