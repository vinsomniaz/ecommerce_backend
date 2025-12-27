<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use App\Exceptions\Coupons\CouponNotFoundException;
use App\Exceptions\Coupons\CouponExpiredException;
use App\Exceptions\Coupons\CouponUsageLimitException;
use App\Exceptions\Coupons\CouponMinAmountException;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{DB, Cache, Log};

class CouponService
{
    /**
     * Obtiene cupones con filtros y paginación
     */
    public function getCoupons(Request $request): LengthAwarePaginator
    {
        $perPage = $request->query('per_page', 20);
        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');
        $isActive = $request->query('is_active');
        $appliesTo = $request->query('applies_to');

        $version = Cache::remember('coupons_version', now()->addDay(), fn() => 1);

        $cacheKey = "coupons_v{$version}_" . md5(serialize([
            $perPage,
            $search,
            $type,
            $status,
            $isActive,
            $appliesTo,
            $request->query('page', 1)
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($perPage, $search, $type, $status, $isActive, $appliesTo) {

            $query = Coupon::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($status) {
                $today = now()->toDateString();

                match ($status) {
                    'active' => $query->where('active', true)
                        ->where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->where(function ($q) {
                            $q->whereNull('usage_limit')
                                ->orWhereRaw('usage_count < usage_limit');
                        }),
                    'expired' => $query->where('end_date', '<', $today),
                    'scheduled' => $query->where('start_date', '>', $today),
                    'exhausted' => $query->whereNotNull('usage_limit')
                        ->whereRaw('usage_count >= usage_limit'),
                    'inactive' => $query->where('active', false),
                    default => null,
                };
            }

            if ($isActive !== null) {
                $query->where('active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            }

            if ($appliesTo) {
                $query->where('applies_to', $appliesTo);
            }

            return $query
                ->withCount('usages')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });
    }

    /**
     * Obtiene un cupón por ID con caché
     */
    public function getCouponById(int $id): Coupon
    {
        $coupon = Cache::remember("coupon_{$id}", now()->addHour(), function () use ($id) {
            return Coupon::with(['categories', 'products'])
                ->withCount('usages')
                ->withSum('usages', 'discount_applied')
                ->find($id);
        });

        if (!$coupon) {
            throw new CouponNotFoundException("No se encontró el cupón con ID: {$id}");
        }

        return $coupon;
    }

    /**
     * Crea un nuevo cupón
     */
    public function createCoupon(array $data): Coupon
    {
        return DB::transaction(function () use ($data) {
            // Asegurar código en mayúsculas
            $data['code'] = strtoupper($data['code']);

            // Extraer categorías y productos si existen
            $categoryIds = $data['category_ids'] ?? [];
            $productIds = $data['product_ids'] ?? [];
            unset($data['category_ids'], $data['product_ids']);

            // Valores por defecto
            $data['usage_count'] = 0;
            $data['active'] = $data['active'] ?? true;
            $data['applies_to'] = $data['applies_to'] ?? 'all';

            $coupon = Coupon::create($data);

            // Sincronizar relaciones si aplica
            if ($data['applies_to'] === 'categories' && !empty($categoryIds)) {
                $coupon->categories()->sync($categoryIds);
            }

            if ($data['applies_to'] === 'products' && !empty($productIds)) {
                $coupon->products()->sync($productIds);
            }

            Log::info('Cupón creado', [
                'id' => $coupon->id,
                'code' => $coupon->code
            ]);

            return $coupon->load(['categories', 'products']);
        });
    }

    /**
     * Actualiza un cupón
     */
    public function updateCoupon(int $id, array $data): Coupon
    {
        return DB::transaction(function () use ($id, $data) {
            $coupon = Coupon::findOrFail($id);

            // Asegurar código en mayúsculas si se actualiza
            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            // Extraer categorías y productos
            $categoryIds = $data['category_ids'] ?? null;
            $productIds = $data['product_ids'] ?? null;
            unset($data['category_ids'], $data['product_ids']);

            $coupon->update($data);

            // Sincronizar relaciones si se proporcionan
            if ($categoryIds !== null) {
                $coupon->categories()->sync($categoryIds);
            }

            if ($productIds !== null) {
                $coupon->products()->sync($productIds);
            }

            Log::info('Cupón actualizado', [
                'id' => $coupon->id,
                'code' => $coupon->code,
            ]);

            return $coupon->fresh(['categories', 'products']);
        });
    }

    /**
     * Elimina un cupón
     */
    public function deleteCoupon(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $coupon = Coupon::findOrFail($id);

            $deleted = $coupon->delete();

            Log::info('Cupón eliminado', ['code' => $coupon->code]);

            return $deleted;
        });
    }

    /**
     * Activa/Desactiva un cupón
     */
    public function toggleActive(int $id): Coupon
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->active = !$coupon->active;
        $coupon->save();

        Log::info('Cupón ' . ($coupon->active ? 'activado' : 'desactivado'), [
            'id' => $coupon->id,
            'code' => $coupon->code,
        ]);

        return $coupon;
    }

    /**
     * Valida un cupón para uso
     */
    public function validateCoupon(string $code, float $amount, ?User $user = null): Coupon
    {
        $coupon = Coupon::findByCode($code);

        if (!$coupon) {
            throw new CouponNotFoundException("El cupón '{$code}' no existe");
        }

        if (!$coupon->is_valid) {
            if ($coupon->is_expired) {
                throw new CouponExpiredException("El cupón '{$code}' ha expirado");
            }
            if ($coupon->is_coming_soon) {
                throw new CouponExpiredException("El cupón '{$code}' aún no está vigente");
            }
            if (!$coupon->active) {
                throw new CouponExpiredException("El cupón '{$code}' no está activo");
            }
        }

        if (!$coupon->canBeUsedBy($user)) {
            if ($coupon->is_usage_limited && $coupon->remaining_uses <= 0) {
                throw new CouponUsageLimitException("El cupón '{$code}' ha alcanzado su límite de usos");
            }
            if ($user && $coupon->usage_per_user) {
                throw new CouponUsageLimitException("Ya has usado este cupón el máximo de veces permitido");
            }
        }

        if ($amount < $coupon->min_amount) {
            throw new CouponMinAmountException(
                "El monto mínimo para usar este cupón es S/. " . number_format($coupon->min_amount, 2)
            );
        }

        return $coupon;
    }

    /**
     * Registra el uso de un cupón
     */
    public function recordUsage(Coupon $coupon, float $discountApplied, float $orderSubtotal, ?int $orderId = null, ?int $userId = null): CouponUsage
    {
        return DB::transaction(function () use ($coupon, $discountApplied, $orderSubtotal, $orderId, $userId) {
            $usage = CouponUsage::create([
                'coupon_id' => $coupon->id,
                'order_id' => $orderId,
                'user_id' => $userId,
                'discount_applied' => $discountApplied,
                'order_subtotal' => $orderSubtotal,
            ]);

            $coupon->incrementUsage();

            Log::info('Uso de cupón registrado', [
                'coupon_code' => $coupon->code,
                'discount_applied' => $discountApplied,
                'order_id' => $orderId,
            ]);

            return $usage;
        });
    }

    /**
     * Obtiene estadísticas globales de cupones
     */
    public function getGlobalStats(): array
    {
        $version = Cache::remember('coupons_version', now()->addDay(), fn() => 1);
        $key = "coupons_global_stats_v{$version}";

        return Cache::remember($key, now()->addMinutes(5), function () {
            $today = now()->toDateString();

            return [
                'total_coupons' => Coupon::count(),
                'active_coupons' => Coupon::where('active', true)
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->count(),
                'expired_coupons' => Coupon::where('end_date', '<', $today)->count(),
                'total_uses' => CouponUsage::count(),
                'total_discount_granted' => CouponUsage::sum('discount_applied'),
            ];
        });
    }

    /**
     * Obtiene estadísticas detalladas
     */
    public function getStatistics(): array
    {
        $today = now()->toDateString();

        return [
            'total_coupons' => Coupon::count(),
            'active_coupons' => Coupon::where('active', true)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count(),
            'expired_coupons' => Coupon::where('end_date', '<', $today)->count(),
            'scheduled_coupons' => Coupon::where('start_date', '>', $today)->count(),
            'inactive_coupons' => Coupon::where('active', false)->count(),

            'by_type' => [
                'percentage' => Coupon::where('type', 'percentage')->count(),
                'amount' => Coupon::where('type', 'amount')->count(),
            ],
            'by_application' => [
                'all' => Coupon::where('applies_to', 'all')->count(),
                'categories' => Coupon::where('applies_to', 'categories')->count(),
                'products' => Coupon::where('applies_to', 'products')->count(),
            ],

            'usage' => [
                'total_uses' => CouponUsage::count(),
                'total_discount_granted' => (float) CouponUsage::sum('discount_applied'),
                'average_discount' => (float) CouponUsage::avg('discount_applied'),
                'uses_this_month' => CouponUsage::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ],

            'top_coupons' => Coupon::select('id', 'code', 'name', 'usage_count')
                ->orderBy('usage_count', 'desc')
                ->limit(5)
                ->get()
                ->toArray(),
        ];
    }
}
