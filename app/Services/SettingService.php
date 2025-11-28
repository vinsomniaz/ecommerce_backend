<?php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$group}.{$key}", 3600, function() use ($group, $key, $default) {
            $setting = Setting::where('group', $group)->where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }
            
            return $this->castValue($setting->value, $setting->type);
        });
    }
    
    public function set(string $group, string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value]
        );
        
        Cache::forget("setting.{$group}.{$key}");
    }
    
    private function castValue(mixed $value, string $type): mixed
    {
        return match($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
    
    public function getGroup(string $group): array
    {
        return Cache::remember("settings.group.{$group}", 3600, function() use ($group) {
            return Setting::where('group', $group)
                ->get()
                ->mapWithKeys(fn($s) => [$s->key => $this->castValue($s->value, $s->type)])
                ->toArray();
        });
    }
}