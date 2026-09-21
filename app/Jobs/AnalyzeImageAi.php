<?php

namespace App\Jobs;

use App\Models\Evidence;
use App\Services\AiImageDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnalyzeImageAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Evidence $evidence) {}

    public function handle(AiImageDetectionService $detector): void
    {
        $contents = Storage::disk('public')->get($this->evidence->file_path);

        if ($contents === null) {
            $this->fail('Image file is missing on disk.');

            return;
        }

        try {
            $result = $detector->analyze($contents, $this->evidence->file_type);

            $this->evidence->update([
                'ai_label' => $result['label'],
                'ai_is_generated' => $result['is_ai_generated'],
                'ai_score' => $result['score'],
                'ai_analyzed_at' => now(),
                'ai_error' => null,
            ]);
        } catch (Throwable $exception) {
            Log::warning("Hugging Face image analysis failed for evidence {$this->evidence->id}: {$exception->getMessage()}");

            $this->fail($exception->getMessage());
        }
    }

    private function fail(string $message): void
    {
        $this->evidence->update([
            'ai_error' => $message,
            'ai_analyzed_at' => now(),
        ]);
    }
}
