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
        $entityId = $this->route('entity');

        return [
            // Validaciones de Entity (todas opcionales en update)
            'entity' => 'sometimes|array',
            'entity.type' => 'sometimes|in:customer,supplier,both',
            'entity.tipo_documento' => 'sometimes|exists:document_types,code',
            'entity.numero_documento' => [
                'sometimes',
                'string',
                Rule::unique('entities', 'numero_documento')->where(function ($query) {
                    return $query->where('tipo_documento', $this->input('entity.tipo_documento'));
                })->ignore($entityId),
            ],
            'entity.tipo_persona' => 'sometimes|in:natural,juridica',
            'entity.first_name' => 'sometimes|string|max:100',
            'entity.last_name' => 'sometimes|string|max:100',
            'entity.business_name' => 'sometimes|string|max:200',
            'entity.trade_name' => 'nullable|string|max:100',
            'entity.estado_sunat' => 'nullable|in:ACTIVO,BAJA,SUSPENDIDO',
            'entity.condicion_sunat' => 'nullable|in:HABIDO,NO HABIDO',
            'entity.is_active' => 'boolean',

            // Validaciones de Addresses (opcional, pero si se envía debe ser válido)
            'addresses' => [
                'sometimes',
                'array',
                function ($attribute, $value, $fail) {
                    if (empty($value)) return;
                    // Validar que al menos una dirección sea is_default
                    $hasDefault = collect($value)->contains('is_default', true);
                    if (!$hasDefault) {
                        $fail('Debe marcar al menos una dirección como principal (is_default = true).');
                    }
                }
            ],
            'addresses.*.id' => 'nullable|integer|exists:addresses,id', // Allow ID for updates
            'addresses.*.address' => [
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $hasId = $this->input("addresses.{$index}.id");

                    // Required only if creating new (no ID)
                    if (!$hasId && empty($value)) {
                        $fail('La dirección es obligatoria.');
                    }

                    // Validate max length if provided
                    if (!empty($value) && strlen($value) > 250) {
                        $fail('La dirección no debe exceder 250 caracteres.');
                    }
                }
            ],
            'addresses.*.ubigeo' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $countryCode = $this->input("addresses.{$index}.country_code");

                    if ($countryCode === 'PE' && empty($value)) {
                        $fail('El ubigeo es obligatorio para direcciones en Perú.');
                    }
                },
                'exists:ubigeos,ubigeo',
            ],
            'addresses.*.country_code' => [
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $hasId = $this->input("addresses.{$index}.id");

                    // Required only if creating new (no ID)
                    if (!$hasId && empty($value)) {
                        $fail('El código de país es obligatorio.');
                    }

                    // Validate format if provided
                    if (!empty($value) && (strlen($value) != 2 || !\App\Models\Country::where('code', $value)->exists())) {
                        $fail('El código de país no es válido.');
                    }
                }
            ],
            'addresses.*.phone' => 'nullable|string|max:20',
            'addresses.*.reference' => 'nullable|string|max:200',
            'addresses.*.label' => 'nullable|string|max:50',
            'addresses.*.is_default' => 'boolean',

            // Validaciones de Contacts (opcional, pero si se envía debe ser válido)
            'contacts' => [
                'sometimes',
                'array',
                function ($attribute, $value, $fail) {
                    if (empty($value)) return;
                    // Validar que al menos un contacto sea is_primary
                    $hasPrimary = collect($value)->contains('is_primary', true);
                    if (!$hasPrimary) {
                        $fail('Debe marcar al menos un contacto como principal (is_primary = true).');
                    }
                }
            ],
            'contacts.*.id' => 'nullable|integer|exists:contacts,id', // Allow ID for updates
            'contacts.*.full_name' => [
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $hasId = $this->input("contacts.{$index}.id");

                    // Required only if creating new (no ID)
                    if (!$hasId && empty($value)) {
                        $fail('El nombre del contacto es obligatorio.');
                    }

                    // Validate max length if provided
                    if (!empty($value) && strlen($value) > 200) {
                        $fail('El nombre del contacto no debe exceder 200 caracteres.');
                    }
                }
            ],
            'contacts.*.position' => 'nullable|string|max:100',
            'contacts.*.email' => [
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $hasId = $this->input("contacts.{$index}.id");

                    // Required only if creating new (no ID)
                    if (!$hasId && empty($value)) {
                        $fail('El email del contacto es obligatorio.');
                    }

                    // Validate email format if provided
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $fail('El email del contacto no es válido.');
                    }

                    // Validate max length if provided
                    if (!empty($value) && strlen($value) > 100) {
                        $fail('El email del contacto no debe exceder 100 caracteres.');
                    }
                }
            ],
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
        $entityId = $this->route('entity');

        return [
            'type' => 'sometimes|in:customer,supplier,both',
            'tipo_documento' => 'sometimes|exists:document_types,code',
            'numero_documento' => [
                'sometimes',
                'string',
                Rule::unique('entities')->where(function ($query) {
                    return $query->where('tipo_documento', $this->tipo_documento);
                })->ignore($entityId),
            ],
            'tipo_persona' => 'sometimes|in:natural,juridica',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'business_name' => 'sometimes|string|max:200',
            'trade_name' => 'nullable|string|max:100',

            // Address fields
            'address' => 'sometimes|string|max:250',
            'country_code' => 'sometimes|string|size:2|exists:countries,code',
            'ubigeo' => [
                'nullable',
                'sometimes',
                'required_if:country_code,PE',
                'exists:ubigeos,ubigeo',
                'size:6'
            ],
            'address_phone' => 'sometimes|string|max:20',
            'address_reference' => 'nullable|string|max:200',
            'address_label' => 'nullable|string|max:50',

            // Contact fields
            'contact_name' => 'sometimes|string|max:200',
            'contact_position' => 'nullable|string|max:100',
            'contact_email' => 'sometimes|email|max:100',
            'contact_phone' => 'sometimes|string|max:20',
            'contact_web_page' => 'nullable|string|max:100',
            'contact_observations' => 'nullable|string|max:1000',

            // SUNAT fields
            'estado_sunat' => 'nullable|in:ACTIVO,BAJA,SUSPENDIDO',
            'condicion_sunat' => 'nullable|in:HABIDO,NO HABIDO',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'entity.type.in' => 'El tipo de entidad debe ser customer, supplier o both.',
            'entity.tipo_documento.exists' => 'El tipo de documento no es válido.',
            'entity.numero_documento.unique' => 'Este número de documento ya está registrado.',

            'addresses.*.address.required' => 'La dirección es obligatoria.',
            'addresses.*.country_code.required' => 'El código de país es obligatorio.',

            'contacts.*.full_name.required' => 'El nombre del contacto es obligatorio.',
            'contacts.*.email.required' => 'El email del contacto es obligatorio.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si es estructura plana, convertir a anidada
        if (!$this->has('entity') && ($this->has('type') || $this->has('business_name') || $this->has('first_name'))) {
            $this->convertFlatToNested();
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
            'type',
            'tipo_documento',
            'numero_documento',
            'tipo_persona',
            'business_name',
            'trade_name',
            'first_name',
            'last_name',
            'estado_sunat',
            'condicion_sunat',
            'is_active'
        ];

        $addressFields = [
            'address',
            'ubigeo',
            'country_code',
            'phone' => 'address_phone',
            'reference' => 'address_reference',
            'label' => 'address_label'
        ];

        $contactFields = [
            'full_name' => 'contact_name',
            'position' => 'contact_position',
            'email' => 'contact_email',
            'phone' => 'contact_phone',
            'web_page' => 'contact_web_page',
            'observations' => 'contact_observations'
        ];

        // Extraer datos de entity
        $entity = [];
        foreach ($entityFields as $field) {
            if ($this->has($field)) {
                $entity[$field] = $this->input($field);
            }
        }

        // Extraer datos de address (solo si hay algún campo de dirección)
        $hasAddressData = $this->has('address') || $this->has('ubigeo') || $this->has('country_code');
        $addresses = null;

        if ($hasAddressData) {
            $address = ['is_default' => true];
            foreach ($addressFields as $newField => $oldField) {
                $field = is_numeric($newField) ? $oldField : $newField;
                $inputField = is_numeric($newField) ? $oldField : $oldField;

                if ($this->has($inputField)) {
                    $address[$field] = $this->input($inputField);
                }
            }

            if (isset($address['address_phone'])) {
                $address['phone'] = $address['address_phone'];
                unset($address['address_phone']);
            }

            $addresses = [array_filter($address)];
        }

        // Extraer datos de contact (solo si hay algún campo de contacto)
        $hasContactData = $this->has('contact_email') || $this->has('contact_name');
        $contacts = null;

        if ($hasContactData) {
            $contact = ['is_primary' => true];
            foreach ($contactFields as $newField => $oldField) {
                $field = is_numeric($newField) ? $oldField : $newField;
                $inputField = is_numeric($newField) ? $oldField : $oldField;

                if ($this->has($inputField)) {
                    $contact[$field] = $this->input($inputField);
                }
            }

            $contacts = [array_filter($contact)];
        }

        // Merge la estructura anidada
        $merged = [];
        if (!empty($entity)) {
            $merged['entity'] = $entity;
        }
        if ($addresses !== null) {
            $merged['addresses'] = $addresses;
        }
        if ($contacts !== null) {
            $merged['contacts'] = $contacts;
        }

        if (!empty($merged)) {
            $this->merge($merged);
        }
    }
}
