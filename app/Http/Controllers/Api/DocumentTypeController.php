<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentType\DocumentTypeResource;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;

class DocumentTypeController extends Controller
{
    /**
     * Display a listing of active document types.
     */
    public function index(): JsonResponse
    {
        $documentTypes = DocumentType::active()->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => DocumentTypeResource::collection($documentTypes),
        ]);
    }

    /**
     * Display the specified document type.
     */
    public function show(string $code): JsonResponse
    {
        $documentType = DocumentType::findOrFail($code);

        return response()->json([
            'success' => true,
            'data' => new DocumentTypeResource($documentType),
        ]);
    }
}