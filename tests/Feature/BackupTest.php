<?php

use App\Http\Middleware\RequireReauthentication;
use App\Models\Incident;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/**
 * Pull every INSERT statement out of a dump.
 *
 * A terminator cannot simply be looked for, because a quoted value is allowed to
 * contain one. Anything a backup claims to be restorable has to survive this.
 *
 * @return array<int, string>
 */
function insertStatements(string $sql): array
{
    $statements = [];
    $offset = 0;
    $length = strlen($sql);

    while (($start = strpos($sql, 'INSERT INTO ', $offset)) !== false) {
        $quoted = false;
        $i = $start;

        for (; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quoted) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === "'") {
                    $quoted = false;
                }

                continue;
            }

            if ($char === "'") {
                $quoted = true;
            } elseif ($char === ';') {
                break;
            }
        }

        $statements[] = substr($sql, $start, $i - $start + 1);
        $offset = $i + 1;
    }

    return $statements;
}

/**
 * Every table a dump is expected to carry data for.
 *
 * @return array<int, string>
 */
function dumpedTables(BackupService $service): array
{
    $names = [];

    foreach (DB::select('SHOW TABLES') as $row) {
        $name = (string) array_values((array) $row)[0];

        if (! in_array($name, BackupService::EXCLUDED_TABLES, true)) {
            $names[] = $name;
        }
    }

    return $names;
}

/*
|--------------------------------------------------------------------------
| Reaching the page
|--------------------------------------------------------------------------
*/

test('the backup page is closed to guests and to every role but the superadmin', function () {
    $this->get(route('dashboard.backup'))->assertRedirect(route('login'));

    foreach (['admin', 'encoder', 'responder', 'communityUser'] as $state) {
        actingAs(User::factory()->{$state}()->create())->get(route('dashboard.backup'))->assertForbidden();
    }
});

test('a superadmin reaches the backup page and is warned that it holds secrets', function () {
    $superadmin = User::factory()->superadmin()->create();

    actingAs($superadmin)->get(route('dashboard.backup'))
        ->assertOk()
        ->assertSee('password hash')
        ->assertDontSee($superadmin->password);
});

/*
|--------------------------------------------------------------------------
| Running a backup
|--------------------------------------------------------------------------
*/

test('running a backup demands a confirmed password first', function () {
    $superadmin = User::factory()->superadmin()->create();

    actingAs($superadmin)->get(route('dashboard.backup.run'))
        ->assertRedirect(route('password.confirm'));

    expect(session('auth.redirect_to'))->toBe(route('dashboard.backup.run'));
});

test('the password confirmation sends the user straight to the download', function () {
    $superadmin = User::factory()->superadmin()->create();

    actingAs($superadmin)->get(route('dashboard.backup.run'))
        ->assertRedirect(route('password.confirm'));

    // The interrupted request is a GET, so there is nothing to replay and the
    // user is not stranded on a scripted "Finishing your request" page.
    expect(session(RequireReauthentication::PENDING_KEY))->toBeNull();

    actingAs($superadmin);
    session(['auth.password_confirmed_at' => time()]);

    // Reading the body is what sends the response, and sending it is what triggers
    // deleteFileAfterSend, so this also proves the download cleans up after itself.
    $response = actingAs($superadmin)->get(route('dashboard.backup.run'));
    $response->assertOk();

    expect($response->streamedContent())->toStartWith('PK')
        ->and(Storage::disk('local')->files('backups'))->toBeEmpty();
});

test('the backup page offers the download as a navigation, not a scripted replay', function () {
    $superadmin = User::factory()->superadmin()->create();

    actingAs($superadmin)->get(route('dashboard.backup'))
        ->assertOk()
        ->assertSee('href="'.route('dashboard.backup.run').'"', false);
});

test('a non superadmin cannot run a backup even with a confirmed password', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin);
    session(['auth.password_confirmed_at' => time()]);

    actingAs($admin)->get(route('dashboard.backup.run'))->assertForbidden();
});

