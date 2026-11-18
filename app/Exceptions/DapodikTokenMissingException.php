<?php

namespace App\Exceptions;

use RuntimeException;

class DapodikTokenMissingException extends RuntimeException
{
    public function __construct(string $message = 'Token Dapodik belum dikonfigurasi.')
    {
        parent::__construct($message);
    }
}
