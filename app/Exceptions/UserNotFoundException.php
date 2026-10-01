<?php

namespace App\Exceptions;

class UserNotFoundException extends ApiException
{
    public function __construct(string $message = 'User not found.', mixed $errors = null)
    {
        parent::__construct($message, 404, $errors);
    }
}
