<?php

declare(strict_types=1);

namespace PoorPlebs\GuzzleRetryAfterMiddleware;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

use function GuzzleHttp\Promise\rejection_for;

class RetryAfterMiddleware
{
    public const HEADER = 'Retry-After';

    public const REQUEST_OPTION = 'retry_after_cache_key';

    public function __construct(protected CacheInterface $cache)
    {
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options = []) use ($handler): PromiseInterface {
            if (!isset($options[self::REQUEST_OPTION])) {
                throw new MissingRetryAfterCacheKeyException(sprintf(
                    'Required qequest option %s has not been provided.',
                    self::REQUEST_OPTION,
                ), $request);
            }

            $key = $options[self::REQUEST_OPTION];
            if (!is_string($key)) {
                throw new MissingRetryAfterCacheKeyException(sprintf(
                    'Request option %s must be of type string, %s given.',
                    self::REQUEST_OPTION,
                    gettype($key),
                ), $request);
            }

            /** @var string|null $retryAfter */
            $retryAfter = $this->cache->get($key);
            if (is_string($retryAfter)) {
                $retryAfter = new CarbonImmutable($retryAfter);

                $now = new CarbonImmutable();
                if ($retryAfter->greaterThanOrEqualTo($now)) {
                    return rejection_for(new RetryAfterException(
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
                        $response = $reason->getResponse();

                        if ($response instanceof ResponseInterface) {
                            $this->checkHeader($response, $key);
                        }
                    }

                    return rejection_for($reason);
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
            $now->diffInSeconds($retryAfter, true) + 1, // Keep it longer in cache
        );
    }
}
