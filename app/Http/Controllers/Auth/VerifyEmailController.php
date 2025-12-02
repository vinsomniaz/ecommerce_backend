<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request, $id, $hash): JsonResponse
    {
        // 1. Buscar al usuario
        $user = User::findOrFail($id);

        // 2. Validar que el hash del correo coincida (Seguridad)
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'El enlace de verificación no es válido.'], 403);
        }

        // 3. Validar la firma temporal (expires & signature) que viene en la URL
        // Esto asegura que el link no haya sido alterado y no haya expirado.
        if (! $request->hasValidSignature()) {
             // Nota: Para que esto funcione, el Frontend debe enviar los parámetros 'expires' y 'signature'
             // tal cual los recibió en el correo.
             return response()->json(['message' => 'El enlace ha expirado o es inválido.'], 403);
        }

        // 4. Verificar si ya estaba verificado
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'El correo ya ha sido verificado anteriormente.'], 200);
        }

        // 5. Marcar como verificado
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Correo verificado exitosamente.']);
    }
}