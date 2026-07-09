<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
    $this->auth = fn () => $this->withHeader('Authorization', "Bearer {$this->token}");
});

it('runs the full CRUD lifecycle for the owner', function () {
    ($this->auth)()->getJson('/api/notes/')->assertOk()->assertJsonPath('notes', []);

    ($this->auth)()->postJson('/api/notes/', [])->assertStatus(422);

    $created = ($this->auth)()->postJson('/api/notes/', [
        'title' => 'Groceries',
        'content' => 'milk, eggs',
    ])->assertCreated()->assertJsonPath('note.title', 'Groceries');

    $id = $created->json('note.id');

    ($this->auth)()->getJson("/api/notes/{$id}")->assertOk()->assertJsonPath('note.content', 'milk, eggs');

    ($this->auth)()->putJson("/api/notes/{$id}", ['content' => 'milk, eggs, bread'])
        ->assertOk()->assertJsonPath('note.content', 'milk, eggs, bread');

    ($this->auth)()->deleteJson("/api/notes/{$id}")->assertOk();
    ($this->auth)()->getJson("/api/notes/{$id}")->assertStatus(404);
});

it("404s (not 403) on another user's note — no existence leak", function () {
    $stranger = User::factory()->create();
    $note = Note::factory()->for($stranger)->create();

    ($this->auth)()->getJson("/api/notes/{$note->id}")->assertStatus(404);
    ($this->auth)()->putJson("/api/notes/{$note->id}", ['title' => 'x'])->assertStatus(404);
    ($this->auth)()->deleteJson("/api/notes/{$note->id}")->assertStatus(404);

    expect(Note::find($note->id))->not->toBeNull();
});
