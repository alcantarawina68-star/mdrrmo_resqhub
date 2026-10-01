<?php

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Jobs\SendSms;
use App\Models\Incident;
use App\Models\User;
use App\Notifications\IncidentAssigned;
use App\Notifications\IncidentReported;
use App\Notifications\IncidentStatusChanged;
use App\Services\IncidentService;
use App\Support\NotificationFeed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

/**
 * @return array<string, mixed>
 */
function notificationIncidentData(array $overrides = []): array
{
    return [
        'incident_type' => IncidentType::Fire->value,
        'description' => 'A house fire was spotted near the barangay hall spreading quickly.',
        'latitude' => 18.2756,
        'longitude' => 121.6756,
        'location_label' => 'Balogo',
        ...$overrides,
    ];
}

test('a new online report alerts every active operations user and nobody else', function () {
    Notification::fake();
    Queue::fake([SendSms::class]);

    $reporter = User::factory()->communityUser()->create();
    $admin = User::factory()->admin()->create();
    $encoder = User::factory()->encoder()->create();
    $superadmin = User::factory()->superadmin()->create();
    $responder = User::factory()->responder()->create();
    $otherCommunityUser = User::factory()->communityUser()->create();
    $suspendedAdmin = User::factory()->admin()->suspended()->create();
    $inactiveAdmin = User::factory()->admin()->inactive()->create();

    app(IncidentService::class)->createOnline($reporter, notificationIncidentData());

    Notification::assertSentTo([$admin, $encoder, $superadmin], IncidentReported::class);
    Notification::assertNotSentTo(
        [$responder, $otherCommunityUser, $suspendedAdmin, $inactiveAdmin, $reporter],
        IncidentReported::class,
    );
});

test('an operations user is not notified about their own report', function () {
    Notification::fake();
    Queue::fake([SendSms::class]);

    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    app(IncidentService::class)->createOnline($admin, notificationIncidentData());

    Notification::assertSentTo($otherAdmin, IncidentReported::class);
    Notification::assertNotSentTo($admin, IncidentReported::class);
});

test('an encoder is not notified about the caller-based report they filed', function () {
    Notification::fake();
    Queue::fake([SendSms::class]);

    $encoder = User::factory()->encoder()->create();
    $admin = User::factory()->admin()->create();

    app(IncidentService::class)->createCallerBased($encoder, notificationIncidentData([
        'caller_name' => 'Maria Santos',
        'caller_contact' => '09179876543',
    ]));

    Notification::assertSentTo($admin, IncidentReported::class);
    Notification::assertNotSentTo($encoder, IncidentReported::class);
});

test('assigning a unit alerts operations, but re-assigning the same unit does not', function () {
    Notification::fake();
    Queue::fake([SendSms::class]);

    $admin = User::factory()->admin()->create();
    $colleague = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->assignedTo('Fire Station 1')->create();

    app(IncidentService::class)->assignUnit($admin, $incident, 'Fire Station 1');

    Notification::assertNothingSent();

    app(IncidentService::class)->assignUnit($admin, $incident, 'Fire Station 2');

    Notification::assertSentTo($colleague, IncidentAssigned::class);
    Notification::assertNotSentTo($admin, IncidentAssigned::class);
    expect($incident->refresh()->assigned_unit)->toBe('Fire Station 2');
});

test('a status transition alerts operations, but re-applying the current status does not', function () {
    Notification::fake();
    Queue::fake([SendSms::class]);

    $admin = User::factory()->admin()->create();
    $colleague = User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    app(IncidentService::class)->updateStatus($admin, $incident, IncidentStatus::Verified);

    Notification::assertNothingSent();

    app(IncidentService::class)->updateStatus($admin, $incident, IncidentStatus::Ongoing);

    Notification::assertSentTo($colleague, IncidentStatusChanged::class);
    Notification::assertNotSentTo($admin, IncidentStatusChanged::class);
});

test('verification produces exactly one status alert even when it also assigns a unit', function () {
    Notification::fake();
    Queue::fake([SendSms::class]);

    $admin = User::factory()->admin()->create();
    $colleague = User::factory()->admin()->create();
    $incident = Incident::factory()->underVerification()->create();

    app(IncidentService::class)->processVerification($admin, $incident, true, null, 'Fire Station 1');

    Notification::assertSentToTimes($colleague, IncidentStatusChanged::class, 1);
    Notification::assertNotSentTo($colleague, IncidentAssigned::class);
    Notification::assertNotSentTo($colleague, IncidentReported::class);
    expect($incident->refresh()->assigned_unit)->toBe('Fire Station 1');
});

