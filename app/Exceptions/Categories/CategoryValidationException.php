<?php

namespace App\Exceptions\Categories;

use Exception;
use Illuminate\Http\JsonResponse;

class CategoryValidationException extends Exception
{
    protected $message = 'Error de validación en categoría';
    protected $code = 422;
    protected array $errors;

    public function __construct(?string $message = null, array $errors = [], ?int $code = null)
    {
        $this->errors = $errors;
        parent::__construct($message ?? $this->message, $code ?? $this->code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'errors' => $this->errors,
            'error_code' => 'CATEGORY_VALIDATION_ERROR'
        ], $this->code);
    }
}
