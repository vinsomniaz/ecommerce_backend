<?php
// app/Exceptions/ProductExceptions.php

namespace App\Exceptions\Products;

use Exception;

class InvalidProductDataException extends Exception
{
    public function __construct(string $message = 'Datos de producto inválidos')
    {
        parent::__construct($message, 422);
    }
}
