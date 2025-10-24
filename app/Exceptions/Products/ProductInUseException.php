<?php
// app/Exceptions/ProductExceptions.php

namespace App\Exceptions\Products;

use Exception;

class ProductInUseException extends Exception
{
    public function __construct(string $reason = '')
    {
        $message = "No se puede eliminar el producto porque está siendo utilizado";
        if ($reason) {
            $message .= ": {$reason}";
        }
        parent::__construct($message, 422);
    }
}
