<?php

namespace App\Exceptions;

class InvalidExpenseSplitException extends ApiException
{
    public function __construct(string $message = 'The expense split is invalid.', mixed $errors = null)
    {
        parent::__construct($message, 422, $errors);
    }
}
