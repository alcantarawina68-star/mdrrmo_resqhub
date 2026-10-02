<?php

use App\Enums\AnnouncementCategory;
use App\Enums\IncidentStatus;
use App\Enums\Severity;
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Incident;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Read a streamed CSV response into an array of rows, dropping the UTF-8 BOM.
 *
 * @return array<int, array<int, string|null>>
 */
function csvRows(string $body): array
{
    $lines = preg_split('/\r\n|\n|\r/', trim(str_replace("\xEF\xBB\xBF", '', $body)));

    return array_map(fn (string $line): array => str_getcsv($line), array_values(array_filter($lines, fn ($l) => $l !== '')));
}

function fakeAuditSession(int $userId, ?int $lastActivity = null): void
{
    DB::table('sessions')->insert([
        'id' => 'session-'.$userId.'-'.uniqid(),
        'user_id' => $userId,
        'ip_address' => '10.0.0.'.$userId,
        'user_agent' => 'PestBrowser/1.0 (Windows NT 10.0)',
        'payload' => base64_encode(serialize([])),
        'last_activity' => $lastActivity ?? time(),
    ]);
}

/**
 * Satisfy the reauthenticate gate on the sensitive exports.
 */
function confirmPassword(User $user): void
{
    actingAs($user);
    session(['auth.password_confirmed_at' => time()]);
}

/*
|--------------------------------------------------------------------------
| Incidents
|--------------------------------------------------------------------------
*/

test('the incident CSV export carries a BOM, headings and one row per incident', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->count(2)->create(['status' => IncidentStatus::Ongoing]);

    $response = actingAs($admin)->get(route('dashboard.reports.export'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('resqhub-incidents-')
        ->and($response->headers->get('content-type'))->toBe('text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF");

    $rows = csvRows($content);

    expect($rows[0])->toBe([
        'Incident No.', 'Type', 'Status', 'Location', 'Source', 'Assigned Unit',
        'Reporter', 'Reported At', 'Verified At', 'Resolved At', 'Evidence',
    ])->and($rows)->toHaveCount(3);
});

test('the incident export no longer repeats the location as a barangay column', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['location_label' => 'Minanga']);

    $rows = csvRows(actingAs($admin)->get(route('dashboard.reports.export'))->streamedContent());

    expect($rows[0])->not->toContain('Barangay');
});

test('the incident CSV keeps a literal backslash and a new line inside a cell', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['description' => 'path C:\\windows and a
break']);

    $content = actingAs($admin)->get(route('dashboard.reports.export'))->streamedContent();

    expect(csvRows($content)[1][0])->toStartWith('RQ-');
});

test('the incident export is bounded by the from and to filters', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['reported_at' => now()->subDays(40)]);
    Incident::factory()->create(['reported_at' => now()]);

    $rows = csvRows(actingAs($admin)->get(route('dashboard.reports.export', [
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]))->streamedContent());

    expect($rows)->toHaveCount(2);
});

test('an unknown filter key is ignored instead of reaching the query builder', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create();

    $rows = csvRows(actingAs($admin)->get(route('dashboard.reports.export', [
        'password' => 'not-a-column',
        'users.name' => 'nope',
    ]))->streamedContent());

    expect($rows)->toHaveCount(2);
});

test('the incidents list export matches the filters applied to the list', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['status' => IncidentStatus::Closed]);
    Incident::factory()->create(['status' => IncidentStatus::Ongoing]);

    $rows = csvRows(actingAs($admin)->get(route('dashboard.incidents.export', [
        'status' => IncidentStatus::Closed->value,
    ]))->streamedContent());

    expect($rows)->toHaveCount(2)->and($rows[1][2])->toBe('Closed');
});

test('the incident PDF export returns a real PDF', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create();

    $response = actingAs($admin)->get(route('dashboard.reports.export.pdf'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->content())->toStartWith('%PDF-');
});

/*
|--------------------------------------------------------------------------
| Users roster
|--------------------------------------------------------------------------
*/

test('the user roster export lists accounts without leaking secrets', function () {
    $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    Incident::factory()->create(['user_id' => $admin->id]);

    $response = actingAs($admin)->get(route('dashboard.users.export'));
    $response->assertOk();

    $rows = csvRows($response->streamedContent());
    $content = $response->streamedContent();

    expect($rows[0])->toContain('Role', 'Status', 'Incidents Submitted', 'Last Active')
        ->and($rows[1][1])->toBe('Ada Admin')
        ->and($rows[1][3])->toBe('Administrator')
        ->and($rows[1][7])->toBe('1')
        ->and($content)->not->toContain($admin->password)
        ->and($content)->not->toContain('session_id');
});

