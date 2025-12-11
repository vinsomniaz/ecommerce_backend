<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $table = 'document_types';
    
    protected $primaryKey = 'code';
    
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'length',
        'validation_pattern',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'length' => 'integer',
    ];

    /**
     * Relación con entidades
     */
    public function entities()
    {
        return $this->hasMany(Entity::class, 'tipo_documento', 'code');
    }

    /**
     * Scope para tipos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Validar un número de documento según el patrón
     */
    public function validateDocument(string $document): bool
    {
        if (!$this->validation_pattern) {
            return true;
        }

        return preg_match('/' . $this->validation_pattern . '/', $document) === 1;
    }
}