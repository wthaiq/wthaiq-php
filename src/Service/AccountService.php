<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\RequestOptions;
use Wthaiq\Resource\Account;

final class AccountService extends AbstractService
{
    /**
     * The account, plan and pricing summary for the API key (`GET /`).
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(array|RequestOptions|null $options = null): Account
    {
        return $this->requestObject(Account::class, 'GET', '/', [], $options);
    }
}
