<?php
// app/Exceptions/ProductExceptions.php

namespace App\Exceptions\Products;

use Exception;

class ProductAlreadyExistsException extends Exception
{
    public function __construct(string $sku)
    {
        parent::__construct("Ya existe un producto con el SKU: {$sku}", 409);
    }
}
