<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateInvoiceMismatchException extends RuntimeException
{
    public const ERROR_CODE = 'DUPLICATE_INVOICE_MISMATCH';

    public function __construct(
        string $message = 'Invoice key fields do not match existing requests for this tax number and invoice number.',
        public readonly ?string $mismatchedField = null,
    ) {
        parent::__construct($message);
    }
}
