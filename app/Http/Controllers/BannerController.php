<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $query = Banner::query();

        if ($request->has('section')) {
            $query->where('section', $request->input('section'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $banners = $query->orderBy('sort_order')->orderBy('id', 'desc')->get();

        // Append image URL
        $banners->transform(function ($banner) {
            $banner->image_url = $banner->getFirstMediaUrl('image');
            return $banner;
        });

        return response()->json($banners);
    }

    public function store(StoreBannerRequest $request)
    {
        $banner = Banner::create($request->validated());

        if ($request->hasFile('image')) {
            $banner->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return response()->json([
            'message' => 'Banner creado exitosamente',
            'banner' => $banner->load('media'),
            'image_url' => $banner->getFirstMediaUrl('image')
        ], 201);
    }

    public function show(Banner $banner)
    {
        $banner->image_url = $banner->getFirstMediaUrl('image');
        return response()->json($banner);
    }

    public function update(UpdateBannerRequest $request, Banner $banner)
    {
        $banner->update($request->validated());

        if ($request->hasFile('image')) {
            $banner->clearMediaCollection('image');
            $banner->addMediaFromRequest('image')->toMediaCollection('image');
        }

        return response()->json([
            'message' => 'Banner actualizado exitosamente',
            'banner' => $banner,
            'image_url' => $banner->getFirstMediaUrl('image')
        ]);
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        return response()->json(['message' => 'Banner eliminado exitosamente']);
    }
}
