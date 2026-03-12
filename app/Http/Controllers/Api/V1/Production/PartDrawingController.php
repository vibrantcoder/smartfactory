<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Production;

use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\PartDrawing;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * PartDrawingController
 *
 * ROUTES (api/v1):
 *   GET    parts/{part}/drawings                       - list drawings
 *   POST   parts/{part}/drawings                       - upload files (multipart, files[])
 *   DELETE parts/{part}/drawings/{drawing}             - delete a drawing
 */
class PartDrawingController extends Controller
{
    private const DISK = 'public';

    private const ALLOWED_MIMES = [
        'pdf', 'dwg', 'dxf', 'png', 'jpg', 'jpeg', 'svg',
        'step', 'stp', 'iges', 'igs',
    ];

    private const MAX_SIZE_KB = 20480; // 20 MB per file

    // ── index ─────────────────────────────────────────────────

    public function index(Part $part): JsonResponse
    {
        $this->authorize('view', $part);

        return response()->json(
            $part->drawings()
                 ->orderBy('created_at')
                 ->get()
                 ->map(fn(PartDrawing $d) => $this->formatDrawing($d))
        );
    }

    // ── store ─────────────────────────────────────────────────

    public function store(Request $request, Part $part): JsonResponse
    {
        $this->authorize('update', $part);

        $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'required',
                'file',
                'mimes:' . implode(',', self::ALLOWED_MIMES),
                'max:'   . self::MAX_SIZE_KB,
            ],
        ]);

        $dir     = "part-drawings/{$part->id}";
        $created = [];

        foreach ($request->file('files') as $file) {
            $ext        = $file->getClientOriginalExtension();
            $storedName = Str::uuid() . ($ext ? ".{$ext}" : '');

            Storage::disk(self::DISK)->putFileAs($dir, $file, $storedName);

            $drawing = PartDrawing::create([
                'part_id'       => $part->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name'   => $storedName,
                'mime_type'     => $file->getMimeType() ?? $file->getClientMimeType(),
                'size'          => $file->getSize(),
            ]);

            $created[] = $this->formatDrawing($drawing);
        }

        return response()->json($created, Response::HTTP_CREATED);
    }

    // ── destroy ───────────────────────────────────────────────

    public function destroy(Part $part, PartDrawing $drawing): JsonResponse
    {
        $this->authorize('update', $part);

        if ($drawing->part_id !== $part->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        Storage::disk(self::DISK)
               ->delete("part-drawings/{$part->id}/{$drawing->stored_name}");

        $drawing->delete();

        return response()->json(['message' => 'Drawing deleted.']);
    }

    // ── helpers ───────────────────────────────────────────────

    private function formatDrawing(PartDrawing $d): array
    {
        return [
            'id'            => $d->id,
            'part_id'       => $d->part_id,
            'original_name' => $d->original_name,
            'mime_type'     => $d->mime_type,
            'size'          => $d->size,
            'human_size'    => $d->human_size,
            'file_type'     => $d->file_type,
            'url'           => $d->url,
            'created_at'    => $d->created_at?->toIso8601String(),
        ];
    }
}