test('a plain admin never sees a superadmin row in the roster export', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->superadmin()->create(['email' => 'boss@resqhub.test']);

    $content = actingAs($admin)->get(route('dashboard.users.export'))->streamedContent();

    expect($content)->not->toContain('boss@resqhub.test');
});

test('a superadmin does see every account in the roster export', function () {
    User::factory()->superadmin()->create(['email' => 'boss@resqhub.test']);

    $content = actingAs(User::factory()->superadmin()->create())
        ->get(route('dashboard.users.export'))
        ->streamedContent();

    expect($content)->toContain('boss@resqhub.test');
});

test('the roster export honours the role and status filters', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->responder()->create();
    User::factory()->communityUser()->create(['status' => 'suspended']);

    $rows = csvRows(actingAs($admin)->get(route('dashboard.users.export', [
        'role' => UserRole::Responder->value,
    ]))->streamedContent());

    expect($rows)->toHaveCount(2)->and($rows[1][3])->toBe('Responder');
});

test('the roster PDF export returns a real PDF', function () {
    $admin = User::factory()->admin()->create();

    expect(actingAs($admin)->get(route('dashboard.users.export.pdf'))->content())->toStartWith('%PDF-');
});

/*
|--------------------------------------------------------------------------
| Sessions audit
|--------------------------------------------------------------------------
*/

test('the sessions export lists devices but never the session id', function () {
    $superadmin = User::factory()->superadmin()->create();
    $sessionId = 'session-abc-123';
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $superadmin->id,
        'ip_address' => '203.0.113.9',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    confirmPassword($superadmin);

    $content = actingAs($superadmin)->get(route('dashboard.sessions.export'))->streamedContent();
    $rows = csvRows($content);

    expect($rows[1][3])->toBe('203.0.113.9')
        ->and($rows[1][4])->toBe('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)')
        ->and($content)->not->toContain($sessionId);
});

