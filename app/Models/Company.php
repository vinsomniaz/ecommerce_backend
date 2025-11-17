<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Company extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'company';

    protected $fillable = [
        'RUC',
        'razon_social',
        'direccion_fiscal',
        'ubigeo',
        'certificado_digital_url',
        'usuario_sol',
        'clave_sol_hash',
    ];

    protected $hidden = [
        'clave_sol_hash',
    ];

    // Relaciones
    public function ubigeo(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class, 'ubigeo', 'ubigeo');
    }

    // Accessors
    public function getUbigeoFullNameAttribute(): ?string
    {
        if (!$this->ubigeo) return null;

        return "{$this->ubigeo->distrito}, {$this->ubigeo->provincia}, {$this->ubigeo->departamento}";
    }

    public function getHasCertificateAttribute(): bool
    {
        return !empty($this->certificado_digital_url);
    }

    public function getHasSolCredentialsAttribute(): bool
    {
        return !empty($this->usuario_sol) && !empty($this->clave_sol_hash);
    }

    // Activity Log
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['razon_social', 'direccion_fiscal', 'ubigeo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Singleton pattern (solo hay una empresa)
    public static function current(): ?self
    {
        return self::first();
    }
}
