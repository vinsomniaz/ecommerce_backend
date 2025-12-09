<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ubigeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UbigeoController extends Controller
{
    /**
     * Obtener estructura jerárquica de ubigeos para Cascader
     * 
     * @return JsonResponse
     */
    public function tree(): JsonResponse
    {
        // Cachear por 1 día (los ubigeos no cambian frecuentemente)
        $tree = Cache::remember('ubigeos_tree_pe', now()->addDay(), function () {
            return $this->buildTree();
        });

        return response()->json([
            'success' => true,
            'data' => $tree,
        ]);
    }

    /**
     * Construir árbol jerárquico: Departamento -> Provincia -> Distrito
     * 
     * @return array
     */
    private function buildTree(): array
    {
        $ubigeos = Ubigeo::where('country_code', 'PE')
            ->select('ubigeo', 'departamento', 'provincia', 'distrito')
            ->orderBy('ubigeo')
            ->get();

        $tree = [];
        
        // Agrupar por departamento
        $departments = $ubigeos->groupBy('departamento');
        
        foreach ($departments as $deptName => $deptUbigeos) {
            $deptCode = substr($deptUbigeos->first()->ubigeo, 0, 2);
            
            // Agrupar por provincia dentro del departamento
            $provinces = $deptUbigeos->groupBy('provincia');
            $provinceChildren = [];
            
            foreach ($provinces as $provName => $provUbigeos) {
                $provCode = substr($provUbigeos->first()->ubigeo, 0, 4);
                
                // Distritos dentro de la provincia
                $districts = $provUbigeos->map(function ($ubigeo) {
                    return [
                        'value' => $ubigeo->ubigeo,
                        'label' => $ubigeo->distrito,
                    ];
                })->values()->toArray();
                
                $provinceChildren[] = [
                    'value' => $provCode,
                    'label' => $provName,
                    'children' => $districts,
                ];
            }
            
            $tree[] = [
                'value' => $deptCode,
                'label' => $deptName,
                'children' => $provinceChildren,
            ];
        }
        
        return $tree;
    }

    /**
     * Listar todos los ubigeos (opcional, para búsquedas)
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $ubigeos = Ubigeo::where('country_code', 'PE')
            ->select('ubigeo', 'departamento', 'provincia', 'distrito')
            ->orderBy('departamento')
            ->orderBy('provincia')
            ->orderBy('distrito')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ubigeos,
        ]);
    }

    /**
     * Obtener un ubigeo específico
     * 
     * @param string $ubigeo
     * @return JsonResponse
     */
    public function show(string $ubigeo): JsonResponse
    {
        $ubigeoData = Ubigeo::where('ubigeo', $ubigeo)->first();

        if (!$ubigeoData) {
            return response()->json([
                'success' => false,
                'message' => 'Ubigeo no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ubigeoData,
        ]);
    }
}
