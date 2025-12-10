<?php
// routes/api.php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductAttributeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierProductController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\InventoryController; // NUEVO
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\SunatController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\StockManagementController;
use App\Http\Controllers\Api\EcommerceController;
use App\Http\Controllers\Api\GeminiController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProductPriceController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SupplierImportController;
use App\Http\Controllers\Auth\PermissionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\UbigeoController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS (Para el E-commerce)
|--------------------------------------------------------------------------
|
| Rutas de lectura (GET) que no requieren autenticación.
|
*/
// --- AÑADE ESTE GRUPO DE RUTAS PARA LA TIENDA ---
Route::prefix('ecommerce')->name('ecommerce/')->group(function () {

    // Ruta para la lista de productos: /api/ecommerce/products
    Route::get('products', [EcommerceController::class, 'index'])
        ->name('products.index');

    // Ruta para el detalle del producto: /api/ecommerce/products/{product}
    Route::get('products/{product}', [EcommerceController::class, 'show'])
        ->name('products.show');

    // --- NUEVAS RUTAS DE CATEGORÍAS PÚBLICAS ---
    Route::get('categories', [EcommerceController::class, 'listCategories'])
        ->name('categories.list');

    Route::get('categories/tree', [EcommerceController::class, 'getCategoryTree'])
        ->name('categories.tree');

    Route::get('categories/{id}', [EcommerceController::class, 'showCategory'])
        ->name('categories.show');

    // Lista de Distribución (Sin paginación)
    Route::get('distribution-list', [EcommerceController::class, 'distributionList'])
        ->name('distribution-list');
});

/*
|--------------------------------------------------------------------------
| RUTAS DE PAÍSES Y UBIGEOS (Públicas)
|--------------------------------------------------------------------------
|
| Endpoints para listar países y ubigeos (necesarios para formularios)
|
*/
Route::prefix('countries')->group(function () {
    Route::get('/', [CountryController::class, 'index']);
    Route::get('{code}', [CountryController::class, 'show']);
});

