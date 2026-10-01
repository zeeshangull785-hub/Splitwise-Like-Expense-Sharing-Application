<?php

namespace App\Exceptions;

class UnauthorizedGroupAccessException extends ApiException
{
    public function __construct(string $message = 'You are not authorized to access or perform operations on this group.', mixed $errors = null)
    {
        parent::__construct($message, 403, $errors);
    }
}
