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
        $isSupplierOrBoth = in_array($this->type, ['supplier', 'both']);

        return [
            'type' => 'required|in:customer,supplier,both',
            'tipo_documento' => [
                'required',
                'exists:document_types,code',
                function ($attribute, $value, $fail) use ($isSupplierOrBoth) {
                    // Proveedores solo pueden usar RUC
                    if ($isSupplierOrBoth && $value !== '06') {
                        $fail('Los proveedores deben tener RUC (tipo_documento = 06).');
                    }
                }
            ],
            'numero_documento' => [
                'required',
                'string',
                Rule::unique('entities', 'numero_documento')->where(function ($query) {
                    return $query->where('tipo_documento', $this->tipo_documento);
                }),
                function ($attribute, $value, $fail) {
                    $documentType = DocumentType::find($this->tipo_documento);
                    
                    if (!$documentType) {
                        $fail('Tipo de documento no válido');
                        return;
                    }

                    // Validar longitud si está definida
                    if ($documentType->length && strlen($value) != $documentType->length) {
                        $fail("{$documentType->name} debe tener {$documentType->length} dígitos");
                        return;
                    }

                    // Validar patrón si está definido
                    if ($documentType->validation_pattern && !$documentType->validateDocument($value)) {
                        $fail("El formato del {$documentType->name} no es válido");
                    }
                }
            ],
            // Para proveedores, tipo_persona siempre será 'juridica'
            'tipo_persona' => ['required', Rule::in($isSupplierOrBoth ? ['juridica'] : ['natural', 'juridica'])],
            'first_name' => 'required_if:tipo_persona,natural|string|max:100',
            'last_name' => 'required_if:tipo_persona,natural|string|max:100',
            'business_name' => 'required_if:tipo_persona,juridica|string|max:200',
            'trade_name' => 'nullable|string|max:100',

            // Campos obligatorios para proveedores
            'email' => [$isSupplierOrBoth ? 'required' : 'nullable', 'email', 'unique:entities,email', 'max:100'],
            'phone' => [$isSupplierOrBoth ? 'required' : 'nullable', 'string', 'max:20'],
            'address' => [$isSupplierOrBoth ? 'required' : 'nullable', 'string', 'max:250'],

            'country_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'ubigeo' => [
                'nullable',
                'required_if:country_code,PE',
                'exists:ubigeos,ubigeo',
                'size:6'
            ],
            'estado_sunat' => 'nullable|in:activo,baja,suspendido',
            'condicion_sunat' => 'nullable|in:habido,no_habido',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $isSupplierOrBoth = in_array($this->type, ['supplier', 'both']);
        
        $messages = [
            'type.required' => 'El tipo de entidad es obligatorio',
            'type.in' => 'El tipo debe ser customer, supplier o both',
            'tipo_documento.required' => 'El tipo de documento es obligatorio',
            'tipo_documento.exists' => 'El tipo de documento no es válido',
            'numero_documento.required' => 'El número de documento es obligatorio',
            'numero_documento.unique' => 'Este número de documento ya está registrado',
            'tipo_persona.required' => 'El tipo de persona es obligatorio',
            'first_name.required_if' => 'El nombre es obligatorio para personas naturales',
            'last_name.required_if' => 'El apellido es obligatorio para personas naturales',
            'business_name.required_if' => 'La razón social es obligatoria para personas jurídicas',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado',
            'phone.max' => 'El teléfono no puede tener más de 20 caracteres',
            'ubigeo.exists' => 'El ubigeo no es válido',
            'ubigeo.size' => 'El ubigeo debe tener 6 caracteres',
            'ubigeo.required_if' => 'El ubigeo es obligatorio para entidades en Perú.',
        ];

        if ($isSupplierOrBoth) {
            $messages['tipo_persona.in'] = 'Para proveedores, el tipo de persona debe ser juridica.';
            $messages['business_name.required_if'] = 'La razón social es obligatoria para proveedores.';
            $messages['email.required'] = 'El email es obligatorio para proveedores.';
            $messages['phone.required'] = 'El teléfono es obligatorio para proveedores.';
            $messages['address.required'] = 'La dirección es obligatoria para proveedores.';
        }

        return $messages;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default type if not provided
        if (!$this->has('type')) {
            $this->merge(['type' => 'customer']);
        }

        if (in_array($this->type, ['supplier', 'both'])) {
            $this->merge(['tipo_persona' => 'juridica']);
        }

        // Trim strings
        if ($this->has('numero_documento')) {
            $this->merge(['numero_documento' => trim($this->numero_documento)]);
        }
    }
}