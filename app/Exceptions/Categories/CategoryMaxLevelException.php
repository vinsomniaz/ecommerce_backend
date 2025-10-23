<?php

namespace App\Exceptions\Categories;

use Exception;
use Illuminate\Http\JsonResponse;

class CategoryMaxLevelException extends Exception
{
    protected $message = 'No se puede crear una categoría de nivel 4 o superior';
    protected $code = 422;

    public function __construct(?string $message = null, ?int $code = null)
    {
        parent::__construct($message ?? $this->message, $code ?? $this->code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error_code' => 'CATEGORY_MAX_LEVEL_EXCEEDED'
        ], $this->code);
    }
}
