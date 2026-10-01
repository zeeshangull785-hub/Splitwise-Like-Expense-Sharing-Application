<?php

namespace App\Exceptions;

class GroupNotFoundException extends ApiException
{
    public function __construct(string $message = 'Group not found.', mixed $errors = null)
    {
        parent::__construct($message, 404, $errors);
    }
}
