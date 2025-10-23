<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Excepción genérica para toda la API
 * Maneja cualquier tipo de error con código y mensaje personalizados
 */
class ApiException extends Exception
{
    protected string $errorCode;
    protected array $errors;
    protected int $httpCode;

    /**
     * @param string $message Mensaje de error para el usuario
     * @param string $errorCode Código de error para el frontend
     * @param int $httpCode Código HTTP (400, 404, 422, 500, etc.)
     * @param array $errors Errores de validación (opcional)
     */
    public function __construct(
        string $message = 'Ha ocurrido un error',
        string $errorCode = 'ERROR',
        int $httpCode = 500,
        array $errors = []
    ) {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->httpCode = $httpCode;
        $this->errors = $errors;
    }

    /**
     * Renderiza la respuesta JSON automáticamente
     */
    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->message,
            'error_code' => $this->errorCode,
        ];

        // Agregar errores de validación si existen
        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        return response()->json($response, $this->httpCode);
    }

    // ============================================
    // MÉTODOS ESTÁTICOS HELPER PARA CASOS COMUNES
    // ============================================

    /**
     * Recurso no encontrado (404)
     */
    public static function notFound(string $resource = 'Recurso', ?int $id = null): self
    {
        $message = $id
            ? "{$resource} con ID {$id} no encontrado"
            : "{$resource} no encontrado";

        return new self(
            message: $message,
            errorCode: strtoupper($resource) . '_NOT_FOUND',
            httpCode: 404
        );
    }

    /**
     * Error de validación (422)
     */
    public static function validation(string $message, array $errors = []): self
    {
        return new self(
            message: $message,
            errorCode: 'VALIDATION_ERROR',
            httpCode: 422,
            errors: $errors
        );
    }

    /**
     * Conflicto / No se puede realizar acción (409)
     */
    public static function conflict(string $message, string $errorCode = 'CONFLICT'): self
    {
        return new self(
            message: $message,
            errorCode: $errorCode,
            httpCode: 409
        );
    }

    /**
     * No autorizado (401)
     */
    public static function unauthorized(string $message = 'No autorizado'): self
    {
        return new self(
            message: $message,
            errorCode: 'UNAUTHORIZED',
            httpCode: 401
        );
    }

    /**
     * Prohibido (403)
     */
    public static function forbidden(string $message = 'No tienes permisos para esta acción'): self
    {
        return new self(
            message: $message,
            errorCode: 'FORBIDDEN',
            httpCode: 403
        );
    }

    /**
     * Bad Request (400)
     */
    public static function badRequest(string $message, string $errorCode = 'BAD_REQUEST'): self
    {
        return new self(
            message: $message,
            errorCode: $errorCode,
            httpCode: 400
        );
    }

    /**
     * Error del servidor (500)
     */
    public static function serverError(string $message = 'Error interno del servidor'): self
    {
        return new self(
            message: $message,
            errorCode: 'SERVER_ERROR',
            httpCode: 500
        );
    }
}
