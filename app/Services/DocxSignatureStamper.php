<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Stamps signature images into a DOCX template before it is sent to Carbone.
 *
 * The templates carry named placeholder pictures (word/media/sig_{key}.png,
 * a transparent 1x1 PNG). This replaces their bytes with the composed
 * signature PNGs — no template-engine image feature involved, so it works
 * with any Carbone version and keeps the templates admin-editable.
 */
class DocxSignatureStamper
{
    /**
     * @param  array<string, string|null>  $imagesByKey  slot key => PNG bytes (null leaves the placeholder invisible)
     */
    public function stamp(string $docxBinary, array $imagesByKey): string
    {
        $images = array_filter($imagesByKey, fn ($png) => \is_string($png) && $png !== '');

        if ($images === []) {
            return $docxBinary;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'docx-sig-');

        try {
            file_put_contents($tmp, $docxBinary);

            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('Could not open the DOCX template for signature stamping.');
            }

            foreach ($images as $key => $png) {
                $entry = "word/media/sig_{$key}.png";

                if ($zip->locateName($entry) !== false) {
                    $zip->addFromString($entry, $png);
                }
            }

            $zip->close();

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
