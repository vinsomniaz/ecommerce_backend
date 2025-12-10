<?php

namespace App\Http\Requests\Entity;

use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntityRequest extends FormRequest
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
        $entityId = $this->route('entity');

        return [
            'type' => 'sometimes|in:customer,supplier,both',
            'tipo_documento' => 'sometimes|exists:document_types,code',
            'numero_documento' => [
                'sometimes',
                'string',
                Rule::unique('entities')->where(function ($query) {
                    return $query->where('tipo_documento', $this->tipo_documento ?? $this->entity->tipo_documento);
                })->ignore($entityId),
                function ($attribute, $value, $fail) {
                    $tipoDoc = $this->tipo_documento ?? $this->entity->tipo_documento;
                    $documentType = DocumentType::find($tipoDoc);
                    
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
            'tipo_persona' => 'sometimes|in:natural,juridica',
            'first_name' => 'required_if:tipo_persona,natural|string|max:100',
            'last_name' => 'required_if:tipo_persona,natural|string|max:100',
            'business_name' => 'required_if:tipo_persona,juridica|string|max:200',
            'trade_name' => 'nullable|string|max:100',
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('entities')->ignore($entityId)
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:250',
            'country_code' => ['sometimes', 'string', 'size:2', 'exists:countries,code'],
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
        return [
            'type.in' => 'El tipo debe ser customer, supplier o both',
            'tipo_documento.exists' => 'El tipo de documento no es válido',
            'numero_documento.unique' => 'Este número de documento ya está registrado',
            'tipo_persona.in' => 'El tipo de persona debe ser natural o juridica',
            'first_name.required_if' => 'El nombre es obligatorio para personas naturales',
            'last_name.required_if' => 'El apellido es obligatorio para personas naturales',
            'business_name.required_if' => 'La razón social es obligatoria para personas jurídicas',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado',
            'phone.max' => 'El teléfono no puede tener más de 20 caracteres',
            'ubigeo.exists' => 'El ubigeo no es válido',
            'ubigeo.size' => 'El ubigeo debe tener 6 caracteres',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('numero_documento')) {
            $this->merge(['numero_documento' => trim($this->numero_documento)]);
        }
    }
}