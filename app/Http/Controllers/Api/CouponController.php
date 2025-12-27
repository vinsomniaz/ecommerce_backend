<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coupons\StoreCouponRequest;
use App\Http\Requests\Coupons\UpdateCouponRequest;
use App\Http\Resources\Coupons\CouponCollection;
use App\Http\Resources\Coupons\CouponResource;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function __construct(
        private CouponService $couponService
    ) {}

    /**
     * Listar todos los cupones con filtros
     *
     * @group Coupons
     * @queryParam per_page int Cantidad por página. Default: 20
     * @queryParam search string Buscar por código, nombre o descripción
     * @queryParam type string Filtrar por tipo (percentage, amount)
     * @queryParam status string Filtrar por estado (active, expired, scheduled, exhausted, inactive)
     * @queryParam is_active boolean Filtrar por estado activo
     * @queryParam applies_to string Filtrar por aplicación (all, categories, products)
     */
    public function index(Request $request): JsonResponse
    {
        $coupons = $this->couponService->getCoupons($request);

        if ($coupons->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aún no se ha creado ningún cupón',
                'data' => [],
                'meta' => [
                    'pagination' => [
                        'total' => 0,
                        'per_page' => $request->query('per_page', 20),
                        'current_page' => 1,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                    ],
                    'stats' => $this->couponService->getGlobalStats(),
                ]
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cupones obtenidos correctamente',
            'data' => (new CouponCollection($coupons))->toArray($request)['data'],
            'meta' => (new CouponCollection($coupons))->with($request)['meta'],
        ], 200);
    }

    /**
     * Mostrar un cupón específico
     *
     * @group Coupons
     */
    public function show(int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        return response()->json([
            'success' => true,
            'message' => 'Cupón obtenido correctamente',
            'data' => new CouponResource($coupon)
        ], 200);
    }

    /**
     * Crear nuevo cupón
     *
     * @group Coupons
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->couponService->createCoupon($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cupón creado correctamente',
            'data' => new CouponResource($coupon)
        ], 201);
    }

    /**
     * Actualizar cupón existente
     *
     * @group Coupons
     */
    public function update(UpdateCouponRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponService->updateCoupon($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cupón actualizado correctamente',
            'data' => new CouponResource($coupon)
        ], 200);
    }

    /**
     * Eliminar cupón
     *
     * @group Coupons
     */
    public function destroy(int $id): JsonResponse
    {
        $this->couponService->deleteCoupon($id);

        return response()->json([
            'success' => true,
            'message' => 'Cupón eliminado correctamente'
        ], 200);
    }

    /**
     * Activar/Desactivar cupón
     *
     * @group Coupons
     */
    public function toggleActive(int $id): JsonResponse
    {
        $coupon = $this->couponService->toggleActive($id);

        return response()->json([
            'success' => true,
            'message' => 'Estado del cupón actualizado correctamente',
            'data' => new CouponResource($coupon)
        ], 200);
    }

    /**
     * Obtener estadísticas de cupones
     *
     * @group Coupons
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->couponService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats
        ], 200);
    }

    /**
     * Validar cupón (para checkout)
     *
     * @group Coupons
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
        ]);

        try {
            $coupon = $this->couponService->validateCoupon(
                $request->input('code'),
                (float) $request->input('amount'),
                $request->user()
            );

            $discount = $coupon->calculateDiscount((float) $request->input('amount'));

            return response()->json([
                'success' => true,
                'message' => 'Cupón válido',
                'data' => [
                    'coupon' => new CouponResource($coupon),
                    'discount' => $discount,
                    'final_amount' => (float) $request->input('amount') - $discount,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
