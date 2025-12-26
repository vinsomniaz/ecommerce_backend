<?php

namespace App\Exceptions\Coupons;

use Exception;

class CouponUsageLimitException extends Exception
{
    protected $code = 422;

    public function __construct(string $message = 'El cupón ha alcanzado su límite de usos')
    {
        parent::__construct($message, $this->code);
    }

    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'COUPON_USAGE_LIMIT',
        ], $this->code);
    }
}