Route::prefix('ubigeos')->group(function () {
    Route::get('tree', [UbigeoController::class, 'tree']); // Para Cascader
    Route::get('/', [UbigeoController::class, 'index']);
    Route::get('{ubigeo}', [UbigeoController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| RUTAS PRIVADAS
|--------------------------------------------------------------------------
|
*/

// ========================================
// RUTAS DE PERMISOS (Solo Super-Admin)
// ========================================
Route::middleware(['auth:sanctum', 'role:super-admin'])->prefix('permissions')->group(function () {

    // Listar todos los permisos disponibles agrupados por módulo
    Route::get('/', [PermissionController::class, 'index'])
        ->name('permissions.index');

    // 🔥 NUEVO: Listar permisos peligrosos/críticos
    Route::get('/dangerous', [PermissionController::class, 'getDangerousPermissions'])
        ->name('permissions.dangerous');

    // Obtener permisos de un usuario
    Route::get('/users/{userId}', [PermissionController::class, 'getUserPermissions'])
        ->name('permissions.user');

    // Asignar permisos adicionales a un usuario
    Route::post('/users/{userId}/assign', [PermissionController::class, 'assignToUser'])
        ->name('permissions.assign');

    // Revocar permisos de un usuario
    Route::post('/users/{userId}/revoke', [PermissionController::class, 'revokeFromUser'])
        ->name('permissions.revoke');

    // Sincronizar permisos (reemplazar todos los directos)
    Route::post('/users/{userId}/sync', [PermissionController::class, 'syncUserPermissions'])
        ->name('permissions.sync');

    // Obtener sugerencias de permisos para un rol
    Route::get('/suggestions/{role}', [PermissionController::class, 'getSuggestedPermissions'])
        ->name('permissions.suggestions');
});

/* ============================================
   USUARIOS
   ============================================ */
Route::prefix('users')->middleware(['auth:sanctum'])->group(function () {
    // CRUD REST (index, store, show, update, destroy)
    Route::get('/', [UserController::class, 'index'])
        ->middleware('permission:users.index');

    Route::get('/{id}', [UserController::class, 'show'])
        ->middleware('permission:users.show');

    Route::post('/', [UserController::class, 'store'])
        ->middleware('permission:users.store');

    Route::match(['put', 'patch'], '/{id}', [UserController::class, 'update'])
        ->middleware('permission:users.update');

    Route::delete('/{id}', [UserController::class, 'destroy'])
        ->middleware('permission:users.destroy');

    // Rutas adicionales
    Route::post('{id}/restore', [UserController::class, 'restore'])
        ->middleware('permission:users.restore');

    Route::patch('{id}/toggle-active', [UserController::class, 'toggleActive'])
        ->middleware('permission:users.toggle-active');

    Route::patch('{id}/change-role', [UserController::class, 'changeRole'])
        ->middleware('permission:users.change-role');
});

/* ============================================
   CATEGORIAS
   ============================================ */
Route::prefix('categories')->middleware(['auth:sanctum'])->group(function () {

    Route::get('/', [CategoryController::class, 'index'])
        ->middleware('permission:categories.index');

    Route::get('/tree', [CategoryController::class, 'tree'])
        ->middleware('permission:categories.tree');

    Route::get('/{id}', [CategoryController::class, 'show'])
        ->middleware('permission:categories.show');

    Route::post('/', [CategoryController::class, 'store'])
        ->middleware('permission:categories.store');

    Route::match(['put', 'patch'], '/{id}', [CategoryController::class, 'update'])
        ->middleware('permission:categories.update');

    Route::delete('/{id}', [CategoryController::class, 'destroy'])
        ->middleware('permission:categories.destroy');
});

/* ============================================
   ALMACENES
   ============================================ */
Route::prefix('warehouses')->middleware(['auth:sanctum'])->group(function () {

    Route::get('/', [WarehouseController::class, 'index'])
        ->middleware('permission:warehouses.index');

    Route::post('/', [WarehouseController::class, 'store'])
        ->middleware('permission:warehouses.store');

    Route::get('/{id}', [WarehouseController::class, 'show'])
        ->middleware('permission:warehouses.show');

    Route::match(['put', 'patch'], '/{id}', [WarehouseController::class, 'update'])
        ->middleware('permission:warehouses.update');

    Route::delete('/{id}', [WarehouseController::class, 'destroy'])
        ->middleware('permission:warehouses.destroy');

    Route::get('/{warehouse}/inventory', [InventoryController::class, 'getByWarehouse'])
        ->middleware('permission:warehouses.inventory');

    Route::get('/{warehouse}/inventory/statistics', [InventoryController::class, 'warehouseStatistics'])
        ->middleware('permission:warehouses.inventory.statistics');
});

/* ============================================
   PRODUCTOS
   ============================================ */
Route::middleware('auth:sanctum')->prefix('products')->group(function () {
    // Rutas especiales PRIMERO
    Route::post('bulk-update', [ProductController::class, 'bulkUpdate'])
        ->middleware('permission:products.bulk-update');

    Route::get('statistics', [ProductController::class, 'statistics'])
        ->middleware('permission:products.statistics');

    Route::post('{product}/duplicate', [ProductController::class, 'duplicate'])
        ->middleware('permission:products.duplicate');

    Route::post('{id}/restore', [ProductController::class, 'restore'])
        ->middleware('permission:products.restore');

    Route::post('{product}/images', [ProductController::class, 'uploadImages'])
        ->middleware('permission:products.images.upload');

    Route::delete('{product}/images/{mediaId}', [ProductController::class, 'deleteImage'])
        ->middleware('permission:products.images.delete');

    // Establecer imagen principal
    Route::patch('{product}/images/{mediaId}/set-primary', [ProductController::class, 'setPrimaryImage'])
        ->middleware('permission:products.images.set-primary');

    // Reordenar imágenes
    Route::patch('{product}/images/reorder', [ProductController::class, 'reorderImages'])
        ->middleware('permission:products.images.reorder');

    Route::get('{product}/inventory', [InventoryController::class, 'getByProduct'])
        ->middleware('permission:products.inventory');

    Route::get('{product}/inventory/statistics', [InventoryController::class, 'productStatistics'])
        ->middleware('permission:products.inventory.statistics');

    // CRUD básico
    Route::get('/', [ProductController::class, 'index'])
        ->middleware('permission:products.index');

    Route::post('/', [ProductController::class, 'store'])
        ->middleware('permission:products.store');

    Route::get('{product}', [ProductController::class, 'show'])
        ->middleware('permission:products.show');

    Route::match(['put', 'patch'], '{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update');

    Route::delete('{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.destroy');

    Route::prefix('{product}/attributes')->group(function () {

        Route::get('/', [ProductAttributeController::class, 'index'])
            ->middleware('permission:attributes.index');

        Route::post('/', [ProductAttributeController::class, 'store'])
            ->middleware('permission:attributes.store');

        Route::put('bulk', [ProductAttributeController::class, 'bulkUpdate'])
            ->middleware('permission:attributes.bulk-update');

        Route::put('{attribute}', [ProductAttributeController::class, 'update'])
            ->middleware('permission:attributes.update');

        Route::delete('{attribute}', [ProductAttributeController::class, 'destroy'])
            ->middleware('permission:attributes.destroy');
    });
});

// ============================================================================
// RUTAS DE LISTAS DE PRECIOS
// ============================================================================

Route::middleware('auth:sanctum')->prefix('price-lists')->group(function () {

    // ============================================================================
    // RUTAS ESPECIALES (DEBEN IR PRIMERO)
    // ============================================================================

    // Obtener listas activas (para selects)
    Route::get('active', [PriceListController::class, 'active']);

    // Estadísticas de listas de precios
    Route::get('statistics', [PriceListController::class, 'statistics'])
        ->middleware('permission:price-lists.statistics');

    // Productos de una lista específica
    Route::get('{id}/products', [PriceListController::class, 'products'])
        ->middleware('permission:price-lists.view');

    // Activar/Desactivar lista
    Route::patch('{id}/toggle-status', [PriceListController::class, 'toggleStatus'])
        ->middleware('permission:price-lists.update');

    // ============================================================================
    // RUTAS CRUD ESTÁNDAR
    // ============================================================================

    // Listar todas las listas de precios
    Route::get('/', [PriceListController::class, 'index'])
        ->middleware('permission:price-lists.view');

    // Crear nueva lista de precios
    Route::post('/', [PriceListController::class, 'store'])
        ->middleware('permission:price-lists.create');

    // Ver una lista específica
    Route::get('{id}', [PriceListController::class, 'show'])
        ->middleware('permission:price-lists.view');

    // Actualizar lista de precios
    Route::match(['put', 'patch'], '{id}', [PriceListController::class, 'update'])
        ->middleware('permission:price-lists.update');

    // Eliminar lista de precios
    Route::delete('{id}', [PriceListController::class, 'destroy'])
        ->middleware('permission:price-lists.delete');
});

/* ============================================
   PRODUCT PRICES (Gestión de Precios de Productos)
   ============================================ */
Route::middleware('auth:sanctum')->prefix('product-prices')->group(function () {

    // ============================================================================
    // RUTAS ESPECIALES (DEBEN IR PRIMERO)
    // ============================================================================

    // Estadísticas de precios
    Route::get('statistics', [ProductPriceController::class, 'statistics'])
        ->middleware('permission:product-prices.statistics');

    // Actualización masiva de precios
    Route::post('bulk-update', [ProductPriceController::class, 'bulkUpdate'])
        ->middleware('permission:product-prices.bulk-update');

    // Copiar precios entre listas
    Route::post('copy', [ProductPriceController::class, 'copy'])
        ->middleware('permission:product-prices.copy');

    // Calcular precio sugerido
    Route::post('calculate', [ProductPriceController::class, 'calculate'])
        ->middleware('permission:product-prices.calculate');

    // Desactivar precios expirados
    Route::post('deactivate-expired', [ProductPriceController::class, 'deactivateExpired'])
        ->middleware('permission:product-prices.deactivate-expired');

    // Obtener precios por producto
    Route::get('by-product/{productId}', [ProductPriceController::class, 'byProduct'])
        ->middleware('permission:product-prices.by-product');

    // ============================================================================
    // CRUD BÁSICO
    // ============================================================================

    // Listar precios (con filtros)
    Route::get('/', [ProductPriceController::class, 'index'])
        ->middleware('permission:product-prices.index');

    // Crear nuevo precio
    Route::post('/', [ProductPriceController::class, 'store'])
        ->middleware('permission:product-prices.store');

    // Ver detalle de precio
    Route::get('{productPrice}', [ProductPriceController::class, 'show'])
        ->middleware('permission:product-prices.show');

    // Actualizar precio
    Route::match(['put', 'patch'], '{productPrice}', [ProductPriceController::class, 'update'])
        ->middleware('permission:product-prices.update');

    // Eliminar precio
    Route::delete('{productPrice}', [ProductPriceController::class, 'destroy'])
        ->middleware('permission:product-prices.destroy');

    // Activar/desactivar precio
    Route::patch('{productPrice}/toggle-active', [ProductPriceController::class, 'toggleActive'])
        ->middleware('permission:product-prices.toggle-active');
});

/* ============================================
   INVENTARIO
   ============================================ */
Route::middleware('auth:sanctum')->prefix('inventory')->group(function () {
    // Estáticas/“palabras” primero
    Route::get('statistics/global', [InventoryController::class, 'globalStatistics'])
        ->middleware('permission:inventory.statistics.global');

    Route::get('alerts/low-stock', [InventoryController::class, 'lowStockAlert'])
        ->middleware('permission:inventory.alerts.low-stock');

    Route::get('alerts/out-of-stock', [InventoryController::class, 'outOfStockAlert'])
        ->middleware('permission:inventory.alerts.out-of-stock');

    // Listado y alta
    Route::get('/', [InventoryController::class, 'index'])
        ->middleware('permission:inventory.index');

    Route::post('/', [InventoryController::class, 'store'])
        ->middleware('permission:inventory.store'); // Sirve para asignar producto a tienda pero ya lo hacen automaticamente

    Route::post('bulk-assign', [InventoryController::class, 'bulkAssign'])
        ->middleware('permission:inventory.bulk-assign');

    // Dinámicas al final + constraints numéricos
    Route::get('{product}/{warehouse}', [InventoryController::class, 'show'])
        ->whereNumber('product')->whereNumber('warehouse')
        ->middleware('permission:inventory.show');

    Route::match(['put', 'patch'], '{product}/{warehouse}', [InventoryController::class, 'update'])
        ->whereNumber('product')->whereNumber('warehouse')
        ->middleware('permission:inventory.update'); // Actualizar atributos de inventario con producto

    Route::delete('{product}/{warehouse}', [InventoryController::class, 'destroy'])
        ->whereNumber('product')->whereNumber('warehouse')
        ->middleware('permission:inventory.destroy');


    //Precios
    Route::prefix('{product}/prices')->group(function () {
        Route::patch('update', [PricingController::class, 'updatePrices'])
            ->middleware('permission:pricing.update-prices');
    });
});

/* ============================================
   TIPOS DE DOCUMENTO
   ============================================ */
Route::prefix('document-types')->group(function () {
    Route::get('/', [DocumentTypeController::class, 'index']);
    Route::get('{code}', [DocumentTypeController::class, 'show']);
});

/* ============================================
   ENTIDADES
   ============================================ */
Route::middleware('auth:sanctum')->prefix('entities')->group(function () {

    // Statistics endpoint (must be before dynamic routes)
    Route::get('statistics/global', [EntityController::class, 'globalStatistics'])
        ->middleware('permission:entities.statistics.global');

    Route::get('search', [EntityController::class, 'search'])
        ->middleware('permission:entities.search');

    Route::get('find-by-document', [EntityController::class, 'findByDocument'])
        ->middleware('permission:entities.find-by-document');

    Route::patch('{entity}/deactivate', [EntityController::class, 'deactivate'])
        ->middleware('permission:entities.deactivate');

    Route::patch('{entity}/activate', [EntityController::class, 'activate'])
        ->middleware('permission:entities.activate');

    Route::get('/', [EntityController::class, 'index'])
        ->middleware('permission:entities.index');

    Route::post('/', [EntityController::class, 'store'])
        ->middleware('permission:entities.store');

    Route::get('{entity}', [EntityController::class, 'show'])
        ->middleware('permission:entities.show');

    Route::match(['put', 'patch'], '{entity}', [EntityController::class, 'update'])
        ->middleware('permission:entities.update');

    Route::delete('{entity}', [EntityController::class, 'destroy'])
        ->middleware('permission:entities.destroy');

    Route::prefix('{entity}/addresses')->group(function () {

        Route::get('/', [AddressController::class, 'index'])
            ->middleware('permission:addresses.index');

        Route::post('/', [AddressController::class, 'store'])
            ->middleware('permission:addresses.store');
    });
});

/* ============================================
   GESTIÓN DE STOCK (MANUAL)
   ============================================ */
Route::middleware('auth:sanctum')->prefix('stock')->group(function () {

    // Traslado entre almacenes
    Route::post('transfer', [StockManagementController::class, 'transfer'])
        ->middleware('permission:stock.transfer');

    // Ajustes de inventario
    Route::post('adjustment/in', [StockManagementController::class, 'adjustmentIn'])
        ->middleware('permission:stock.adjustment.in');

    Route::post('adjustment/out', [StockManagementController::class, 'adjustmentOut'])
        ->middleware('permission:stock.adjustment.out');

    // Consultas
    Route::get('batches', [StockManagementController::class, 'availableBatches'])
        ->middleware('permission:stock.batches');

    Route::get('movements/product/{productId}', [StockManagementController::class, 'productMovements'])
        ->middleware('permission:stock.movements');

    // Sincronización
    Route::post('sync', [StockManagementController::class, 'syncInventory'])
        ->middleware('permission:stock.sync');
});

/* ============================================
   DIRECCIONES
   ============================================ */
Route::middleware('auth:sanctum')->prefix('addresses')->group(function () {
    Route::patch('{address}/set-default', [AddressController::class, 'setDefault'])
        ->middleware('permission:addresses.set-default');

    Route::get('{address}', [AddressController::class, 'show'])
        ->middleware('permission:addresses.show');

    Route::match(['put', 'patch'], '{address}', [AddressController::class, 'update'])
        ->middleware('permission:addresses.update');

    Route::delete('{address}', [AddressController::class, 'destroy'])
        ->middleware('permission:addresses.destroy');
});

/* ============================================
   COTIZACIONES (QUOTATIONS)
   ============================================ */
Route::middleware('auth:sanctum')->prefix('quotations')->group(function () {

    // ============================================================================
    // CRUD BÁSICO
    // ============================================================================

    // Listar cotizaciones (con filtros)
    Route::get('/', [QuotationController::class, 'index'])
        ->middleware('permission:quotations.index');

    // Crear nueva cotización
    Route::post('/', [QuotationController::class, 'store'])
        ->middleware('permission:quotations.store');

    // Ver detalle de cotización
    Route::get('/{quotation}', [QuotationController::class, 'show'])
        ->middleware('permission:quotations.show');

    // Actualizar cotización (solo draft)
    Route::match(['put', 'patch'], '/{quotation}', [QuotationController::class, 'update'])
        ->middleware('permission:quotations.update');

    // Eliminar cotización (soft delete, solo draft)
    Route::delete('/{quotation}', [QuotationController::class, 'destroy'])
        ->middleware('permission:quotations.destroy');

    // ============================================================================
    // GESTIÓN DE ITEMS
    // ============================================================================

    // Agregar producto a cotización
    Route::post('/{quotation}/items', [QuotationController::class, 'addItem'])
        ->middleware('permission:quotations.items.add');

    // Actualizar item existente
    Route::patch('/{quotation}/items/{detailId}', [QuotationController::class, 'updateItem'])
        ->middleware('permission:quotations.items.update');

    // Eliminar producto de cotización
    Route::delete('/{quotation}/items/{detailId}', [QuotationController::class, 'removeItem'])
        ->middleware('permission:quotations.items.remove');

    // Actualizar cantidad de un item
    Route::patch('/{quotation}/items/{detailId}/quantity', [QuotationController::class, 'updateItemQuantity'])
        ->middleware('permission:quotations.items.update-quantity');

    // ============================================================================
    // ACCIONES DE ENVÍO
    // ============================================================================

    // Enviar cotización por email/WhatsApp
    Route::post('/{quotation}/send', [QuotationController::class, 'send'])
        ->middleware('permission:quotations.send');

    // Reenviar cotización
    Route::post('/{quotation}/resend', [QuotationController::class, 'resend'])
        ->middleware('permission:quotations.resend');

    // Generar/regenerar PDF
    Route::post('/{quotation}/generate-pdf', [QuotationController::class, 'generatePdf'])
        ->middleware('permission:quotations.generate-pdf');

    // Descargar PDF
    Route::get('/{quotation}/download-pdf', [QuotationController::class, 'downloadPdf'])
        ->middleware('permission:quotations.download-pdf');

    // ============================================================================
    // CAMBIOS DE ESTADO
    // ============================================================================

    // Cambiar estado de cotización
    Route::post('/{quotation}/status', [QuotationController::class, 'changeStatus'])
        ->middleware('permission:quotations.change-status');

    // Marcar como aceptada
    Route::post('/{quotation}/accept', [QuotationController::class, 'accept'])
        ->middleware('permission:quotations.accept');

    // Marcar como rechazada
    Route::post('/{quotation}/reject', [QuotationController::class, 'reject'])
        ->middleware('permission:quotations.reject');

    // Marcar como expirada (automático o manual)
    Route::post('/{quotation}/expire', [QuotationController::class, 'expire'])
        ->middleware('permission:quotations.expire');

    // ============================================================================
    // CONVERSIÓN A VENTA
    // ============================================================================

    // Convertir cotización a venta
    Route::post('/{quotation}/convert-to-sale', [QuotationController::class, 'convertToSale'])
        ->middleware('permission:quotations.convert-to-sale');

    // ============================================================================
    // COMISIONES
    // ============================================================================

    // Marcar comisión como pagada
    Route::post('/{quotation}/pay-commission', [QuotationController::class, 'payCommission'])
        ->middleware('permission:quotations.pay-commission');

    // ============================================================================
    // CONSULTAS Y ESTADÍSTICAS
    // ============================================================================

    // Estadísticas generales de cotizaciones
    Route::get('/statistics/general', [QuotationController::class, 'statistics'])
        ->middleware('permission:quotations.statistics');

    // Estadísticas por vendedor
    Route::get('/statistics/by-seller', [QuotationController::class, 'statisticsBySeller'])
        ->middleware('permission:quotations.statistics.by-seller');

    // Reporte de comisiones pendientes
    Route::get('/reports/pending-commissions', [QuotationController::class, 'pendingCommissions'])
        ->middleware('permission:quotations.reports.commissions');

    // Cotizaciones próximas a expirar
    Route::get('/alerts/expiring-soon', [QuotationController::class, 'expiringSoon'])
        ->middleware('permission:quotations.alerts.expiring');

    // Historial de cambios de estado
    Route::get('/{quotation}/history', [QuotationController::class, 'statusHistory'])
        ->middleware('permission:quotations.history');

    // ============================================================================
    // UTILIDADES
    // ============================================================================

    // Obtener proveedores disponibles para un producto
    Route::get('/products/{productId}/suppliers', [QuotationController::class, 'getProductSuppliers'])
        ->middleware('permission:quotations.products.suppliers');

    // Verificar stock disponible
    Route::post('/check-stock', [QuotationController::class, 'checkStock'])
        ->middleware('permission:quotations.check-stock');

    // Duplicar cotización
    Route::post('/{quotation}/duplicate', [QuotationController::class, 'duplicate'])
        ->middleware('permission:quotations.duplicate');

    // Calcular totales (preview sin guardar)
    Route::post('/calculate-totals', [QuotationController::class, 'calculateTotals'])
        ->middleware('permission:quotations.calculate-totals');

    // Validar disponibilidad y precios actuales de una cotización
    Route::get('/{quotation}/validate-availability', [QuotationController::class, 'validateAvailability'])
        ->middleware('permission:quotations.validate-availability');

    // Recalcular cotización con precios actuales (para cotizaciones vencidas)
    Route::post('/{quotation}/recalculate-prices', [QuotationController::class, 'recalculateWithCurrentPrices'])
        ->middleware('permission:quotations.recalculate-prices');

    // Validar precio propuesto para un producto (útil en frontend antes de agregar)
    Route::post('/validate-price', [QuotationController::class, 'validatePrice'])
        ->middleware('permission:quotations.validate-price');

    // Obtener precio sugerido según margen de categoría
    Route::post('/suggest-price', [QuotationController::class, 'suggestPrice'])
        ->middleware('permission:quotations.suggest-price');

    Route::get('/{quotation}/margins-breakdown', [QuotationController::class, 'marginsBreakdown'])
        ->middleware('permission:quotations.margins-breakdown');
});

/* ============================================
   SUPPLIER PRODUCTS (Productos de Proveedores)
   ============================================ */
Route::middleware('auth:sanctum')->prefix('supplier-products')->group(function () {

    // Listar productos de proveedores
    Route::get('/', [SupplierProductController::class, 'index'])
        ->middleware('permission:supplier-products.index');

    // Crear relación producto-proveedor
    Route::post('/', [SupplierProductController::class, 'store'])
        ->middleware('permission:supplier-products.store');

    // Ver detalle
    Route::get('/{supplierProduct}', [SupplierProductController::class, 'show'])
        ->middleware('permission:supplier-products.show');

    // Actualizar precio/stock
    Route::match(['put', 'patch'], '/{supplierProduct}', [SupplierProductController::class, 'update'])
        ->middleware('permission:supplier-products.update');

    // Eliminar
    Route::delete('/{supplierProduct}', [SupplierProductController::class, 'destroy'])
        ->middleware('permission:supplier-products.destroy');

    // Actualización masiva de precios
    Route::post('/bulk-update-prices', [SupplierProductController::class, 'bulkUpdatePrices'])
        ->middleware('permission:supplier-products.bulk-update-prices');

    // Por producto (todos los proveedores que lo tienen)
    Route::get('/by-product/{productId}', [SupplierProductController::class, 'byProduct'])
        ->middleware('permission:supplier-products.by-product');

    // Por proveedor (todos sus productos)
    Route::get('/by-supplier/{supplierId}', [SupplierProductController::class, 'bySupplier'])
        ->middleware('permission:supplier-products.by-supplier');

    // Comparar precios entre proveedores
    Route::get('/compare-prices/{productId}', [SupplierProductController::class, 'comparePrices'])
        ->middleware('permission:supplier-products.compare-prices');
});

/* ============================================
   SUPPLIER IMPORTS (Importación desde Scrapers)
   ============================================ */
Route::middleware('auth:sanctum')->prefix('supplier-imports')->group(function () {

    // Listar importaciones
    Route::get('/', [SupplierImportController::class, 'index'])
        ->middleware('permission:supplier-imports.index');

    // Ver detalle de importación
    Route::get('/{import}', [SupplierImportController::class, 'show'])
        ->middleware('permission:supplier-imports.show');

    // Reprocesar importación fallida
    Route::post('/{import}/reprocess', [SupplierImportController::class, 'reprocess'])
        ->middleware('permission:supplier-imports.reprocess');

    // Estadísticas de importaciones
    Route::get('/statistics/summary', [SupplierImportController::class, 'statistics'])
        ->middleware('permission:supplier-imports.statistics');
});

// ============================================================================
// ENDPOINT PÚBLICO PARA SCRAPERS (sin auth:sanctum)
// ============================================================================
Route::post('/suppliers/{slug}/import', [SupplierImportController::class, 'import'])
    ->middleware('throttle:60,1'); // Rate limit: 60 requests por minuto

/* ============================================
   SETTINGS (Configuraciones del Sistema)
   ============================================ */
Route::middleware(['auth:sanctum', 'role:super-admin'])->prefix('settings')->group(function () {

    // Listar todas las configuraciones
    Route::get('/', [SettingController::class, 'index']);

    // Obtener por grupo
    Route::get('/group/{group}', [SettingController::class, 'getGroup']);

    // Obtener configuración específica
    Route::get('/{group}/{key}', [SettingController::class, 'get']);

    // Crear/actualizar configuración
    Route::post('/', [SettingController::class, 'set']);

    // Actualizar múltiples configuraciones
    Route::post('/bulk-update', [SettingController::class, 'bulkUpdate']);

    // Eliminar configuración
    Route::delete('/{group}/{key}', [SettingController::class, 'delete']);

    // Restaurar configuraciones por defecto
    Route::post('/restore-defaults', [SettingController::class, 'restoreDefaults']);
});

/* ============================================
   VALIDACIÓN SUNAT/RENIEC
   ============================================ */
Route::middleware('auth:sanctum')->prefix('sunat')->group(function () {
    Route::get('validate/{tipo}/{numero}', [SunatController::class, 'validateDocument'])
        ->middleware('throttle:10,1')
        ->middleware('permission:sunat.validate-document');
});

/* ============================================
   GEMINI AI
   ============================================ */
Route::middleware('auth:sanctum')->prefix('gemini')->group(function () {
    Route::post('/generate-product-info', [GeminiController::class, 'generateProductInfo'])
        ->middleware('permission:gemini.generate-product-info');

    Route::post('/generate-batch', [GeminiController::class, 'generateBatch'])
        ->middleware('permission:gemini.generate-batch');

    Route::post('/warm-cache', [GeminiController::class, 'warmCache'])
        ->middleware('permission:gemini.warm-cache');

    Route::post('/clear-cache', [GeminiController::class, 'clearCache'])
        ->middleware('permission:gemini.clear-cache');
});
