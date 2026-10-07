<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A short-lived signing link for one signer (embedded iframe or redirect), valid 30 minutes.
 *
 * @property-read string      $object      `signing_session`
 * @property-read string      $signer      `sgr_…`
 * @property-read string      $mode        `iframe` or `redirect`
 * @property-read string      $signing_url
 * @property-read int         $expires_at
 * @property-read string|null $success_url Redirect mode only
 * @property-read string|null $cancel_url  Redirect mode only
 */
class SigningSession extends WthaiqObject
{
    public const OBJECT_NAME = 'signing_session';
}
