<?php
// app/Exceptions/ProductExceptions.php

namespace App\Exceptions\Products;

use Exception;

class ProductNotFoundException extends Exception
{
    public function __construct(string $identifier = '')
    {
        $message = $identifier
            ? "Producto '{$identifier}' no encontrado"
            : "Producto no encontrado";
        parent::__construct($message, 404);
    }
}
