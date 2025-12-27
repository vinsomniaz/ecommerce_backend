<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Profile\ProfileResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    /**
     * Get authenticated user's profile
     *
     * @group Profile
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();

        // Verify is ERP user (not customer)
        if (!$this->profileService->isErpUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Los clientes no tienen acceso a esta funcionalidad',
            ], 403);
        }

        $profile = $this->profileService->getProfile($user);

        return response()->json([
            'success' => true,
            'data' => new ProfileResource($profile),
        ]);
    }

    /**
     * Update authenticated user's profile
     *
     * @group Profile
     * @bodyParam first_name string optional Nombre. Example: Juan
     * @bodyParam last_name string optional Apellido. Example: Pérez
     * @bodyParam cellphone string optional Teléfono celular. Example: 987654321
     * @bodyParam email string optional Email del usuario. Example: juan@example.com
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Verify is ERP user
        if (!$this->profileService->isErpUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Los clientes no tienen acceso a esta funcionalidad',
            ], 403);
        }

        try {
            $profile = $this->profileService->updateProfile($user, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente',
                'data' => new ProfileResource($profile),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update authenticated user's password
     *
     * @group Profile
     * @bodyParam current_password string required Contraseña actual. Example: oldpassword123
     * @bodyParam password string required Nueva contraseña (mínimo 8 caracteres). Example: newpassword123
     * @bodyParam password_confirmation string required Confirmación de nueva contraseña. Example: newpassword123
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Verify is ERP user
        if (!$this->profileService->isErpUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Los clientes no tienen acceso a esta funcionalidad',
            ], 403);
        }

        try {
            $this->profileService->updatePassword(
                $user,
                $request->current_password,
                $request->password
            );

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Upload or update avatar
     *
     * @group Profile
     * @bodyParam avatar file required Imagen de avatar (jpg, png, webp, max 2MB). No-example
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Verify is ERP user
        if (!$this->profileService->isErpUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Los clientes no tienen acceso a esta funcionalidad',
            ], 403);
        }

        try {
            $avatar = $this->profileService->updateAvatar($user, $request->file('avatar'));

            return response()->json([
                'success' => true,
                'message' => 'Avatar actualizado exitosamente',
                'data' => [
                    'avatar' => $avatar,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el avatar: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete avatar
     *
     * @group Profile
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = auth()->user();

        // Verify is ERP user
        if (!$this->profileService->isErpUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Los clientes no tienen acceso a esta funcionalidad',
            ], 403);
        }

        $this->profileService->deleteAvatar($user);

        return response()->json([
            'success' => true,
            'message' => 'Avatar eliminado exitosamente',
        ]);
    }
}