test('a confirmed superadmin receives the archive, and nothing is left on the server', function () {
    $superadmin = User::factory()->superadmin()->create();

    actingAs($superadmin);
    session(['auth.password_confirmed_at' => time()]);

    $response = actingAs($superadmin)->get(route('dashboard.backup.run'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/zip')
        ->and($response->headers->get('content-disposition'))->toContain('resqhub-backup-')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and($response->headers->get('cache-control'))->toContain('private')
        ->and($response->headers->get('cache-control'))->not->toContain('public');

    $body = $response->streamedContent();

    expect($body)->toStartWith('PK');

    $temp = tempnam(sys_get_temp_dir(), 'backup').'.zip';
    file_put_contents($temp, $body);

    $archive = new ZipArchive;
    expect($archive->open($temp))->toBeTrue();
    expect($archive->getFromName('database/dump.sql'))->toContain('CREATE TABLE')
        ->and($archive->getFromName('manifest.json'))->toBeString();
    $archive->close();

    unlink($temp);

    expect(Storage::disk('local')->files('backups'))->toBeEmpty();
});

test('the archive records who took it and what was left out', function () {
    $superadmin = User::factory()->superadmin()->create();

    $backup = app(BackupService::class)->create($superadmin);

    $archive = new ZipArchive;
    $archive->open($backup['path']);

    $manifest = json_decode((string) $archive->getFromName('manifest.json'), true);
    $archive->close();

    expect($manifest['requested_by']['email'])->toBe($superadmin->email)
        ->and($manifest['requested_by'])->not->toHaveKey('password')
        ->and($manifest['excluded_tables'])->toBe(BackupService::EXCLUDED_TABLES)
        ->and($manifest['contains']['database_dump'])->toBe('database/dump.sql')
        ->and($manifest)->toHaveKey('tables');

    unlink($backup['path']);
});

test('the archive carries the uploaded evidence files', function () {
    Storage::fake('public');
    Storage::disk('public')->put('evidence/2026/photo.jpg', 'binary-ish evidence');

    $backup = app(BackupService::class)->create();

    $archive = new ZipArchive;
    $archive->open($backup['path']);

    expect($archive->getFromName('files/evidence/2026/photo.jpg'))->toBe('binary-ish evidence');
    $archive->close();

    unlink($backup['path']);
});

/*
|--------------------------------------------------------------------------
| The dump itself
|--------------------------------------------------------------------------
*/

test('the dump creates the schema and skips the scratch and credential tables', function () {
    User::factory()->superadmin()->create();
    DB::table('sessions')->insert([
        'id' => 'to-be-excluded', 'user_id' => null, 'ip_address' => '127.0.0.1',
        'user_agent' => 'pest', 'payload' => 'x', 'last_activity' => time(),
    ]);

    $sql = app(BackupService::class)->dump();

    expect($sql)->toContain('SET FOREIGN_KEY_CHECKS=0;')
        ->and($sql)->toEndWith("SET FOREIGN_KEY_CHECKS=1;\n");

    foreach (dumpedTables(app(BackupService::class)) as $table) {
        expect($sql)->toContain("CREATE TABLE `{$table}`");
    }

    foreach (BackupService::EXCLUDED_TABLES as $table) {
        expect($sql)->not->toContain("CREATE TABLE `{$table}`");
    }

    expect($sql)->not->toContain('to-be-excluded');
});

test('every quoted value in the dump is a single well formed field', function () {
    Incident::factory()->create([
        'description' => "Line one\nLine two, with a comma; and a 'quote' plus \"doubles\" and C:\\windows\\system32",
    ]);

    $sql = app(BackupService::class)->dump();
    $incidentInserts = array_values(array_filter(
        insertStatements($sql),
        fn (string $statement): bool => str_starts_with($statement, 'INSERT INTO `incidents`'),
    ));

    expect($incidentInserts)->toHaveCount(1);

    // "VALUES" is followed by a newline and an opening bracket, and so is every
    // tuple after it, so the count is also the tuple count. A raw newline inside
    // a value would break the statement and show up here as an extra tuple.
    expect(substr_count($incidentInserts[0], "\n("))->toBe(1)
        ->and($incidentInserts[0])->not->toContain("Line one\n");
});

test('the dump restores its rows byte for byte', function () {
    $hostile = "O'Neil \"Q\" C:\\temp\nSecond line, -- not a comment; \\ end";
    $user = User::factory()->superadmin()->create(['name' => $hostile]);
    Incident::factory()->create(['user_id' => $user->id, 'description' => $hostile]);

    $sql = app(BackupService::class)->dump();
    $statements = insertStatements($sql);

    expect($statements)->not->toBeEmpty();

    // Replay the dump against emptied tables inside a transaction. DDL would
    // commit implicitly, so only the statements are applied here.
    DB::beginTransaction();

    try {
        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

        foreach (dumpedTables(app(BackupService::class)) as $table) {
            DB::table($table)->delete();
        }

        foreach ($statements as $statement) {
            DB::unprepared($statement);
        }

        expect(User::find($user->id)->name)->toBe($hostile)
            ->and(Incident::where('user_id', $user->id)->value('description'))->toBe($hostile)
            ->and(User::count())->toBeGreaterThan(0);
    } finally {
        DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        DB::rollBack();
    }
});

test('an archive that fails to assemble is cleaned up instead of being left half written', function () {
    Storage::fake('local');

    $service = $this->partialMock(BackupService::class);
    $service->shouldReceive('dump')->andThrow(new RuntimeException('the dump failed'));

    expect(fn () => $service->create())->toThrow(RuntimeException::class);

    expect(Storage::disk('local')->files('backups'))->toBeEmpty();
});
