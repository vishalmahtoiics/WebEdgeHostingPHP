<?php
declare(strict_types=1);

namespace App\Providers;

use RuntimeException;

/**
 * An error from a hosting provider. getMessage() holds the detailed
 * (admin-only) message; customers only ever see publicMessage().
 */
final class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly array $fieldErrors = [],
        public readonly ?string $correlationId = null
    ) {
        parent::__construct($message);
    }

    public function publicMessage(): string
    {
        if ($this->httpStatus === 422 && $this->fieldErrors) {
            // Validation messages describe the customer's own input, so they are safe to show.
            return 'The request was rejected: ' . implode(' ', array_slice(array_merge(...array_values($this->fieldErrors)), 0, 3));
        }
        return 'The hosting service could not complete this request right now. Please try again shortly or contact support.';
    }
}
