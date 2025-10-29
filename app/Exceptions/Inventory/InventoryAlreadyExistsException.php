<?php
// app/Exceptions/Inventory/InventoryAlreadyExistsException.php

namespace App\Exceptions\Inventory;

use Exception;

class InventoryAlreadyExistsException extends Exception
{
    public function __construct(int $productId, int $warehouseId)
    {
        $message = "El producto ID {$productId} ya está asignado al almacén ID {$warehouseId}";
        parent::__construct($message, 409);
    }
}
