<?php

namespace App\Http\Requests\Entity;

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
                'in:01,06',
                // Regla para asegurar que proveedores solo usen RUC
                function ($attribute, $value, $fail) use ($isSupplierOrBoth) {
                    if ($isSupplierOrBoth && $value !== '06') {
                        $fail('Los proveedores deben tener RUC (tipo_documento = 06).');
                    }
                }
            ],
            'numero_documento' => [
                'required',
                'numeric',
                Rule::unique('entities', 'numero_documento')->where(function ($query) {
                    return $query->where('tipo_documento', $this->tipo_documento);
                }),
                function ($attribute, $value, $fail) {
                    if ($this->tipo_documento == '01' && strlen($value) != 8) {
                        $fail('DNI debe tener 8 dígitos');
                    }
                    if ($this->tipo_documento == '06') {
                        if (strlen($value) != 11) {
                            $fail('RUC debe tener 11 dígitos');
                        }
                        // Validar que RUC empiece con 10 o 20
                        if (!str_starts_with($value, '10') && !str_starts_with($value, '20')) {
                            $fail('RUC debe empezar con 10 o 20');
                        }
                    }
                }
            ],
            // Para proveedores, tipo_persona siempre será 'juridica'
            'tipo_persona' => ['required', Rule::in($isSupplierOrBoth ? ['juridica'] : ['natural', 'juridica'])],
            'first_name' => 'required_if:tipo_persona,natural|string|max:100',
            'last_name' => 'required_if:tipo_persona,natural|string|max:100',
            'business_name' => 'required_if:tipo_persona,juridica|string|max:200',
            'trade_name' => 'nullable|string|max:100',

            // Campos que son obligatorios para proveedores
            'email' => [$isSupplierOrBoth ? 'required' : 'nullable', 'email', 'unique:entities,email', 'max:100'],
            'phone' => [$isSupplierOrBoth ? 'required' : 'nullable', 'digits:9'],
            'address' => [$isSupplierOrBoth ? 'required' : 'nullable', 'string', 'max:250'],

            'ubigeo' => 'nullable|exists:ubigeos,ubigeo|size:6',
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
            'tipo_documento.in' => 'El tipo de documento debe ser 01 (DNI) o 06 (RUC)',
            'numero_documento.required' => 'El número de documento es obligatorio',
            'numero_documento.unique' => 'Este número de documento ya está registrado',
            'numero_documento.numeric' => 'El número de documento debe ser numérico',
            'tipo_persona.required' => 'El tipo de persona es obligatorio',
            'tipo_persona.in' => 'El tipo de persona debe ser natural o juridica',
            'first_name.required_if' => 'El nombre es obligatorio para personas naturales',
            'last_name.required_if' => 'El apellido es obligatorio para personas naturales',
            'business_name.required_if' => 'La razón social es obligatoria para personas jurídicas',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado',
            'phone.digits' => 'El teléfono debe tener 9 dígitos',
            'ubigeo.exists' => 'El ubigeo no es válido',
            'ubigeo.size' => 'El ubigeo debe tener 6 caracteres',
        ];

        // Se determina dinámicamente el mensaje correcto para 'tipo_persona.in'
        if ($isSupplierOrBoth) {
            $messages['tipo_persona.in'] = 'Para proveedores, el tipo de persona debe ser juridica.';
            $messages['business_name.required_if'] = 'La razón social es obligatoria para proveedores.';
            $messages['email.required'] = 'El email es obligatorio para proveedores.';
            $messages['phone.required'] = 'El teléfono es obligatorio para proveedores.';
            $messages['address.required'] = 'La dirección es obligatoria para proveedores.';
        } else {
            $messages['tipo_persona.in'] = 'El tipo de persona debe ser natural o juridica.';
            $messages['business_name.required_if'] = 'La razón social es obligatoria para personas jurídicas.';
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
