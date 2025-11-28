<?php

namespace App\Exceptions;

use Exception;

class LowMarginException extends Exception
{
    public function __construct(
        string $message,
        private float $actualMargin,
        private float $minimumMargin
    ) {
        parent::__construct($message);
    }
    
    public function render()
    {
        return response()->json([
            'error' => 'low_margin',
            'message' => $this->getMessage(),
            'actual_margin' => $this->actualMargin,
            'minimum_margin' => $this->minimumMargin,
        ], 422);
    }
}
