<?php

use App\Enums\AnnouncementCategory;
use App\Enums\Severity;
use App\Models\Announcement;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

$payload = [
    'title' => 'Typhoon Prep Alert',
    'content' => 'Residents of Camalaniugan are advised to prepare emergency kits ahead of the approaching storm.',
    'category' => AnnouncementCategory::Warning->value,
    'severity' => Severity::Urgent->value,
];

test('the public announcement list only shows currently active announcements', function () {
    Announcement::factory()->create(['title' => 'Active Notice']);
    Announcement::factory()->expired()->create(['title' => 'Expired Notice']);

    getJson('/api/v1/announcements')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Active Notice');
});

test('an announcement can be filtered by category', function () {
    Announcement::factory()->create(['category' => AnnouncementCategory::Warning]);
    Announcement::factory()->create(['category' => AnnouncementCategory::Advisory]);

    getJson('/api/v1/announcements?category=warning')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});

test('an expired announcement is hidden from the public show endpoint', function () {
    $announcement = Announcement::factory()->expired()->create();

    getJson("/api/v1/announcements/{$announcement->id}")
        ->assertStatus(404);
});

test('an operator can create an announcement', function () use ($payload) {
    $admin = User::factory()->admin()->create();

    actingAs($admin, 'sanctum')
        ->postJson('/api/v1/announcements', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'Typhoon Prep Alert')
        ->assertJsonPath('data.category', AnnouncementCategory::Warning->value);
});

test('a community user cannot create announcements', function () use ($payload) {
    $user = User::factory()->communityUser()->create();

    actingAs($user, 'sanctum')
        ->postJson('/api/v1/announcements', $payload)
        ->assertStatus(403);
});

test('an operator can update and delete an announcement', function () use ($payload) {
    $admin = User::factory()->admin()->create();
    $announcement = Announcement::factory()->create();

    actingAs($admin, 'sanctum')
        ->patchJson("/api/v1/announcements/{$announcement->id}", [
            ...$payload,
            'title' => 'Updated Title',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated Title');

    actingAs($admin, 'sanctum')
        ->deleteJson("/api/v1/announcements/{$announcement->id}")
        ->assertStatus(200);

    expect(Announcement::find($announcement->id))->toBeNull();
});

test('announcement validation rejects invalid categories', function () use ($payload) {
    $admin = User::factory()->admin()->create();

    actingAs($admin, 'sanctum')
        ->postJson('/api/v1/announcements', [
            ...$payload,
            'category' => 'not-a-category',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['category']]);
});
