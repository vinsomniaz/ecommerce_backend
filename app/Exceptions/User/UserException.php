<?php

namespace App\Exceptions\User;

use Exception;

class UserException extends Exception
{
    /**
     * Usuario no encontrado
     */
    public static function notFound(int $id): self
    {
        return new self(
            "Usuario con ID {$id} no encontrado",
            404
        );
    }

    /**
     * Usuario no encontrado en papelera
     */
    public static function notFoundInTrash(int $id): self
    {
        return new self(
            "Usuario con ID {$id} no encontrado en papelera",
            404
        );
    }

    /**
     * Email duplicado
     */
    public static function duplicateEmail(string $email): self
    {
        return new self(
            "El email {$email} ya está en uso por otro usuario",
            422
        );
    }

    /**
     * No puede eliminarse a sí mismo
     */
    public static function cannotDeleteSelf(): self
    {
        return new self(
            "No puedes eliminar tu propia cuenta",
            403
        );
    }

    /**
     * No puede desactivarse a sí mismo
     */
    public static function cannotDeactivateSelf(): self
    {
        return new self(
            "No puedes desactivar tu propia cuenta",
            403
        );
    }

    /**
     * Usuario tiene registros relacionados
     */
    public static function hasRelatedRecords(int $id, string $recordType = 'registros'): self
    {
        return new self(
            "No se puede eliminar el usuario con ID {$id} porque tiene {$recordType} relacionados",
            422
        );
    }

    /**
     * Rol inválido
     */
    public static function invalidRole(string $role): self
    {
        return new self(
            "El rol '{$role}' no es válido",
            422
        );
    }

    /**
     * Sin permisos para la acción
     */
    public static function unauthorized(string $action = 'realizar esta acción'): self
    {
        return new self(
            "No tienes permisos para {$action}",
            403
        );
    }
}
