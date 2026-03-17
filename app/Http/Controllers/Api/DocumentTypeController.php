<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    /**
     * List active document types (for dropdowns when registering control numbers).
     */
    public function index(): JsonResponse
    {
        $types = DocumentType::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($types);
    }

    /**
     * List all document types (admin only) for management.
     */
    public function indexAll(): JsonResponse
    {
        $types = DocumentType::orderBy('name')->get(['id', 'name', 'code', 'is_active']);
        return response()->json(['data' => $types]);
    }

    /**
     * Create a document type (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $type = DocumentType::create($validated);
        return response()->json(['message' => 'Document type created.', 'data' => $type], 201);
    }

    /**
     * Update a document type (admin only).
     */
    public function update(Request $request, DocumentType $documentType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $documentType->update($validated);

        return response()->json(['message' => 'Document type updated.', 'data' => $documentType->fresh()]);
    }
}
