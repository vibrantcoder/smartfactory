<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Support\Facades\Storage;

/**
 * PartDrawing
 *
 * Attached drawing/document file for a Part.
 * Files stored on the 'public' disk under part-drawings/{part_id}/{stored_name}.
 *
 * @property int    $id
 * @property int    $part_id
 * @property string $original_name   filename shown to users
 * @property string $stored_name     UUID-based name on disk
 * @property string $mime_type
 * @property int    $size            bytes
 */
class PartDrawing extends BaseModel
{
    protected $table = 'part_drawings';

    protected $fillable = [
        'part_id',
        'original_name',
        'stored_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'size' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    // ── Accessors ─────────────────────────────────────────────

    /** Public URL to serve/download the file. */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url("part-drawings/{$this->part_id}/{$this->stored_name}");
    }

    /** Human-readable file size. */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024)       return $bytes . ' B';
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /** Icon type based on mime: pdf | image | cad | file */
    public function getFileTypeAttribute(): string
    {
        if ($this->mime_type === 'application/pdf')       return 'pdf';
        if (str_starts_with($this->mime_type, 'image/')) return 'image';
        $ext = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        if (in_array($ext, ['dwg', 'dxf', 'step', 'stp', 'iges', 'igs'])) return 'cad';
        return 'file';
    }
}
