<?php

namespace App\Http\Resources\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'initials' => $this->getInitials(),
            'email' => $this->email,
            'cellphone' => $this->cellphone,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'is_active' => $this->is_active,

            // Role info
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'role' => $this->whenLoaded('roles', function () {
                return $this->roles->first()?->name;
            }),

            // Warehouse (if vendor)
            'warehouse' => $this->when(
                $this->relationLoaded('warehouse') && $this->warehouse,
                function () {
                    return [
                        'id' => $this->warehouse->id,
                        'name' => $this->warehouse->name,
                    ];
                }
            ),

            // Commission (if applicable)
            'commission_percentage' => $this->commission_percentage,

            // Avatar
            'avatar' => $this->getAvatarFormatted(),

            // Timestamps
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get user initials for fallback avatar
     */
    private function getInitials(): string
    {
        $first = $this->first_name ? mb_strtoupper(mb_substr($this->first_name, 0, 1)) : '';
        $last = $this->last_name ? mb_strtoupper(mb_substr($this->last_name, 0, 1)) : '';
        return $first . $last;
    }

    /**
     * Get avatar URLs formatted
     */
    private function getAvatarFormatted(): ?array
    {
        $media = $this->getFirstMedia('avatar');

        if (!$media) {
            return null;
        }

        return [
            'id' => $media->id,
            'original_url' => $media->getUrl(),
            'thumb_url' => $media->getUrl('thumb'),
            'medium_url' => $media->getUrl('medium'),
        ];
    }
}
