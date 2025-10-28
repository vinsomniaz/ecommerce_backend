<?php
// app/Http/Requests/Products/UploadProductImagesRequest.php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Preparar los datos para validación
     */
    protected function prepareForValidation()
    {
        $images = [];

        // Obtener todos los archivos del request
        $allFiles = $this->allFiles();

        // Buscar archivos con el nombre 'images' (con o sin corchetes)
        foreach ($allFiles as $key => $file) {
            if ($key === 'images' || str_starts_with($key, 'images[')) {
                if (is_array($file)) {
                    $images = array_merge($images, $file);
                } else {
                    $images[] = $file;
                }
            }
        }

        // Si encontramos archivos, los asignamos
        if (!empty($images)) {
            $this->merge(['images' => $images]);
        }
    }

    public function rules(): array
    {
        return [
            'images' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],
            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB en kilobytes
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'Debe proporcionar al menos una imagen',
            'images.array' => 'El formato de imágenes no es válido',
            'images.min' => 'Debe proporcionar al menos una imagen',
            'images.max' => 'No puede subir más de 5 imágenes a la vez',

            'images.*.required' => 'Cada imagen es requerida',
            'images.*.file' => 'El archivo debe ser un archivo válido',
            'images.*.image' => 'El archivo debe ser una imagen',
            'images.*.mimes' => 'La imagen debe ser de tipo: jpeg, jpg, png o webp',
            'images.*.max' => 'Cada imagen no debe superar los 2MB',
        ];
    }
}
