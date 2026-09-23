<?php

return [
    'title' => 'Users',
    'help' => 'Accounts are created from the command line, not from this screen. There is no password reset by email because email is not configured.',
    'create_command' => 'php artisan user:create "Name" email@school.example',
    'password_command' => 'php artisan user:password email@school.example',
    'labels' => [
        'name' => 'Name',
        'email' => 'Email',
    ],
];
