<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\LogbookEntry;
use App\Services\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * Register a new control number (document).
     */
    public function store(Request $request): JsonResponse
    {
        // Admins are read-only for documents; only personnel can register control numbers.
        if ($request->user()->role === 'admin') {
            return response()->json([
                'message' => 'Administrators cannot register control numbers. This action is for personnel only.',
            ], 403);
        }

        $validated = $request->validate([
            'control_number' => ['required', 'string', 'max:255', 'unique:documents,control_number'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'description' => ['nullable', 'string', 'max:65535'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'date_prepared' => ['nullable', 'date'],
        ]);

        $document = Document::create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
            'status' => 'in_transit',
            'current_holder_user_id' => null,
        ]);

        $document->load(['documentType:id,name,code', 'createdBy:id,name,email,section_unit,designation_position']);

        AuditLog::log($request->user()->id, 'document_registered', $document->id, $document->control_number, [
            'document_type_id' => $document->document_type_id,
        ], $request->user()->email, $request);

        return response()->json($document, 201);
    }

    /**
     * List documents with optional filters.
     * Query params: document_type_id, status, control_number, created_by_me (1 = only documents I created).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Document::with([
            'documentType:id,name,code',
            'createdBy:id,name,email,section_unit,designation_position',
            'currentHolder:id,name,email,section_unit,designation_position',
        ]);

        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->document_type_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('control_number')) {
            $query->where('control_number', 'like', '%' . $request->control_number . '%');
        }
        if ($request->boolean('created_by_me')) {
            $query->where('created_by_user_id', $request->user()->id);
        }

        $documents = $query->orderByDesc('created_at')->paginate(
            $request->integer('per_page', 15)
        );

        return response()->json($documents);
    }

    /**
     * Get one document by id with full logbook.
     */
    public function show(Document $document): JsonResponse
    {
        $document->load([
            'documentType:id,name,code',
            'createdBy:id,name,email,section_unit,designation_position',
            'currentHolder:id,name,email,section_unit,designation_position',
            'logbookEntries' => fn ($q) => $q->with('user:id,name,email,section_unit,designation_position')->orderBy('moved_at', 'asc'),
        ]);

        return response()->json($document);
    }

    /**
     * Get one document by control number with full logbook.
     */
    public function showByControlNumber(string $controlNumber): JsonResponse
    {
        $document = Document::with([
            'documentType:id,name,code',
            'createdBy:id,name,email,section_unit,designation_position',
            'currentHolder:id,name,email,section_unit,designation_position',
            'logbookEntries' => fn ($q) => $q->with('user:id,name,email,section_unit,designation_position')->orderBy('moved_at', 'asc'),
        ])->where('control_number', $controlNumber)->firstOrFail();

        return response()->json($document);
    }

    /**
     * In: record that the authenticated user received this document.
     * Allowed only when document is in_transit (no current holder). Body: registration_details (optional), remarks (optional).
     */
    public function in(Request $request, Document $document): JsonResponse
    {
        // Admins are read-only for documents; only personnel can record In.
        if ($request->user()->role === 'admin') {
            return response()->json([
                'message' => 'Administrators cannot record In for documents. This action is for personnel only.',
            ], 403);
        }

        if ($document->current_holder_user_id !== null) {
            return response()->json([
                'message' => 'This document is currently with another person. They must record Out before you can record In.',
            ], 409);
        }

        $validated = $request->validate([
            'registration_details' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string', 'max:65535'],
        ]);

        $remarks = trim($validated['remarks'] ?? '');
        $registrationDetails = $validated['registration_details'] ?? [];
        if (is_array($registrationDetails)) {
            $registrationDetails = array_filter($registrationDetails, fn ($v) => $v !== null && $v !== '');
        } else {
            $registrationDetails = [];
        }
        if ($remarks !== '') {
            $registrationDetails['remarks'] = $remarks;
        }

        // Require at least remarks or registration details (e.g. date received) for accountability
        if ($remarks === '' && empty($registrationDetails)) {
            return response()->json([
                'message' => 'Please provide registration details (e.g. date received) or remarks when recording In.',
            ], 422);
        }

        $user = $request->user();

        $document->update([
            'current_holder_user_id' => $user->id,
            'status' => 'with_personnel',
        ]);

        LogbookEntry::create([
            'document_id' => $document->id,
            'action' => LogbookEntry::ACTION_IN,
            'user_id' => $user->id,
            'remarks' => $remarks !== '' ? $remarks : null,
            'registration_details' => $registrationDetails ?: null,
            'moved_at' => now(),
        ]);

        AuditLog::log($user->id, 'document_in', $document->id, $document->control_number, null, $user->email, $request);

        $document->load([
            'documentType:id,name,code',
            'createdBy:id,name,email,section_unit,designation_position',
            'currentHolder:id,name,email,section_unit,designation_position',
            'logbookEntries' => fn ($q) => $q->with('user:id,name,email,section_unit,designation_position')->orderBy('moved_at', 'asc'),
        ]);

        return response()->json($document);
    }

    /**
     * Out: record that the authenticated user released this document.
     * Allowed if: (1) current holder, or (2) admin, or (3) creator/owner when there is no current holder (first release after creation).
     * Body: remarks (optional).
     */
    public function out(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:65535'],
        ]);

        $user = $request->user();
        // Admins are read-only for documents; only personnel can record Out.
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Administrators cannot record Out for documents. This action is for personnel only.',
            ], 403);
        }

        $isCurrentHolder = $document->current_holder_user_id === (int) $user->id;
        // Owner may record Out as the first movement only if there are no logbook entries yet and no current holder.
        $hasAnyLogbook = $document->logbookEntries()->exists();
        $isCreatorReleasingFirst = $document->created_by_user_id === (int) $user->id
            && $document->current_holder_user_id === null
            && ! $hasAnyLogbook;

        // At this point admins are already blocked above, so only allow:
        // - the current holder, or
        // - the creator when releasing for the first time.
        if (! $isCurrentHolder && ! $isCreatorReleasingFirst) {
            return response()->json([
                'message' => 'Only the current holder or the document owner (when releasing it for the first time) may record Out.',
            ], 403);
        }

        $document->update([
            'current_holder_user_id' => null,
            'status' => 'in_transit',
        ]);

        LogbookEntry::create([
            'document_id' => $document->id,
            'action' => LogbookEntry::ACTION_OUT,
            'user_id' => $user->id,
            'remarks' => $validated['remarks'] ?? null,
            'registration_details' => null,
            'moved_at' => now(),
        ]);

        AuditLog::log($user->id, 'document_out', $document->id, $document->control_number, null, $user->email, $request);

        $document->load([
            'documentType:id,name,code',
            'createdBy:id,name,email,section_unit,designation_position',
            'currentHolder:id,name,email,section_unit,designation_position',
            'logbookEntries' => fn ($q) => $q->with('user:id,name,email,section_unit,designation_position')->orderBy('moved_at', 'asc'),
        ]);

        return response()->json($document);
    }
}
