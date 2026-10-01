<?php

namespace App\Exceptions;

class InvalidSettlementException extends ApiException
{
    public function __construct(string $message = 'The settlement is invalid.', mixed $errors = null)
    {
        parent::__construct($message, 422, $errors);
    }
}
