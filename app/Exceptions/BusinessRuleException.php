<?php

namespace App\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis; pesannya aman ditampilkan ke pengguna. */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
