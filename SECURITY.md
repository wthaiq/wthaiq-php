# Security Policy

## Reporting a vulnerability

Please report security issues privately by email to **support@wthaiq.com** with the subject
"Security: wthaiq-php". Do not open a public GitHub issue, pull request or discussion for a vulnerability.

Include a description of the issue, the affected version, steps to reproduce and, if possible, a proof of
concept. We acknowledge reports within three business days and keep you informed until the issue is fixed.

## Supported versions

| Version | Supported |
|---|---|
| 1.x | Yes |

## Handling secrets

- Keep secret keys (`sk_…`) and webhook secrets (`whsec_…`) on your server, for example in environment
  variables. Never commit them or send them to a browser.
- Rotate a key immediately from the developer portal at <https://developers.wthaiq.com> if it may have leaked.
- Always verify webhook signatures with `Wthaiq\Webhook::constructEvent()` before trusting a payload.
