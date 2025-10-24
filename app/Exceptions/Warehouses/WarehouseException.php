<?php

namespace App\Exceptions\Warehouses;

use Exception;
use Illuminate\Http\JsonResponse;

class WarehouseException extends Exception
{
    protected $code;
    protected $statusCode;

    public function __construct(string $message, int $statusCode = 400, string $code = 'WAREHOUSE_ERROR')
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->code = $code;
    }

    public static function notFound(int $id): self
    {
        return new self(
            "Almacén con ID {$id} no encontrado",
            404,
            'WAREHOUSE_NOT_FOUND'
        );
    }

    public static function invalidUbigeo(string $ubigeo): self
    {
        return new self(
            "El ubigeo '{$ubigeo}' no es válido o no existe",
            422,
            'INVALID_UBIGEO'
        );
    }

    public static function duplicateName(string $name): self
    {
        return new self(
            "Ya existe un almacén con el nombre '{$name}'",
            422,
            'DUPLICATE_WAREHOUSE_NAME'
        );
    }

    public static function cannotDeleteWithInventory(int $id): self
    {
        return new self(
            "No se puede eliminar el almacén con ID {$id} porque tiene inventario asociado",
            422,
            'WAREHOUSE_HAS_INVENTORY'
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->code,
                'message' => $this->getMessage(),
            ],
        ], $this->statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