test('a rejected status is refused before anyone is alerted', function () {
    Notification::fake();
    Queue::fake([SendSms::class]);

    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    $incident = Incident::factory()->verified()->create();

    expect(fn () => app(IncidentService::class)
        ->updateStatus($admin, $incident, IncidentStatus::Rejected))
        ->toThrow(RuntimeException::class);

    Notification::assertNothingSent();
});

test('only operations roles can reach the notification pages', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();
    $admin->notify(new IncidentReported($incident));

    foreach ([User::factory()->communityUser()->create(), User::factory()->responder()->create()] as $user) {
        actingAs($user)->get(route('notifications.index'))->assertForbidden();
        actingAs($user)->getJson(route('notifications.unread-count'))->assertForbidden();
        actingAs($user)->post(route('notifications.read-all'))->assertForbidden();
    }

    actingAs($admin)->get(route('notifications.index'))->assertOk();
});

test('a user cannot mark someone else notification as read', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $incident = Incident::factory()->create();
    $owner->notify(new IncidentReported($incident));

    $notificationId = $owner->notifications()->sole()->id;

    actingAs($other)->post(route('notifications.read', $notificationId))->assertNotFound();

    expect($owner->notifications()->sole()->read_at)->toBeNull();
});

test('the feed endpoint reports the unread count and the alerts behind it', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();

    actingAs($admin)
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertExactJson(['unread' => 0, 'notifications' => []]);

    $admin->notify(new IncidentReported($incident));

    actingAs($admin)
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('notifications.0.title', 'New incident reported')
        ->assertJsonPath('notifications.0.readAt', null)
        ->assertJsonPath('notifications.0.url', route('dashboard.incidents.show', $incident))
        ->assertJsonStructure(['notifications' => [['id', 'title', 'body', 'url', 'readAt', 'createdAt']]]);

    actingAs($admin)
        ->postJson(route('notifications.read', $admin->notifications()->sole()->id))
        ->assertOk()
        ->assertExactJson(['unread' => 0]);

    actingAs($admin)
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('notifications.0.readAt', fn (?string $readAt) => $readAt !== null);
});

test('the feed endpoint is capped so the bell cannot grow without bound', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();

    for ($i = 0; $i < NotificationFeed::LIMIT + 4; $i++) {
        $admin->notify(new IncidentAssigned($incident, 'Fire Station '.$i));
    }

    actingAs($admin)
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJsonPath('unread', NotificationFeed::LIMIT + 4)
        ->assertJsonCount(NotificationFeed::LIMIT, 'notifications');
});

test('the bell dropdown ships its alerts inline so it never renders empty', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();
    $admin->notify(new IncidentReported($incident));

    actingAs($admin)
        ->get(route('dashboard.incidents'))
        ->assertOk()
        ->assertSee('data-notification-items', escape: false)
        ->assertSee('New incident reported', escape: false)
        ->assertSee('Mark all read', escape: false)
        ->assertSee('View all notifications')
        ->assertSee('data-read-url-template="'.route('notifications.read', ['notification' => '__ID__']).'"', escape: false)
        ->assertSee('data-csrf-token="', escape: false);
});

test('the bell escapes alert copy rather than trusting it as markup', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();
    $incident->update(['location_label' => '<script>alert(1)</script>']);

    $admin->notify(new IncidentReported($incident->refresh()));

    actingAs($admin)
        ->get(route('dashboard.incidents'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

test('marking all as read clears every unread alert for the user', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();
    $admin->notify(new IncidentReported($incident));
    $admin->notify(new IncidentAssigned($incident, 'Fire Station 1'));

    actingAs($admin)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($admin->unreadNotifications()->count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(2);
});

test('the alert bell is shown to operations roles only', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create();
    $admin->notify(new IncidentReported($incident));

    actingAs($admin)
        ->get(route('dashboard.incidents'))
        ->assertOk()
        ->assertSee('data-unread-url="'.route('notifications.unread-count').'"', escape: false);

    actingAs(User::factory()->communityUser()->create())
        ->get(route('my-reports'))
        ->assertOk()
        ->assertDontSee('data-unread-url', escape: false);

    actingAs(User::factory()->responder()->create())
        ->get(route('dashboard.incidents'))
        ->assertOk()
        ->assertDontSee('data-unread-url', escape: false);
});

test('the notification history lists alerts with their incident link', function () {
    $admin = User::factory()->admin()->create();
    $incident = Incident::factory()->create(['incident_type' => IncidentType::Flood]);
    $admin->notify(new IncidentReported($incident));

    actingAs($admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('New incident reported')
        ->assertSee(route('dashboard.incidents.show', $incident), escape: false);
});

test('the notification history shows an empty state with nothing to read', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('No alerts yet.')
        ->assertDontSee('Mark all as read');
});
