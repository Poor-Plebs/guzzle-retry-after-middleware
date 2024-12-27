<?php

declare(strict_types=1);

namespace PoorPlebs\GuzzleRetryAfterMiddleware;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

class RetryAfterMiddleware
{
    public const HEADER = 'Retry-After';

    public const REQUEST_OPTION = 'retry_after_cache_key';

    public function __construct(protected readonly CacheInterface $cache)
    {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options = []) use ($handler): PromiseInterface {
            if (!array_key_exists(self::REQUEST_OPTION, $options)) {
                return $handler($request, $options);
            }

            $key = $options[self::REQUEST_OPTION];
            if (!is_string($key) || $key === '') {
                $type = gettype($key);
                throw new MissingRetryAfterCacheKeyException(sprintf(
                    'Request option %s must be a non empty string, %s given.',
                    self::REQUEST_OPTION,
                    $type === 'string' ? 'empty string' : $type,
                ), $request);
            }

            /** @var string|null $retryAfter */
            $retryAfter = $this->cache->get($key);
            if (is_string($retryAfter)) {
                $retryAfter = new CarbonImmutable($retryAfter);

                $now = new CarbonImmutable();
                if ($retryAfter->greaterThanOrEqualTo($now)) {
                    return Create::rejectionFor(new RetryAfterException(
                        $this->retryAfterMessage($retryAfter, $now),
                        $request,
                    ));
                }
            }

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($key): mixed {
                    $this->checkHeader($response, $key);

                    return $response;
                },
                function (Throwable $reason) use ($key): PromiseInterface {
                    if ($reason instanceof BadResponseException) {
                        $this->checkHeader($reason->getResponse(), $key);
                    }

                    return Create::rejectionFor($reason);
                }
            );
        };
    }

    protected function retryAfterMessage(CarbonInterface $until, CarbonInterface $now): string
    {
        return sprintf(
            'Retry after %s. Checked at %s.',
            $until->toISOString(true),
            $now->toISOString(true),
        );
    }

    private function checkHeader(ResponseInterface $response, string $key): void
    {
        $headers = $response->getHeader(self::HEADER);
        $lastValue = end($headers);

        if ($lastValue === false) {
            return;
        }

        $now = new CarbonImmutable();
        if (is_numeric($lastValue)) {
            $retryAfter = $now->addSeconds((int)$lastValue);
        } else {
            $retryAfter = new CarbonImmutable($lastValue);
        }

        $this->cache->set(
            $key,
            $retryAfter->toISOString(true),
            (int)$now->diffInSeconds($retryAfter, true) + 1, // Keep it longer in cache
        );
    }
}
