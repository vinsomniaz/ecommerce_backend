<?php

namespace App\Exceptions\Coupons;

use Exception;

class CouponExpiredException extends Exception
{
    protected $code = 422;

    public function __construct(string $message = 'El cupón ha expirado o no está vigente')
    {
        parent::__construct($message, $this->code);
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'COUPON_EXPIRED',
        ], $this->code);
    }
}
