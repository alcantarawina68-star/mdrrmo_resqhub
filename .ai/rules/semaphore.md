---
paths:
  - 'app/Support/Semaphore/**'
---

# Semaphore

## Semaphore client is vendored, not composer-installed
kickstartph/semaphore-client pins guzzlehttp/guzzle 6.0.2 exactly, which conflicts with Laravel 13's ^7.9||^8 requirement, so it cannot be `composer require`d. Its MIT source is vendored at app/Support/Semaphore/SemaphoreClient.php (namespace Semaphore\, autoloaded via composer.json PSR-4). SmsService depends on it behind the `services.sms.driver=semaphore` driver; tests mock the SemaphoreClient boundary. The class is NOT verbatim upstream: besides the `timeout` client option and https API_BASE, `sendername` is only added to the POST form when one is configured — per https://semaphore.co/docs omitting it defaults to the account's registered sender name, while passing an unregistered name (upstream hardcoded `SEMAPHORE`) returns "Invalid sender name". So keep the default `$senderName = null`.
