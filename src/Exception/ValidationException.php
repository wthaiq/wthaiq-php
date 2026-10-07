<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 400: a parameter is missing or invalid. {@see getParam()} names the field.
 */
class ValidationException extends InvalidRequestException
{
    /**
     * Field errors keyed by parameter path, e.g. `['signers[0].email' => '...']`.
     * The API reports one field per error, so this holds at most one entry.
     *
     * @return array<string, string>
     */
    public function getFieldErrors(): array
    {
        $param = $this->getParam();

        return $param === null ? [] : [$param => $this->getMessage()];
    }
}
