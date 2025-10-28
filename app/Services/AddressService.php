<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Entity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /**
     * Listar todas las direcciones de una entidad
     */
    public function listForEntity(int $entityId): Collection
    {
        return Address::where('entity_id', $entityId)
            ->with('ubigeoData')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Crear una nueva dirección para una entidad
     */
    public function createForEntity(Entity $entity, array $data): Address
    {
        $isFirstAddress = !$entity->addresses()->exists();
        $isDefault = $data['is_default'] ?? false;

        if ($isFirstAddress) {
            $isDefault = true;
        }

        // NUEVO: Lógica de País/Ubigeo
        $data['country_code'] = $data['country_code'] ?? 'PE';
        if ($data['country_code'] !== 'PE') {
            $data['ubigeo'] = null;
        }

        $data['is_default'] = $isDefault;
        $data['entity_id'] = $entity->id;

        $address = DB::transaction(function () use ($data, $isDefault, $entity) {
            $address = Address::create($data);

            // Si esta es la nueva predeterminada, desmarcar las otras
            if ($isDefault) {
                Address::where('entity_id', $entity->id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            return $address;
        });

        return $address->load(['ubigeoData', 'country']);
    }

    /**
     * Actualizar una dirección
     */
    public function update(Address $address, array $data): Address
    {
        // NUEVO: Lógica de País/Ubigeo
        $countryCode = $data['country_code'] ?? $address->country_code;
        if ($countryCode !== 'PE') {
            $data['ubigeo'] = null;
        }

        $address->update($data);

        // Si se intentó marcar como predeterminada desde el update,
        // se ejecuta la lógica completa para asegurar que sea la única.
        if (isset($data['is_default']) && $data['is_default'] === true) {
            return $this->setDefault($address);
        }

        return $address->fresh(['ubigeoData', 'country']);
    }

    /**
     * Eliminar una dirección
     */
    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $entityId = $address->entity_id;

            $address->delete();

            // Si la dirección eliminada era la predeterminada,
            // se asigna una nueva predeterminada si quedan direcciones.
            if ($wasDefault) {
                $newDefault = Address::where('entity_id', $entityId)->first();
                if ($newDefault) {
                    $this->setDefault($newDefault);
                }
            }
        });
    }

    /**
     * Marcar una dirección como predeterminada
     */
    public function setDefault(Address $address): Address
    {
        DB::transaction(function () use ($address) {
            // Desmarcar todas las direcciones de esta entidad
            Address::where('entity_id', $address->entity_id)
                ->update(['is_default' => false]);

            // Marcar la nueva como default
            $address->update(['is_default' => true]);
        });

        return $address->load('ubigeoData');
    }
}
