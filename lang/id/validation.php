<?php

/*
| Rules used by the application. Missing keys fall back to English
| (app.fallback_locale); add translations here when new rules are used.
*/

return [
    'after' => ':attribute harus setelah :date.',
    'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi saat ini salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'different' => ':attribute harus berbeda dari :other.',
    'distinct' => ':attribute berisi nilai duplikat.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':attribute maksimal berisi :max item.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],
    'min' => [
        'array' => ':attribute minimal berisi :min item.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',

    'attributes' => [
        'email' => 'email',
        'password' => 'kata sandi',
        'name' => 'nama',
    ],
];
