<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EcommerceConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EcommerceConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = EcommerceConfig::query()->orderBy('sort_order')->orderBy('created_at', 'desc');

        if ($request->has('key')) {
            $query->where('key', $request->key);
        }

        if ($request->has('position')) {
            $query->where('key', $request->position);
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $configs = $query->get();

        $configs->transform(function ($config) {
            $config->image_url = $config->getFirstMediaUrl('image');
            return $config;
        });

        return response()->json([
            'data' => $configs
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
            'image' => 'required|image|max:10240', // Max 10MB
            'title' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $config = EcommerceConfig::create([
            'key' => $request->key,
            'title' => $request->title,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        if ($request->hasFile('image')) {
            $config->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return response()->json([
            'message' => 'Configuración creada exitosamente',
            'data' => $config->load('media'),
            'image_url' => $config->getFirstMediaUrl('image')
        ], Response::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $config = EcommerceConfig::findOrFail($id);

        $request->validate([
            'key' => 'sometimes|string|max:255',
            'image' => 'nullable|image|max:10240',
            'title' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $config->update($request->only([
            'key',
            'title',
            'sort_order',
            'is_active'
        ]));

        if ($request->hasFile('image')) {
            $config->clearMediaCollection('image');
            $config->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return response()->json([
            'message' => 'Configuración actualizada exitosamente',
            'data' => $config,
            'image_url' => $config->getFirstMediaUrl('image')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $config = EcommerceConfig::findOrFail($id);
        $config->delete();

        return response()->json([
            'message' => 'Configuración eliminada exitosamente'
        ]);
    }
}
