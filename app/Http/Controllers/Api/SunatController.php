<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SunatService;
use Illuminate\Http\JsonResponse;

class SunatController extends Controller
{
    protected $sunatService;

    public function __construct(SunatService $sunatService)
    {
        $this->sunatService = $sunatService;
    }

    /**
     * Validate a document (DNI or RUC) using an external API.
     */
    public function validateDocument(string $tipo, string $numero): JsonResponse
    {
        if (!in_array($tipo, ['01', '06'])) {
            return response()->json(['message' => 'Tipo de documento no válido.'], 400);
        }

        if ($tipo == '01' && strlen($numero) != 8) {
            return response()->json(['message' => 'DNI debe tener 8 dígitos.'], 400);
        }

        if ($tipo == '06' && strlen($numero) != 11) {
            return response()->json(['message' => 'RUC debe tener 11 dígitos.'], 400);
        }

        $result = $this->sunatService->validateDocument($tipo, $numero);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json($result['data']);
    }
}
