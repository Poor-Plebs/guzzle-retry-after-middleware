# poor-plebs/guzzle-retry-after-middleware

[![CI](https://github.com/Poor-Plebs/guzzle-retry-after-middleware/actions/workflows/ci.yml/badge.svg)](https://github.com/Poor-Plebs/guzzle-retry-after-middleware/actions/workflows/ci.yml)

**[What is it for?](#what-is-it-for)** |
**[What are the requirements?](#what-are-the-requirements)** |
**[How to install it?](#how-to-install-it)** |
**[How to use it?](#how-to-use-it)** |
**[How to contribute?](#how-to-contribute)**

A guzzle middleware to keep track of the Retry-After HTTP header and fail
consecutive requests until this time has passed.

## What is it for?

Some HTTP servers may respond with a `Retry-After` [header][1], hinting the
client when to resume doing further requests to the server. The reasons can
differ in many ways, there is no strict case when to use that header. Most often
it is used together with the response status code `429 Too Many Requests` doing
rate limiting or with `503 Service Unavailable` when a dependency is offline or
the server is in maintenance. In both cases the server hints the client when to
retry the request to avoid preassure on the server that can not serve the
request at that point in time. In some cases, if the client will not respect
this back off request, the server may block or ban the client in some form.

To avoid that case, you can use this middleware that is specifically made for
the [guzzle http][2] client. Following the documentation about middlewares, you
can keep track of the retry after header's back off period so that requests
during that period will fail with a dedicated exception before they are event
sent out.

## What are the requirements?

- PHP 8.0 or above

## How to install it?

```bash
composer require poor-plebs/guzzle-retry-after-middleware
```

## How to use it?

```php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterException;

/* Any implementation of the PSR-16 (simple cache) interface. In case of using
 * this package with Laravel, it could look something like:
 */
$cache = Cache::store('array');

$handlerStack = HandlerStack::create();

// Where to put this middleware in the middleware stack depends on the usecase.
$handlerStack->unshift(
    new RetryAfterMiddleware($cache),
    'retry_after', // Name of the middlewere for debugging purposes.
);

$client = new Client([
    'base_uri' => 'https://sometest.com/',
    'handler' => $handlerStack,
    // Can be set/overwritten on per request basis as well.
    RetryAfterMiddleware::REQUEST_OPTION => 'cache_key_to_use',
]);

try {
    $client->postAsync('sendMessage')->wait();
} catch (RetryAfterException $exception) {
    // Do something when the library blocks requests.
}
```

## How to contribute?

`poor-plebs/guzzle-retry-after-middleware` follows semantic versioning. Read
more on [semver.org][3].

Create issues to report problems or requests. Fork and create pull requests to
propose solutions and ideas. Always add a CHANGELOG.md entry in the unreleased
section.

[1]: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Retry-After
[2]: https://github.com/guzzle/guzzle
[3]: https://semver.org
