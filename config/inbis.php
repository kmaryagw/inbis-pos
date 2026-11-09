<?php

return [

    /*
    | Kata sandi awal untuk akun yang dibuat seeder. Isi di .env (jangan di kode),
    | kosongkan agar seeder membuat kata sandi acak dan menampilkannya sekali di terminal.
    */
    'seed_passwords' => [
        'admin' => env('SEED_ADMIN_PASSWORD'),
        'kasir1' => env('SEED_CASHIER_PASSWORD'),
        'pimpinan' => env('SEED_MANAGER_PASSWORD'),
    ],

];
