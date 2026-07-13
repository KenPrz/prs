<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Signature;
use App\Support\AttachmentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SignatureController extends Controller
{
    /**
     * Display the user's signatures.
     */
    public function index(Request $request): Response
    {
        $signatures = $request->user()->signatures()
            ->with('media')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($signature) {
                $media = $signature->getFirstMedia(AttachmentCollection::NAME);

                return [
                    'id' => $signature->id,
                    'is_active' => $signature->is_active,
                    'url' => $media ? route('signatures.image', $signature) : null,
                    'name' => $media?->file_name,
                    'created_at' => $signature->created_at->toIso8601String(),
                ];
            });

        return Inertia::render('settings/signatures', [
            'signatures' => $signatures,
        ]);
    }

    /**
     * Serve the owner's signature image. Signatures live on the private media
     * disk — this authorized route is the only way they are web-served.
     */
    public function image(Request $request, Signature $signature): BinaryFileResponse
    {
        if ($signature->user_id !== $request->user()->id) {
            abort(403);
        }

        $media = $signature->getFirstMedia(AttachmentCollection::NAME);

        abort_unless($media !== null, 404);

        return response()->file($media->getPath());
    }

    /**
     * Store a new signature.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['signature' => ['required', 'file', 'image', 'mimes:png', 'max:2048']],
            [
                'signature.mimes' => 'The signature must be a PNG image.',
                'signature.max' => 'The signature must not be larger than 2 MB.',
            ],
        );

        $user = $request->user();

        $signature = $user->signatures()->create([
            'is_active' => $user->signatures()->count() === 0, // Set active if it's the first one
        ]);

        // Re-encode through GD: strips broken metadata chunks (e.g. bad iCCP
        // profiles that make libpng warn on every render) and any EXIF.
        $normalized = $this->normalizePng((string) file_get_contents($request->file('signature')->getRealPath()));

        $signature->addMediaFromString($normalized)
            ->usingFileName('signature.png')
            ->toMediaCollection(AttachmentCollection::NAME);

        return back()->with('success', 'Signature uploaded successfully.');
    }

    /**
     * Decode and re-encode a PNG, preserving transparency. Falls back to the
     * original bytes if GD cannot parse them (validation already vetted it).
     */
    private function normalizePng(string $contents): string
    {
        $img = @imagecreatefromstring($contents);

        if (! $img instanceof \GdImage) {
            return $contents;
        }

        imagesavealpha($img, true);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /**
     * Set a signature as active.
     */
    public function update(Request $request, Signature $signature): RedirectResponse
    {
        if ($signature->user_id !== $request->user()->id) {
            abort(403);
        }

        $signature->update(['is_active' => true]);

        return back()->with('success', 'Signature marked as active.');
    }

    /**
     * Delete a signature.
     */
    public function destroy(Request $request, Signature $signature): RedirectResponse
    {
        if ($signature->user_id !== $request->user()->id) {
            abort(403);
        }

        $signature->delete();

        return back()->with('success', 'Signature deleted successfully.');
    }
}
