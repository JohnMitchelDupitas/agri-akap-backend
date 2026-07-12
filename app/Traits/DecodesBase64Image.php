<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait DecodesBase64Image
{
    /**
     * Securely decode a Base64 (data-URI or raw) image and persist it to the
     * public disk. Supports JPEG, PNG and WebP. Returns the relative storage
     * path (e.g. "assessments/abc123.jpg") or null on failure.
     */
    protected function storeBase64Image(?string $base64, string $folder = 'assessments'): ?string
    {
        if (empty($base64)) {
            return null;
        }

        $extension = 'jpg';
        $data = $base64;

        // Parse "data:image/<mime>;base64,<payload>" when present.
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $mime = strtolower($matches[1]);
            $extension = match ($mime) {
                'jpeg', 'jpg' => 'jpg',
                'png' => 'png',
                'webp' => 'webp',
                default => 'jpg',
            };
            $data = substr($base64, strpos($base64, ',') + 1);
        }

        // URL-safe base64 sometimes replaces "+" with spaces during transport.
        $data = str_replace(' ', '+', $data);

        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return null;
        }

        $fileName = $folder . '/' . Str::random(20) . '.' . $extension;
        Storage::disk('public')->put($fileName, $decoded);

        return $fileName;
    }
}
