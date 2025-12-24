<?php

namespace App\Exceptions;

use Exception;

class QuotationNotEditableException extends Exception
{
    public function __construct(string $message = 'La cotización no puede ser editada')
    {
        parent::__construct($message);
    }
}
