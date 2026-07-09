<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notes\StoreNoteRequest;
use App\Http\Requests\Notes\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reference CRUD example (schema -> validation -> persistence). Copy this
 * shape for a real feature, then delete it.
 */
class NotesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notes = $request->user()->notes()->latest()->get();

        return response()->json(['notes' => NoteResource::collection($notes)]);
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $request->user()->notes()->create($request->validated());

        return response()->json(['note' => new NoteResource($note)], 201);
    }

    public function show(Request $request, int $note): JsonResponse
    {
        $note = $this->findOwnedNote($request, $note);

        return response()->json(['note' => new NoteResource($note)]);
    }

    public function update(UpdateNoteRequest $request, int $note): JsonResponse
    {
        $note = $this->findOwnedNote($request, $note);
        $note->update($request->validated());

        return response()->json(['note' => new NoteResource($note)]);
    }

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
