<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notes\StoreNoteRequest;
use App\Http\Requests\Notes\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Reference CRUD example (schema -> validation -> persistence). Copy this
 * shape for a real feature, then delete it.
 */
#[OA\Tag(name: 'Notes')]
class NotesController extends Controller
{
    #[OA\Get(
        path: '/api/notes',
        summary: 'List notes for the current user',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        responses: [new OA\Response(response: 200, description: 'Notes list')],
    )]
    public function index(Request $request): JsonResponse
    {
        $notes = $request->user()->notes()->latest()->get();

        return response()->json(['notes' => NoteResource::collection($notes)]);
    }

    #[OA\Post(
        path: '/api/notes',
        summary: 'Create a note',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'content', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [new OA\Response(response: 201, description: 'Note created')],
    )]
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $request->user()->notes()->create($request->validated());

        return response()->json(['note' => new NoteResource($note)], 201);
    }

    #[OA\Get(
        path: '/api/notes/{note}',
        summary: 'Get a note by id',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        parameters: [new OA\Parameter(name: 'note', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Note found'),
            new OA\Response(response: 404, description: 'Note not found'),
        ],
    )]
    public function show(Request $request, int $note): JsonResponse
    {
        $note = $this->findOwnedNote($request, $note);

        return response()->json(['note' => new NoteResource($note)]);
    }

    #[OA\Put(
        path: '/api/notes/{note}',
        summary: 'Update a note',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        parameters: [new OA\Parameter(name: 'note', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'content', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Note updated'),
            new OA\Response(response: 404, description: 'Note not found'),
        ],
    )]
    public function update(UpdateNoteRequest $request, int $note): JsonResponse
    {
        $note = $this->findOwnedNote($request, $note);
        $note->update($request->validated());

        return response()->json(['note' => new NoteResource($note)]);
    }

    #[OA\Delete(
        path: '/api/notes/{note}',
        summary: 'Delete a note',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        parameters: [new OA\Parameter(name: 'note', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Note deleted'),
            new OA\Response(response: 404, description: 'Note not found'),
        ],
    )]
    public function destroy(Request $request, int $note): JsonResponse
    {
        $this->findOwnedNote($request, $note)->delete();

        return response()->json(['message' => 'Note deleted.']);
    }

    /**
     * Scoped to the authenticated user — a note that exists but belongs to
     * someone else 404s exactly like one that doesn't exist (no leak).
     */
    private function findOwnedNote(Request $request, int $id): Note
    {
        return $request->user()->notes()->findOrFail($id);
    }
}
