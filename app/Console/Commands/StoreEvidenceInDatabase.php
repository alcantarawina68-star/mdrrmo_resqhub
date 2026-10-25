<?php

namespace App\Console\Commands;

use App\Models\Evidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class StoreEvidenceInDatabase extends Command
{
    protected $signature = 'evidence:store-in-database {--dry-run : Report what would be copied without writing}';

    protected $description = 'Copy evidence images from the public disk into the evidence_files table';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dryRun = (bool) $this->option('dry-run');

        $stored = 0;
        $missing = 0;
        $unreadable = 0;

        Evidence::query()
            ->whereDoesntHave('file')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($disk, $dryRun, &$stored, &$missing, &$unreadable): void {
                foreach ($rows as $evidence) {
                    if (! $disk->exists($evidence->file_path)) {
                        $missing++;

                        continue;
                    }

                    $contents = $disk->get($evidence->file_path);

                    if ($contents === null) {
                        $unreadable++;

                        continue;
                    }

                    if (! $dryRun) {
                        $evidence->file()->create(['content' => $contents]);
                    }

                    $stored++;
                }
            });

        $this->components->info($dryRun ? 'Dry run: nothing was written.' : 'Evidence images are now stored in the database.');

        $this->components->twoColumnDetail('Copied', (string) $stored);
        $this->components->twoColumnDetail('Missing on disk', (string) $missing);
        $this->components->twoColumnDetail('Unreadable', (string) $unreadable);

        return self::SUCCESS;
    }
}
