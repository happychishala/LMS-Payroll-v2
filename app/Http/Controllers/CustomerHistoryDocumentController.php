<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerHistoryDocumentController extends Controller
{
    public function __invoke(Request $request)
    {
        $disk = (string) $request->query('disk', '');
        $encodedPath = (string) $request->query('path', '');

        abort_unless(in_array($disk, ['public', 'borrowers'], true), 404);

        $decodedPath = base64_decode($encodedPath, true);
        abort_unless(is_string($decodedPath) && $decodedPath !== '', 404);

        $normalizedPath = ltrim(str_replace('\\', '/', $decodedPath), '/');
        abort_if(Str::contains($normalizedPath, '..'), 404);

        if ($disk === 'borrowers') {
            $normalizedPath = Str::startsWith($normalizedPath, 'BORROWERS/')
                ? Str::after($normalizedPath, 'BORROWERS/')
                : $normalizedPath;
        }

        abort_unless(Storage::disk($disk)->exists($normalizedPath), 404);

        return response()->file(Storage::disk($disk)->path($normalizedPath));
    }
}
