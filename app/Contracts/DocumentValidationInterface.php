<?php

namespace App\Contracts;

interface DocumentValidationInterface
{
    /**
     * Validate a DNI (Documento Nacional de Identidad).
     *
     * @param string $numero DNI number (8 digits)
     * @return array Formatted response with person data
     */
    public function validateDni(string $numero): array;

    /**
     * Validate a RUC (Registro Único de Contribuyentes).
     *
     * @param string $numero RUC number (11 digits)
     * @param bool $advanced Whether to fetch advanced information (if available)
     * @return array Formatted response with company data
     */
    public function validateRuc(string $numero, bool $advanced = false): array;
}
