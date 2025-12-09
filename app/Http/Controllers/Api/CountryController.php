<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * Listar todos los países
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $countries = Country::select('code', 'name', 'phone_code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $countries,
        ]);
    }

    /**
     * Obtener un país específico
     * 
     * @param string $code
     * @return JsonResponse
     */
    public function show(string $code): JsonResponse
    {
        $country = Country::where('code', $code)->first();

        if (!$country) {
            return response()->json([
                'success' => false,
                'message' => 'País no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $country,
        ]);
    }
}
