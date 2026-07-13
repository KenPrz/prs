<?php

namespace App\Pdf;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class MergeMediaIntoPdf
{
    /**
     * Append attachment PDFs (all pages) and raster images (one page each) after the main PDF.
     *
     * @param  iterable<Media>  $attachments
     */
    public function merge(string $mainPdfBinary, iterable $attachments): string
    {
        $list = collect($attachments)->filter(fn (Media $m) => $this->isMergeable($m));

        if ($list->isEmpty()) {
            return $mainPdfBinary;
        }

        try {
            $pdf = new Fpdi;
            $this->appendPdfBinary($pdf, $mainPdfBinary);

            foreach ($list as $media) {
                $path = $this->resolveReadablePath($media);
                if ($path === null) {
                    continue;
                }

                $cleanup = $path !== $media->getPath();

                try {
                    if ($this->isPdfMime($media)) {
                        $this->appendPdfFile($pdf, $path);
                    } elseif ($this->isRasterImageMime($media)) {
                        $this->appendImagePage($pdf, $path);
                    }
                } catch (Throwable $e) {
                    Log::warning('pdf_merge_attachment_skipped', [
                        'media_id' => $media->id,
                        'message' => $e->getMessage(),
                    ]);
                } finally {
                    if ($cleanup && is_file($path)) {
                        @unlink($path);
                    }
                }
            }

            return $pdf->Output('S');
        } catch (Throwable $e) {
            Log::warning('pdf_merge_failed', ['message' => $e->getMessage()]);

            return $mainPdfBinary;
        }
    }

    /**
     * Concatenate several already-rendered PDF binaries in order into a single PDF.
     *
     * @param  array<int, string|null>  $binaries
     */
    public function concat(array $binaries): string
    {
        $binaries = array_values(array_filter($binaries, fn ($b) => is_string($b) && $b !== ''));

        if (count($binaries) <= 1) {
            return $binaries[0] ?? '';
        }

        try {
            $pdf = new Fpdi;

            foreach ($binaries as $binary) {
                $this->appendPdfBinary($pdf, $binary);
            }

            return $pdf->Output('S');
        } catch (Throwable $e) {
            Log::warning('pdf_concat_failed', ['message' => $e->getMessage()]);

            return $binaries[0] ?? '';
        }
    }

    private function isMergeable(Media $media): bool
    {
        return $this->isPdfMime($media) || $this->isRasterImageMime($media);
    }

    private function isPdfMime(Media $media): bool
    {
        $mime = strtolower((string) ($media->mime_type ?? ''));

        return $mime === 'application/pdf';
    }

    private function isRasterImageMime(Media $media): bool
    {
        $mime = strtolower((string) ($media->mime_type ?? ''));

        return str_starts_with($mime, 'image/')
            && $mime !== 'image/svg+xml';
    }

    private function resolveReadablePath(Media $media): ?string
    {
        $path = $media->getPath();
        if (is_readable($path)) {
            return $path;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pdf-att-');
        try {
            $contents = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
            if ($contents === null || $contents === '') {
                @unlink($tmp);

                return null;
            }
            file_put_contents($tmp, $contents);

            return $tmp;
        } catch (Throwable) {
            @unlink($tmp);

            return null;
        }
    }

    private function appendPdfBinary(Fpdi $pdf, string $binary): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf-main-');
        try {
            file_put_contents($tmp, $binary);
            $this->appendPdfFile($pdf, $tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function appendPdfFile(Fpdi $pdf, string $path): void
    {
        $pageCount = $pdf->setSourceFile($path);
        for ($i = 1; $i <= $pageCount; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'] === 'L' ? 'L' : 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
        }
    }

    private function appendImagePage(Fpdi $pdf, string $path): void
    {
        $pdf->AddPage();
        $pdf->Image($path, 10, 10, 190);
    }
}
