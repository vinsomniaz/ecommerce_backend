<?php

namespace App\Exceptions\Coupons;

use Exception;

class CouponMinAmountException extends Exception
{
    protected $code = 422;

    public function __construct(string $message = 'El monto de la compra no alcanza el mínimo requerido para este cupón')
    {
        parent::__construct($message, $this->code);
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'COUPON_MIN_AMOUNT',
        ], $this->code);
    }
}
