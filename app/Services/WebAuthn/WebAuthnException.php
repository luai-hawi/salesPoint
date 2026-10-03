<?php

namespace App\Services\WebAuthn;

use RuntimeException;

class WebAuthnException extends RuntimeException
{
    public function __construct(protected string $errorCode)
    {
        parent::__construct($errorCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
