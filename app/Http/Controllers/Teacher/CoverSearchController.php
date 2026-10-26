<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Support\CoverSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoverSearchController extends Controller
{
    /**
     * "Get cover image from web" — the teacher never types keywords;
     * the story's own title and author are the query. The candidates
     * are transient, never stored; the teacher picks one and it fills
     * the existing cover URL field.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $results = CoverSearch::search(
                trim($validated['title']),
                trim((string) ($validated['author'] ?? '')),
            );
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }
}
