<?php

namespace App\Services\Attendance;

use RuntimeException;

class AttendanceException extends RuntimeException
{
    public function __construct(
        protected string $errorCode,
        array $context = [],
    ) {
        parent::__construct($errorCode);
        $this->context = $context;
    }

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
