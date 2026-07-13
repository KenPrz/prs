<?php

namespace App\Services;

use App\Models\User;
use App\Support\AttachmentCollection;
use Illuminate\Support\Collection;

/**
 * Builds the PNG images stamped into document templates' signature
 * placeholders. Every placeholder frame uses a 3:1 aspect ratio, and the
 * composed canvas matches it exactly, so signatures never distort. A block
 * with several signers (e.g. an approval chain) becomes one horizontal row.
 */
class SignatureImageComposer
{
    private const CANVAS_W = 900;

    private const CANVAS_H = 300;

    private const GAP = 40;

    /**
     * A 3:1 transparent PNG of the users' active signatures, or null when
     * nobody has one (the template placeholder then stays invisible).
     *
     * @param  Collection<int, User|null>  $users
     */
    public function composePng(Collection $users): ?string
    {
        $images = $users
            ->filter()
            ->unique(fn (User $user) => $user->id)
            ->map(fn (User $user) => $this->loadSignature($user))
            ->filter()
            ->values();

        if ($images->isEmpty()) {
            return null;
        }

        // Row of signatures at a common working height.
        $rowHeight = 240;
        $scaled = $images->map(function (\GdImage $img) use ($rowHeight) {
            $w = max(1, (int) round(imagesx($img) * ($rowHeight / imagesy($img))));

            return ['img' => $img, 'w' => $w];
        });
        $rowWidth = (int) ($scaled->sum('w') + self::GAP * ($scaled->count() - 1));

        // Contain the row inside the fixed-aspect canvas.
        $scale = min(self::CANVAS_W / $rowWidth, self::CANVAS_H / $rowHeight, 1.0);
        $targetH = (int) round($rowHeight * $scale);
        $offsetY = intdiv(self::CANVAS_H - $targetH, 2);
        $offsetX = intdiv(self::CANVAS_W - (int) round($rowWidth * $scale), 2);

        $canvas = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $x = $offsetX;
        foreach ($scaled as $entry) {
            $w = (int) round($entry['w'] * $scale);
            imagecopyresampled(
                $canvas, $entry['img'],
                $x, $offsetY, 0, 0,
                $w, $targetH,
                imagesx($entry['img']), imagesy($entry['img']),
            );
            $x += $w + (int) round(self::GAP * $scale);
        }

        ob_start();
        imagesavealpha($canvas, true);
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    /**
     * Single-user convenience wrapper.
     */
    public function forUser(?User $user): ?string
    {
        return $this->composePng(collect([$user]));
    }

    private function loadSignature(User $user): ?\GdImage
    {
        $media = $user->activeSignature?->getFirstMedia(AttachmentCollection::NAME);

        if ($media === null) {
            return null;
        }

        try {
            $contents = file_get_contents($media->getPath());
        } catch (\Throwable) {
            return null;
        }

        if ($contents === false || $contents === '') {
            return null;
        }

        $img = @imagecreatefromstring($contents);

        return $img instanceof \GdImage ? $img : null;
    }
}
