<?php

namespace App\Http\Requests\Entity;

use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Detectar si es estructura anidada o plana
        $isNested = $this->has('entity');
        
        if ($isNested) {
            return $this->nestedRules();
        }
        
        // Estructura plana (compatibilidad hacia atrás)
        return $this->flatRules();
    }

    /**
     * Reglas para estructura anidada
     */
    private function nestedRules(): array
    {
        $isSupplierOrBoth = in_array($this->input('entity.type'), ['supplier', 'both']);

        return [
            // Validaciones de Entity
            'entity' => 'required|array',
            'entity.type' => 'required|in:customer,supplier,both',
            'entity.tipo_documento' => [
                'required',
                'exists:document_types,code',
                function ($attribute, $value, $fail) use ($isSupplierOrBoth) {
                    if ($isSupplierOrBoth && $value !== '06') {
                        $fail('Los proveedores deben tener RUC (tipo_documento = 06).');
                    }
                }
            ],
            'entity.numero_documento' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $this->validateDocumentNumber($value, $this->input('entity.tipo_documento'), $this->input('entity.type'), $fail);
                }
            ],
            'entity.tipo_persona' => [
                'required',
                'in:natural,juridica',
                function ($attribute, $value, $fail) use ($isSupplierOrBoth) {
                    if ($isSupplierOrBoth && $value !== 'juridica') {
                        $fail('Los proveedores deben ser persona jurídica.');
                    }
                }
            ],
            'entity.first_name' => 'required_if:entity.tipo_persona,natural|string|max:100',
            'entity.last_name' => 'required_if:entity.tipo_persona,natural|string|max:100',
            'entity.business_name' => 'required_if:entity.tipo_persona,juridica|string|max:200',
            'entity.trade_name' => 'nullable|string|max:100',
            'entity.estado_sunat' => 'nullable|in:ACTIVO,BAJA,SUSPENDIDO',
            'entity.condicion_sunat' => 'nullable|in:HABIDO,NO HABIDO',
            'entity.is_active' => 'boolean',

            // Validaciones de Addresses
            'addresses' => [
                'nullable', // Antes required
                'array',
                function ($attribute, $value, $fail) {
                    if (empty($value)) return;
                    // Validar que al menos una dirección sea is_default
                    $hasDefault = collect($value)->contains('is_default', true);
                    if (!$hasDefault) {
                        $fail('Si agrega direcciones, debe marcar al menos una como principal (is_default = true).');
                    }
                }
            ],
            'addresses.*.address' => 'required|string|max:250',
            'addresses.*.ubigeo' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $countryCode = $this->input("addresses.{$index}.country_code");
                    
                    if ($countryCode === 'PE' && empty($value)) {
                        $fail('El ubigeo es obligatorio para direcciones en Perú.');
                    }
                    
                    if (!empty($value) && strlen($value) != 6) {
                        $fail('El ubigeo debe tener 6 dígitos.');
                    }
                },
                'exists:ubigeos,ubigeo',
            ],
            'addresses.*.country_code' => 'required|string|size:2|exists:countries,code',
            'addresses.*.phone' => 'nullable|string|max:20',
            'addresses.*.reference' => 'nullable|string|max:200',
            'addresses.*.label' => 'nullable|string|max:50',
            'addresses.*.is_default' => 'boolean',

            // Validaciones de Contacts
            'contacts' => [
                'nullable', // Antes required
                'array',
                function ($attribute, $value, $fail) {
                    if (empty($value)) return;
                    // Validar que al menos un contacto sea is_primary
                    $hasPrimary = collect($value)->contains('is_primary', true);
                    if (!$hasPrimary) {
                        $fail('Si agrega contactos, debe marcar al menos uno como principal (is_primary = true).');
                    }
                }
            ],
            'contacts.*.full_name' => 'required|string|max:200',
            'contacts.*.position' => 'nullable|string|max:100',
            'contacts.*.email' => 'required|email|max:100',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.web_page' => 'nullable|string|max:100',
            'contacts.*.observations' => 'nullable|string|max:1000',
            'contacts.*.is_primary' => 'boolean',
        ];
    }

    /**
     * Reglas para estructura plana (compatibilidad hacia atrás)
     */
    private function flatRules(): array
    {
        $isSupplierOrBoth = in_array($this->type, ['supplier', 'both']);

        return [
            'type' => 'required|in:customer,supplier,both',
            'tipo_documento' => [
                'required',
                'exists:document_types,code',
                function ($attribute, $value, $fail) use ($isSupplierOrBoth) {
                    if ($isSupplierOrBoth && $value !== '06') {
                        $fail('Los proveedores deben tener RUC (tipo_documento = 06).');
                    }
                }
            ],
            'numero_documento' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $this->validateDocumentNumber($value, $this->tipo_documento, $this->type, $fail);
                }
            ],
            'tipo_persona' => [
                'required',
                'in:natural,juridica',
                function ($attribute, $value, $fail) use ($isSupplierOrBoth) {
                    if ($isSupplierOrBoth && $value !== 'juridica') {
                        $fail('Los proveedores deben ser persona jurídica.');
                    }
                }
            ],
            'first_name' => 'required_if:tipo_persona,natural|string|max:100',
            'last_name' => 'required_if:tipo_persona,natural|string|max:100',
            'business_name' => 'required_if:tipo_persona,juridica|string|max:200',
            'trade_name' => 'nullable|string|max:100',

            // Address fields
            'address' => ['nullable', 'string', 'max:250'],
            'country_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'ubigeo' => [
                'nullable',
                'required_if:country_code,PE',
                'exists:ubigeos,ubigeo',
                'size:6'
            ],
            'address_phone' => ['nullable', 'string', 'max:20'],
            'address_reference' => 'nullable|string|max:200',
            'address_label' => 'nullable|string|max:50',

            // Contact fields
            'contact_name' => ['nullable', 'string', 'max:200'],
            'contact_position' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_web_page' => ['nullable', 'string', 'max:100'],
            'contact_observations' => ['nullable', 'string', 'max:1000'],

            // SUNAT fields
            'estado_sunat' => 'nullable|in:ACTIVO,BAJA,SUSPENDIDO',
            'condicion_sunat' => 'nullable|in:HABIDO,NO HABIDO',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Validar número de documento y detectar duplicados
     */
    private function validateDocumentNumber(string $value, string $tipoDocumento, string $type, $fail): void
    {
        $documentType = DocumentType::find($tipoDocumento);
        
        if (!$documentType) {
            $fail('Tipo de documento no válido');
            return;
        }

        // Validar longitud
        if ($documentType->length && strlen($value) != $documentType->length) {
            $fail("{$documentType->name} debe tener {$documentType->length} dígitos");
            return;
        }

        // Validar patrón
        if ($documentType->validation_pattern && !$documentType->validateDocument($value)) {
            $fail("El formato del {$documentType->name} no es válido");
            return;
        }

        // Verificar duplicados
        $existing = \App\Models\Entity::where('tipo_documento', $tipoDocumento)
            ->where('numero_documento', $value)
            ->first();

        if ($existing) {
            $requestedType = $type;
            $existingType = $existing->type;

            // Caso 1: Mismo tipo exacto
            if ($existingType === $requestedType) {
                $typeLabel = match($existingType) {
                    'customer' => 'cliente',
                    'supplier' => 'proveedor',
                    'both' => 'cliente y proveedor',
                };
                
                $fail("Ya existe un {$typeLabel} registrado con este documento.");
                return;
            }

            // Caso 2: Ya es 'both'
            if ($existingType === 'both') {
                $fail("Este documento ya está registrado como cliente y proveedor.");
                return;
            }

            // Caso 3: Tipos diferentes - sugerir conversión
            $existingLabel = match($existingType) {
                'customer' => 'cliente',
                'supplier' => 'proveedor',
            };
            
            $requestedLabel = match($requestedType) {
                'customer' => 'cliente',
                'supplier' => 'proveedor',
                'both' => 'cliente y proveedor',
            };

            // Guardar datos estructurados para el frontend
            $errorData = [
                'error_code' => 'DUPLICATE_ENTITY_DIFFERENT_TYPE',
                'message' => "Ya existe un {$existingLabel} con este documento.",
                'existing_entity' => [
                    'id' => $existing->id,
                    'type' => $existing->type,
                    'type_label' => $existingLabel,
                    'full_name' => $existing->full_name,
                    'numero_documento' => $existing->numero_documento,
                ],
                'requested_type' => $requestedType,
                'requested_type_label' => $requestedLabel,
                'suggested_action' => 'convert_to_both',
                'suggestion_message' => "¿Deseas convertirlo a cliente y proveedor?",
            ];

            session(['entity_duplicate_error' => $errorData]);
            $fail($errorData['message']);
        }
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        $isSupplierOrBoth = in_array($this->input('entity.type') ?? $this->type, ['supplier', 'both']);

        $messages = [
            'entity.type.required' => 'El tipo de entidad es obligatorio.',
            'entity.tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'entity.numero_documento.required' => 'El número de documento es obligatorio.',
            'entity.tipo_persona.required' => 'El tipo de persona es obligatorio.',
            
            'addresses.*.address.required' => 'La dirección es obligatoria.',
            'addresses.*.country_code.required' => 'El código de país es obligatorio.',
            
            'contacts.*.full_name.required' => 'El nombre del contacto es obligatorio.',
            'contacts.*.email.required' => 'El email del contacto es obligatorio.',
        ];

        if ($isSupplierOrBoth) {
            $messages['entity.tipo_persona.in'] = 'Para proveedores, el tipo de persona debe ser juridica.';
            $messages['entity.business_name.required_if'] = 'La razón social es obligatoria para proveedores.';
        }

        return $messages;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si es estructura plana, convertir a anidada
        if (!$this->has('entity') && $this->has('type')) {
            $this->convertFlatToNested();
        }

        // Set default type if not provided
        if (!$this->has('entity.type') && !$this->has('type')) {
            $this->merge(['entity' => array_merge($this->input('entity', []), ['type' => 'customer'])]);
        }

        // Forzar tipo_persona para proveedores
        if (in_array($this->input('entity.type') ?? $this->type, ['supplier', 'both'])) {
            if ($this->has('entity')) {
                $this->merge(['entity' => array_merge($this->input('entity'), ['tipo_persona' => 'juridica'])]);
            } else {
                $this->merge(['tipo_persona' => 'juridica']);
            }
        }

        // Trim strings
        if ($this->has('entity.numero_documento')) {
            $entity = $this->input('entity');
            $entity['numero_documento'] = trim($entity['numero_documento']);
            $this->merge(['entity' => $entity]);
        } elseif ($this->has('numero_documento')) {
            $this->merge(['numero_documento' => trim($this->numero_documento)]);
        }
    }

    /**
     * Convertir estructura plana a anidada
     */
    private function convertFlatToNested(): void
    {
        $entityFields = [
            'type', 'tipo_documento', 'numero_documento', 'tipo_persona',
            'business_name', 'trade_name', 'first_name', 'last_name',
            'estado_sunat', 'condicion_sunat', 'is_active', 'registered_at'
        ];

        $addressFields = [
            'address', 'ubigeo', 'country_code', 'phone' => 'address_phone',
            'reference' => 'address_reference', 'label' => 'address_label'
        ];

        $contactFields = [
            'full_name' => 'contact_name', 'position' => 'contact_position',
            'email' => 'contact_email', 'phone' => 'contact_phone',
            'web_page' => 'contact_web_page', 'observations' => 'contact_observations'
        ];

        // Extraer datos de entity
        $entity = [];
        foreach ($entityFields as $field) {
            if ($this->has($field)) {
                $entity[$field] = $this->input($field);
            }
        }

        // Extraer datos de address
        $address = ['is_default' => true];
        foreach ($addressFields as $newField => $oldField) {
            $field = is_numeric($newField) ? $oldField : $newField;
            $inputField = is_numeric($newField) ? $oldField : $oldField;
            
            if ($this->has($inputField)) {
                $address[$field] = $this->input($inputField);
            }
        }
        
        // Renombrar phone a address_phone si existe
        if (isset($address['address_phone'])) {
            $address['phone'] = $address['address_phone'];
            unset($address['address_phone']);
        } elseif ($this->has('address_phone')) {
            $address['phone'] = $this->input('address_phone');
        }

        // Extraer datos de contact
        $contact = ['is_primary' => true];
        foreach ($contactFields as $newField => $oldField) {
            $field = is_numeric($newField) ? $oldField : $newField;
            $inputField = is_numeric($newField) ? $oldField : $oldField;
            
            if ($this->has($inputField)) {
                $contact[$field] = $this->input($inputField);
            }
        }
        
        // Auto-generar full_name si no existe
        if (empty($contact['full_name'])) {
            if (!empty($entity['first_name']) && !empty($entity['last_name'])) {
                $contact['full_name'] = trim($entity['first_name'] . ' ' . $entity['last_name']);
            } elseif (!empty($entity['business_name'])) {
                $contact['full_name'] = $entity['business_name'];
            }
        }

        // Merge la estructura anidada
        $this->merge([
            'entity' => $entity,
            'addresses' => [array_filter($address)],
            'contacts' => [array_filter($contact)],
        ]);
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors();
        
        // Si hay datos de error estructurado en sesión, agregarlos a la respuesta
        if (session()->has('entity_duplicate_error')) {
            $duplicateError = session()->pull('entity_duplicate_error');
            
            throw new \Illuminate\Validation\ValidationException(
                $validator,
                response()->json([
                    'message' => 'Los datos proporcionados no son válidos.',
                    'errors' => $errors->toArray(),
                    'duplicate_entity' => $duplicateError,
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }
}