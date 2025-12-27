<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProfileService
{
    /**
     * Get user profile with avatar
     */
    public function getProfile(User $user): User
    {
        return $user->load(['roles', 'warehouse', 'media']);
    }

    /**
     * Update user profile data
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // Validate unique email if changed
            if (isset($data['email']) && $data['email'] !== $user->email) {
                $this->validateUniqueEmail($data['email'], $user->id);
            }

            $user->update($data);

            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['changed_fields' => array_keys($data)])
                ->log('Perfil actualizado');

            return $user->fresh(['roles', 'warehouse', 'media']);
        });
    }

    /**
     * Update user password
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            throw new \InvalidArgumentException('La contraseña actual es incorrecta');
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log('Contraseña actualizada');

        return true;
    }

    /**
     * Upload or update user avatar
     */
    public function updateAvatar(User $user, UploadedFile $image): array
    {
        return DB::transaction(function () use ($user, $image) {
            // Generate unique filename
            $fileName = sprintf(
                'avatar-%s-%s',
                Str::slug($user->full_name),
                time()
            );

            // Clear existing avatar (singleFile collection handles this, but explicit is better)
            $user->clearMediaCollection('avatar');

            // Add new avatar
            $media = $user->addMedia($image)
                ->usingName($user->full_name)
                ->usingFileName($fileName . '.' . $image->getClientOriginalExtension())
                ->toMediaCollection('avatar', 'public');

            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['media_id' => $media->id])
                ->log('Avatar actualizado');

            return [
                'id' => $media->id,
                'original_url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
                'medium_url' => $media->getUrl('medium'),
            ];
        });
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar(User $user): bool
    {
        $user->clearMediaCollection('avatar');

        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log('Avatar eliminado');

        return true;
    }

    /**
     * Validate email is unique
     */
    private function validateUniqueEmail(string $email, int $excludeId): void
    {
        $exists = User::where('email', $email)
            ->where('id', '!=', $excludeId)
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('El email ya está en uso por otro usuario');
        }
    }

    /**
     * Check if user is ERP user (not customer)
     */
    public function isErpUser(User $user): bool
    {
        return !$user->hasRole('customer');
    }
}
