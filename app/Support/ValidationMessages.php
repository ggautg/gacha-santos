<?php

namespace App\Support;

/**
 * Mensajes de validación en español para las peticiones de la interfaz.
 */
final class ValidationMessages
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'string' => 'Este campo debe ser texto.',
            'integer' => 'Este campo debe ser un número entero.',
            'array' => 'Este campo debe ser una lista.',
            'in' => 'El valor seleccionado no es válido.',
            'min.numeric' => 'El valor debe ser al menos :min.',
            'min.array' => 'Debe incluir al menos :min elementos.',
            'max.array' => 'No puede incluir más de :max elementos.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'count' => 'número de oraciones',
            'team' => 'equipo',
            'team.*' => 'santo del equipo',
            'room' => 'sala',
            'action' => 'acción',
        ];
    }
}
