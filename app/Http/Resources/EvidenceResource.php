<?php

namespace App\Http\Resources;

use App\Models\Evidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Evidence */
class EvidenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_type' => $this->file_type,
            'original_name' => $this->original_name,
            'file_size' => $this->file_size,
            'url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'uploaded_at' => $this->uploaded_at?->toIso8601String(),
        ];
    }
}
