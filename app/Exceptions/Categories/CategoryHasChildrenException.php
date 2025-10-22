<?php

namespace App\Exceptions\Categories;

use Exception;
use Illuminate\Http\JsonResponse;

class CategoryHasChildrenException extends Exception
{
    protected $message = 'No se puede eliminar una categoría que tiene subcategorías';
    protected $code = 409; // Conflict

    public function __construct(?string $message = null, ?int $code = null)
    {
        parent::__construct($message ?? $this->message, $code ?? $this->code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error_code' => 'CATEGORY_HAS_CHILDREN'
        ], $this->code);
    }
}
