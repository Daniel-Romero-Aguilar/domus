<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'string' => 'El campo :attribute debe ser texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser como mínimo :min.',
    ],
    'max' => [
        'string' => 'El campo :attribute no puede superar :max caracteres.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
    ],
    'unique' => 'El valor de :attribute ya está registrado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'exists' => 'El valor seleccionado para :attribute no es válido.',
    'in' => 'El valor seleccionado para :attribute no es válido.',
    'regex' => 'El formato de :attribute no es válido.',
    'attributes' => [
        'name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña',
        'login' => 'usuario o correo', 'amount' => 'monto',
    ],
];
