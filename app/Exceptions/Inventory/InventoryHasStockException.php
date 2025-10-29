<?php
// app/Exceptions/Inventory/InventoryHasStockException.php

namespace App\Exceptions\Inventory;

use Exception;

class InventoryHasStockException extends Exception
{
    public function __construct(string $message = 'El inventario tiene stock y no puede ser eliminado')
    {
        parent::__construct($message, 422);
    }
}
