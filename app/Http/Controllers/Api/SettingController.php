<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settingService
    ) {}

    public function index(): JsonResponse
    {
        $settings = Setting::all()->groupBy('group');

        return response()->json(['data' => $settings]);
    }

    public function getGroup(string $group): JsonResponse
    {
        $settings = $this->settingService->getGroup($group);

        return response()->json(['data' => $settings]);
    }

    public function get(string $group, string $key): JsonResponse
    {
        $value = $this->settingService->get($group, $key);

        if ($value === null) {
            return response()->json([
                'error' => 'setting_not_found',
                'message' => "Configuración '{$group}.{$key}' no encontrada",
            ], 404);
        }

        return response()->json(['data' => $value]);
    }

    public function set(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => 'required|string|max:50',
            'key' => 'required|string|max:100',
            'value' => 'required',
            'type' => 'nullable|in:string,integer,decimal,boolean,json',
            'description' => 'nullable|string',
        ]);

        $this->settingService->set(
            $validated['group'],
            $validated['key'],
            $validated['value']
        );

        return response()->json([
            'message' => 'Configuración guardada exitosamente',
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.group' => 'required|string',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($validated['settings'] as $setting) {
            $this->settingService->set(
                $setting['group'],
                $setting['key'],
                $setting['value']
            );
        }

        return response()->json([
            'message' => 'Configuraciones actualizadas exitosamente',
            'updated_count' => count($validated['settings']),
        ]);
    }

    public function delete(string $group, string $key): JsonResponse
    {
        Setting::where('group', $group)->where('key', $key)->delete();

        return response()->json([
            'message' => 'Configuración eliminada exitosamente',
        ]);
    }

    public function restoreDefaults(): JsonResponse
    {
        // Ejecutar seeder
        Artisan::call('db:seed', ['--class' => 'DefaultSettingsSeeder']);

        return response()->json([
            'message' => 'Configuraciones restauradas a valores por defecto',
        ]);
    }
}
