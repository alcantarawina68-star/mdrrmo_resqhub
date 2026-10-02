<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds a restorable snapshot of the application as a single zip: a full SQL
 * dump plus the uploaded evidence files, alongside a manifest recording what the
 * snapshot contains and which account produced it.
 *
 * The dump is generated in PHP on purpose. Shelling out to mysqldump would tie
 * backups to whichever client libraries happen to be installed on the host, and
 * they are not guaranteed to exist. Values are quoted through the live PDO
 * handle, so the escaping always matches the server that produced them.
 */
class BackupService
{
    /**
     * Uploaded evidence, relative to the public disk root.
     */
    public const EVIDENCE_DIRECTORY = 'evidence';

    /**
     * Value tuples per INSERT statement, so a large table streams out in bounded
     * chunks instead of one enormous statement.
     */
    private const INSERT_BATCH_SIZE = 100;

    /**
     * Scratch space that regenerates on its own, plus anything holding a
     * credential. Leaving out sessions means a restore signs everyone out,
     * which is what the single-session rule expects anyway.
     *
     * @var array<int, string>
     */
    public const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'personal_access_tokens',
        'password_reset_tokens',
    ];

    /**
     * Where the archive is assembled before it is streamed to the operator. The
     * local disk is private, so the file is never web reachable.
     */
    private const WORKING_DIRECTORY = 'backups';

    /**
     * What a backup would contain right now, so the operator can see the size of
     * the job before starting it.
     *
     * @return array{tables: array<int, array{name: string, rows: int}>, files: int, bytes: int}
     */
    public function preview(): array
    {
        $disk = Storage::disk('public');
        $files = $disk->allFiles(self::EVIDENCE_DIRECTORY);

        return [
            'tables' => array_map(
                fn (string $table) => ['name' => $table, 'rows' => (int) DB::table($table)->count()],
                $this->includedTables(),
            ),
            'files' => count($files),
            'bytes' => array_sum(array_map(fn (string $path) => (int) $disk->size($path), $files)),
        ];
    }

    /**
     * Build the archive and return where it landed.
     *
     * The caller hands the file to the response and then deletes it: nothing is
     * kept server-side on purpose.
     *
     * @return array{path: string, filename: string}
     */
    public function create(?User $requestedBy = null): array
    {
        $createdAt = now();
        $filename = 'resqhub-backup-'.$createdAt->format('Ymd-His').'.zip';
        $relative = self::WORKING_DIRECTORY.'/'.$filename;

        $disk = Storage::disk('local');
        $disk->makeDirectory(self::WORKING_DIRECTORY);

        $archive = new ZipArchive;

        if ($archive->open($disk->path($relative), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open the backup archive for writing.');
        }

        try {
            $archive->addFromString('database/dump.sql', $this->dump());
            $archive->addFromString('manifest.json', json_encode(
                $this->manifest($createdAt, $requestedBy),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
            $this->addEvidence($archive);

            $closed = $archive->close();

            if ($closed !== true) {
                throw new RuntimeException('Could not finalise the backup archive.');
            }
        } catch (Throwable $e) {
            $archive->close();
            $disk->delete($relative);

            throw $e;
        }

        return ['path' => $disk->path($relative), 'filename' => $filename];
    }

    /**
     * The complete SQL dump: schema and data for every included table.
     */
    public function dump(): string
    {
        $sql = "-- ResQHub database dump\n";
        $sql .= '-- Generated '.now()->toDateTimeString()."\n\n";
        // Insertion order stops mattering while the checks are off; any foreign
        // keys in the schema come back with the CREATE statements.
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->includedTables() as $table) {
            $sql .= $this->dumpTable($table);
        }

        return $sql."\nSET FOREIGN_KEY_CHECKS=1;\n";
    }

    /**
     * @return array<int, string>
     */
    private function includedTables(): array
    {
        $names = [];

        foreach (DB::select('SHOW TABLES') as $row) {
            $name = (string) array_values((array) $row)[0];

            if (! in_array($name, self::EXCLUDED_TABLES, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function dumpTable(string $table): string
    {
        $wrapped = $this->wrap($table);

        $definition = (array) DB::selectOne("SHOW CREATE TABLE {$wrapped}");
        $create = (string) ($definition['Create Table'] ?? $definition['Create View'] ?? '');

        $sql = "\n-- Table: {$table}\n";
        $sql .= "DROP TABLE IF EXISTS {$wrapped};\n";
        $sql .= $create.";\n\n";

        // The column list is taken from the first row rather than the schema, so
        // the headings and the values can never drift out of alignment.
        $columns = null;
        $tuples = [];

        foreach (DB::table($table)->cursor() as $row) {
            $row = (array) $row;

            $columns ??= '`'.implode('`, `', array_keys($row)).'`';

            $tuples[] = '('.implode(', ', array_map(
                fn (mixed $value): string => $this->quote($value),
                array_values($row),
            )).')';

            if (count($tuples) < self::INSERT_BATCH_SIZE) {
                continue;
            }

            $sql .= $this->insertStatement($table, $columns, $tuples);
            $tuples = [];
        }

        if ($tuples !== [] && $columns !== null) {
            $sql .= $this->insertStatement($table, $columns, $tuples);
        }

        return $sql;
    }

    /**
     * @param  array<int, string>  $tuples
     */
    private function insertStatement(string $table, string $columns, array $tuples): string
    {
        return 'INSERT INTO '.$this->wrap($table)." ({$columns}) VALUES\n".implode(",\n", $tuples).";\n";
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Binary can arrive as a stream or as raw bytes. A hex literal survives
        // both; a quoted string would be re-read as the connection charset.
        if (is_resource($value)) {
            return "X'".strtoupper(bin2hex((string) stream_get_contents($value)))."'";
        }

        if (! mb_check_encoding((string) $value, 'UTF-8')) {
            return "X'".strtoupper(bin2hex((string) $value))."'";
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    private function wrap(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function addEvidence(ZipArchive $archive): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->allFiles(self::EVIDENCE_DIRECTORY) as $path) {
            if ($disk->exists($path)) {
                $archive->addFile($disk->path($path), 'files/'.$path);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(Carbon $createdAt, ?User $requestedBy): array
    {
        return [
            'application' => config('app.name'),
            'environment' => app()->environment(),
            'created_at' => $createdAt->toIso8601String(),
            'requested_by' => $requestedBy === null ? null : [
                'id' => $requestedBy->id,
                'name' => $requestedBy->name,
                'email' => $requestedBy->email,
            ],
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => DB::connection()->getDriverName(),
            'contains' => [
                'database_dump' => 'database/dump.sql',
                'evidence_directory' => self::EVIDENCE_DIRECTORY,
            ],
            'excluded_tables' => self::EXCLUDED_TABLES,
            'tables' => array_reduce(
                $this->includedTables(),
                fn (array $carry, string $table): array => $carry + [$table => (int) DB::table($table)->count()],
                [],
            ),
            'evidence_files' => Storage::disk('public')->allFiles(self::EVIDENCE_DIRECTORY),
        ];
    }
}
