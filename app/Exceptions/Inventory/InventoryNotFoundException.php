<?php
// app/Exceptions/Inventory/InventoryNotFoundException.php

namespace App\Exceptions\Inventory;

use Exception;

class InventoryNotFoundException extends Exception
{
    public function __construct(string $message = 'Inventario no encontrado')
    {
        parent::__construct($message, 404);
    }
}
