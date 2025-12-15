<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Listar contactos de una entidad
     */
    public function index(Entity $entity): JsonResponse
    {
        $contacts = $entity->contacts()->get();
        
        return response()->json([
            'success' => true,
            'data' => ContactResource::collection($contacts)
        ]);
    }

    /**
     * Crear un nuevo contacto para una entidad
     */
    public function store(Request $request, Entity $entity): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:200',
            'position' => 'nullable|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'web_page' => 'nullable|string|max:100',
            'observations' => 'nullable|string|max:1000',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Si se marca como primary, desmarcar los demás
        if ($data['is_primary'] ?? false) {
            $entity->contacts()->update(['is_primary' => false]);
        }

        $contact = $entity->contacts()->create($data);

        return response()->json([
            'message' => 'Contacto agregado exitosamente',
            'data' => new ContactResource($contact)
        ], 201);
    }

    /**
     * Mostrar un contacto específico
     */
    public function show(Contact $contact): JsonResponse
    {
        return response()->json([
            'data' => new ContactResource($contact)
        ]);
    }

    /**
     * Actualizar un contacto
     */
    public function update(Request $request, Entity $entity, Contact $contact): JsonResponse
    {
        // Verificar que el contacto pertenece a la entidad
        if ($contact->entity_id !== $entity->id) {
            return response()->json([
                'message' => 'Este contacto no pertenece a la entidad especificada'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:200',
            'position' => 'nullable|string|max:100',
            'email' => 'sometimes|email|max:100',
            'phone' => 'nullable|string|max:20',
            'web_page' => 'nullable|string|max:100',
            'observations' => 'nullable|string|max:1000',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Si se marca como primary, desmarcar los demás
        if (($data['is_primary'] ?? false) && !$contact->is_primary) {
            $entity->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }

        $contact->update($data);

        return response()->json([
            'message' => 'Contacto actualizado exitosamente',
            'data' => new ContactResource($contact->fresh())
        ]);
    }

    /**
     * Eliminar un contacto
     */
    public function destroy(Entity $entity, Contact $contact): JsonResponse
    {
        // Verificar que el contacto pertenece a la entidad
        if ($contact->entity_id !== $entity->id) {
            return response()->json([
                'message' => 'Este contacto no pertenece a la entidad especificada'
            ], 403);
        }

        // No permitir eliminar si es el único contacto
        if ($entity->contacts()->count() === 1) {
            return response()->json([
                'message' => 'No se puede eliminar el único contacto de la entidad'
            ], 422);
        }

        // Si era el primary, marcar otro como primary
        if ($contact->is_primary) {
            $newPrimary = $entity->contacts()->where('id', '!=', $contact->id)->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        $contact->delete();

        return response()->json([
            'message' => 'Contacto eliminado exitosamente'
        ]);
    }

    /**
     * Marcar un contacto como principal
     */
    public function setPrimary(Entity $entity, Contact $contact): JsonResponse
    {
        // Verificar que el contacto pertenece a la entidad
        if ($contact->entity_id !== $entity->id) {
            return response()->json([
                'message' => 'Este contacto no pertenece a la entidad especificada'
            ], 403);
        }

        // Desmarcar todos los demás
        $entity->contacts()->update(['is_primary' => false]);
        
        // Marcar este como primary
        $contact->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Contacto marcado como principal exitosamente',
            'data' => new ContactResource($contact->fresh())
        ]);
    }
}
