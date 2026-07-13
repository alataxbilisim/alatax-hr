<?php

/**
 * Rol → varsayılan veri kapsamı.
 * roles.data_scope doluysa o kazanır; null ise buradaki varsayılan kullanılır.
 * Birden fazla rolde EN GENİŞ kapsam uygulanır.
 */
return [

    'defaults' => [
        'admin' => 'company',
        'hr_manager' => 'company',
        'hr_specialist' => 'department',
        'branch_manager' => 'branch',
        'manager' => 'team',
        'employee' => 'own',
    ],

];