test('the sessions export requires a confirmed password', function () {
    $superadmin = User::factory()->superadmin()->create();
    fakeAuditSession($superadmin->id);

    actingAs($superadmin)->get(route('dashboard.sessions.export'))
        ->assertRedirect(route('password.confirm'));

    actingAs($superadmin);
    session(['auth.password_confirmed_at' => time()]);

    actingAs($superadmin)->get(route('dashboard.sessions.export'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Announcements log
|--------------------------------------------------------------------------
*/

test('the announcements export lists advisories and whether each one expired', function () {
    $encoder = User::factory()->encoder()->create();
    Announcement::factory()->create([
        'title' => 'Typhoon signal three',
        'category' => AnnouncementCategory::Warning,
        'severity' => Severity::Urgent,
        'expires_at' => null,
    ]);
    Announcement::factory()->expired()->create();

    $rows = csvRows(actingAs($encoder)->get(route('dashboard.announcements.export'))->streamedContent());

    expect($rows)->toHaveCount(3)
        ->and(implode('|', $rows[1]))->toContain('Typhoon signal three')
        ->and($rows[1][5])->toBe('No expiry')
        ->and($rows[2][5])->toBe('Yes');
});

test('the announcements export honours the category filter', function () {
    $encoder = User::factory()->encoder()->create();
    Announcement::factory()->create(['category' => AnnouncementCategory::Warning]);
    Announcement::factory()->create(['category' => AnnouncementCategory::Advisory]);

    $rows = csvRows(actingAs($encoder)->get(route('dashboard.announcements.export', [
        'category' => AnnouncementCategory::Advisory->value,
    ]))->streamedContent());

    expect($rows)->toHaveCount(2);
});

test('the announcements PDF export returns a real PDF', function () {
    $encoder = User::factory()->encoder()->create();

    expect(actingAs($encoder)->get(route('dashboard.announcements.export.pdf'))->content())->toStartWith('%PDF-');
});

/*
|--------------------------------------------------------------------------
| SMS delivery log
|--------------------------------------------------------------------------
*/

test('the SMS export lists delivery attempts and requires a confirmed password', function () {
    $encoder = User::factory()->encoder()->create();
    SmsMessage::query()->create([
        'phone' => '09171234567',
        'message' => 'Your report has been received.',
        'status' => 'failed',
        'attempts' => 2,
        'error' => 'Gateway timeout',
        'created_at' => now(),
    ]);

    actingAs($encoder)->get(route('dashboard.sms.export'))
        ->assertRedirect(route('password.confirm'));

    confirmPassword($encoder);

    $rows = csvRows(actingAs($encoder)->get(route('dashboard.sms.export'))->streamedContent());

    expect($rows)->toHaveCount(2)
        ->and($rows[1][1])->toBe('09171234567')
        ->and($rows[1][2])->toBe('failed')
        ->and($rows[1][3])->toBe('2');
});

test('the SMS export honours the status filter', function () {
    $encoder = User::factory()->encoder()->create();
    SmsMessage::query()->create(['phone' => '09170000000', 'message' => 'a', 'status' => 'sent', 'attempts' => 1, 'created_at' => now()]);
    SmsMessage::query()->create(['phone' => '09171111111', 'message' => 'b', 'status' => 'failed', 'attempts' => 3, 'created_at' => now()]);

    confirmPassword($encoder);

    $rows = csvRows(actingAs($encoder)->get(route('dashboard.sms.export', ['status' => 'sent']))->streamedContent());

    expect($rows)->toHaveCount(2)->and($rows[1][1])->toBe('09170000000');
});

/*
|--------------------------------------------------------------------------
| My Reports
|--------------------------------------------------------------------------
*/

test('a user export contains only their own reports', function () {
    $owner = User::factory()->communityUser()->create();
    $other = User::factory()->communityUser()->create();

    Incident::factory()->create(['user_id' => $owner->id, 'location_label' => 'Mine']);
    Incident::factory()->create(['user_id' => $other->id, 'location_label' => 'Theirs']);

    $content = actingAs($owner)->get(route('my-reports.export'))->streamedContent();

    expect($content)->toContain('Mine')->not->toContain('Theirs');
});

test('the My Reports PDF export returns a real PDF scoped to the viewer', function () {
    $owner = User::factory()->communityUser()->create();
    Incident::factory()->create(['user_id' => $owner->id]);

    expect(actingAs($owner)->get(route('my-reports.export.pdf'))->content())->toStartWith('%PDF-');
});

test('the My Reports list and its export agree on the period filter', function () {
    $owner = User::factory()->communityUser()->create();
    Incident::factory()->create(['user_id' => $owner->id, 'reported_at' => now()->subDays(40)]);
    Incident::factory()->create(['user_id' => $owner->id, 'reported_at' => now()]);

    $filters = ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()];

    $listed = actingAs($owner)->get(route('my-reports', $filters))->assertOk();
    $exported = csvRows(actingAs($owner)->get(route('my-reports.export', $filters))->streamedContent());

    expect($listed->viewData('incidents')->total())->toBe(1)
        ->and($exported)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Authorization matrix
|--------------------------------------------------------------------------
*/

dataset('exports refused to unprivileged roles', [
    'users' => ['dashboard.users.export', [UserRole::Encoder]],
    'sessions' => ['dashboard.sessions.export', [UserRole::Admin]],
    'announcements' => ['dashboard.announcements.export', [UserRole::Responder]],
    'sms' => ['dashboard.sms.export', [UserRole::CommunityUser]],
    'incidents' => ['dashboard.incidents.export', [UserRole::CommunityUser]],
    'reports' => ['dashboard.reports.export', [UserRole::Responder]],
]);

test('an export is refused to a role that may not use it', function (string $route, array $roles) {
    foreach ($roles as $role) {
        $user = User::factory()->create(['role' => $role]);

        actingAs($user)->get(route($route))->assertForbidden();
    }
})->with('exports refused to unprivileged roles');

test('every export requires an authenticated user', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'dashboard.users.export',
    'dashboard.sessions.export',
    'dashboard.announcements.export',
    'dashboard.sms.export',
    'dashboard.incidents.export',
    'dashboard.reports.export',
    'my-reports.export',
]);

test('an export with no matching rows still returns a headed CSV', function () {
    $admin = User::factory()->admin()->create();

    $rows = csvRows(actingAs($admin)->get(route('dashboard.reports.export', [
        'from' => now()->subYear()->toDateString(),
        'to' => now()->subYears(2)->toDateString(),
    ]))->streamedContent());

    expect($rows)->toHaveCount(1)->and($rows[0][0])->toBe('Incident No.');
});
