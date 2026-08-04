<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Şirket bağlamı set edilmeden job/işlem çalıştırıldığında.
 */
class CompanyContextMissingException extends RuntimeException
{
}
