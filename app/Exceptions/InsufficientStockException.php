<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(
        string $message,
        private int $requestedQuantity,
        private int $availableStock
    ) {
        parent::__construct($message);
    }
    
    public function render()
    {
        return response()->json([
            'error' => 'insufficient_stock',
            'message' => $this->getMessage(),
            'requested' => $this->requestedQuantity,
            'available' => $this->availableStock,
        ], 422);
    }
}