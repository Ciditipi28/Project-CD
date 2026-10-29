<?php
// XAMPP defaults. Change the password here if your MySQL root account has one.
return [
    'host' => getenv('CE_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('CE_DB_PORT') ?: '3306',
    'name' => getenv('CE_DB_NAME') ?: 'carinderease_family_db',
    'user' => getenv('CE_DB_USER') ?: 'root',
    'password' => getenv('CE_DB_PASSWORD') ?: '',
];
