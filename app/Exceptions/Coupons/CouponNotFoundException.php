<?php

namespace App\Exceptions\Coupons;

use Exception;

class CouponNotFoundException extends Exception
{
    protected $code = 404;

    public function __construct(string $message = 'Cupón no encontrado')
    {
        parent::__construct($message, $this->code);
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->code);
    }
}
