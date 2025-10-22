<?php

namespace App\Exceptions\Categories;

use Exception;
use Illuminate\Http\JsonResponse;

class CategoryNotFoundException extends Exception
{
    protected $message = 'Categoría no encontrada';
    protected $code = 404;

    public function __construct(?string $message = null, ?int $code = null)
    {
        parent::__construct($message ?? $this->message, $code ?? $this->code);
    }

    /**
     * Laravel 11/12: Este método se ejecuta automáticamente
     * NO necesitas registrarlo en Handler.php
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error_code' => 'CATEGORY_NOT_FOUND'
        ], $this->code);
    }
}
