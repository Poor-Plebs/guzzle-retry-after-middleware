<?php

declare(strict_types=1);

namespace PoorPlebs\GuzzleRetryAfterMiddleware\Tests;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use LogicException;
use PHPUnit\Framework\TestCase;
use PoorPlebs\GuzzleRetryAfterMiddleware\MissingRetryAfterCacheKeyException;
use PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterException;
use PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware;
use Throwable;

/**
 * @coversDefaultClass \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware
 */
class RetryAfterMiddlewareTest extends TestCase
{
    private const CACHE_KEY = 'test_cache_key';

    private const RETRY_AFTER_DATE = 'Wed, 21 Oct 2022 07:28:00 GMT';

    private const RETRY_AFTER_SECONDS = 2;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow(false);
    }

    /**
     * @test
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\MissingRetryAfterCacheKeyException
     */
    public function it_can_pass_the_cache_key_on_the_request(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"ok":true,"result":{}}'),
        ]));
        $handlerStack->unshift(
            new RetryAfterMiddleware(new Repository(new ArrayStore())),
            'retry_after',
        );
        $client = new Client([
            'base_uri' => 'https://sometest.com/',
            'handler' => $handlerStack,
        ]);

        $response = $client->postAsync(
            'sendMessage',
            [
                RetryAfterMiddleware::REQUEST_OPTION => self::CACHE_KEY,
            ],
        )->wait();
        $this->assertSame('{"ok":true,"result":{}}', (string)$response->getBody());
    }

    /**
     * @test
     * @dataProvider retryAfterDateDataProvider
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterException
     */
    public function it_fails_during_retry_after_date_period($mockHttpHandler): void
    {
        $now = (new CarbonImmutable(self::RETRY_AFTER_DATE))->subSeconds(self::RETRY_AFTER_SECONDS);

        CarbonImmutable::setTestNow($now);

        $repository = new Repository(new ArrayStore());

        $handlerStack = HandlerStack::create($mockHttpHandler);
        $handlerStack->unshift(
            new RetryAfterMiddleware($repository),
            'retry_after',
        );

        $client = new Client([
            'base_uri' => 'https://sometest.com/',
            'handler' => $handlerStack,
            RetryAfterMiddleware::REQUEST_OPTION => self::CACHE_KEY,
        ]);

        // Initial request that fails with a client rate limit exception.
        try {
            $client->postAsync('sendMessage')->wait();
        } catch (Throwable $exception) {
            $this->assertSame(ClientException::class, get_class($exception));
            $this->assertSame(429, $exception->getCode());
            $this->assertSame(
                'Client error: `POST https://api.telegram.org/bot**********/sendMessage` resulted in a `429 Too ' .
                'Many Requests` response: {"ok":false,"error_code":429,"description":"Too Many Requests: retry ' .
                'after ' . self::RETRY_AFTER_DATE . '","parameters":{"retry_after":"' . self::RETRY_AFTER_DATE . '"}}',
                $exception->getMessage(),
            );
            $this->assertNull($exception->getPrevious());
        }

        $cachedRetryAfter = $repository->get(self::CACHE_KEY);
        $this->assertNotNull($cachedRetryAfter);
        $this->assertIsString($cachedRetryAfter);

        $cachedRetryAfterObj = new CarbonImmutable($cachedRetryAfter);

        $this->assertSame(self::RETRY_AFTER_SECONDS, $now->diffInSeconds($cachedRetryAfterObj));

        // Second request is stopped at the beginning of retry after period
        try {
            $client->postAsync('sendMessage')->wait();
            throw new LogicException('Should never be reached.');
        } catch (Throwable $exception) {
            $this->assertSame(RetryAfterException::class, get_class($exception));
            $this->assertSame(0, $exception->getCode());
            $this->assertSame(sprintf(
                'Retry after %s. Checked at %s.',
                $now->addSeconds(self::RETRY_AFTER_SECONDS)->toISOString(true),
                $now->toISOString(true),
            ), $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        // Thrird request is stopped at the last microsecond of retry after period.
        $nowAtEndOfRetryAfter = $now->addSeconds(self::RETRY_AFTER_SECONDS);
        CarbonImmutable::setTestNow($nowAtEndOfRetryAfter);

        try {
            $client->postAsync('sendMessage')->wait();
            throw new LogicException('Should never be reached.');
        } catch (Throwable $exception) {
            $this->assertSame(RetryAfterException::class, get_class($exception));
            $this->assertSame(0, $exception->getCode());
            $this->assertSame(sprintf(
                'Retry after %s. Checked at %s.',
                $now->addSeconds(self::RETRY_AFTER_SECONDS)->toISOString(true),
                $nowAtEndOfRetryAfter->toISOString(true),
            ), $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        // Fourth request is sent out one micro second after the retry after period.
        $afterRetryAfter = $now->addSeconds(self::RETRY_AFTER_SECONDS)->addMicrosecond();
        CarbonImmutable::setTestNow($afterRetryAfter);
        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $client->postAsync('sendMessage')->wait();

        $this->assertSame('{"ok":true,"result":{}}', (string)$response->getBody());
    }

    /**
     * @test
     * @dataProvider retryAfterSecondsDataProvider
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterException
     */
    public function it_fails_during_retry_after_seconds_period($mockHttpHandler): void
    {
        $now = new CarbonImmutable();

        CarbonImmutable::setTestNow($now);

        $repository = new Repository(new ArrayStore());

        $handlerStack = HandlerStack::create($mockHttpHandler);
        $handlerStack->unshift(
            new RetryAfterMiddleware($repository),
            'retry_after',
        );

        $client = new Client([
            'base_uri' => 'https://sometest.com/',
            'handler' => $handlerStack,
            RetryAfterMiddleware::REQUEST_OPTION => self::CACHE_KEY,
        ]);

        // Initial request that fails with a client rate limit exception.
        try {
            $client->postAsync('sendMessage')->wait();
        } catch (Throwable $exception) {
            $this->assertSame(ClientException::class, get_class($exception));
            $this->assertSame(429, $exception->getCode());
            $this->assertSame(
                'Client error: `POST https://api.telegram.org/bot**********/sendMessage` resulted in a `429 Too ' .
                'Many Requests` response: {"ok":false,"error_code":429,"description":"Too Many Requests: retry ' .
                'after ' . self::RETRY_AFTER_SECONDS . '","parameters":{"retry_after":' . self::RETRY_AFTER_SECONDS . '}}',
                $exception->getMessage(),
            );
            $this->assertNull($exception->getPrevious());
        }

        $cachedRetryAfter = $repository->get(self::CACHE_KEY);
        $this->assertNotNull($cachedRetryAfter);
        $this->assertIsString($cachedRetryAfter);

        $cachedRetryAfterObj = new CarbonImmutable($cachedRetryAfter);

        $this->assertSame(self::RETRY_AFTER_SECONDS, $now->diffInSeconds($cachedRetryAfterObj));

        // Second request is stopped at the beginning of retry after period
        try {
            $client->postAsync('sendMessage')->wait();
            throw new LogicException('Should never be reached.');
        } catch (Throwable $exception) {
            $this->assertSame(RetryAfterException::class, get_class($exception));
            $this->assertSame(0, $exception->getCode());
            $this->assertSame(sprintf(
                'Retry after %s. Checked at %s.',
                $now->addSeconds(self::RETRY_AFTER_SECONDS)->toISOString(true),
                $now->toISOString(true),
            ), $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        // Thrird request is stopped at the last microsecond of retry after period.
        $nowAtEndOfRetryAfter = $now->addSeconds(self::RETRY_AFTER_SECONDS);
        CarbonImmutable::setTestNow($nowAtEndOfRetryAfter);

        try {
            $client->postAsync('sendMessage')->wait();
            throw new LogicException('Should never be reached.');
        } catch (Throwable $exception) {
            $this->assertSame(RetryAfterException::class, get_class($exception));
            $this->assertSame(0, $exception->getCode());
            $this->assertSame(sprintf(
                'Retry after %s. Checked at %s.',
                $now->addSeconds(self::RETRY_AFTER_SECONDS)->toISOString(true),
                $nowAtEndOfRetryAfter->toISOString(true),
            ), $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        // Fourth request is sent out one micro second after the retry after period.
        $afterRetryAfter = $now->addSeconds(self::RETRY_AFTER_SECONDS)->addMicrosecond();
        CarbonImmutable::setTestNow($afterRetryAfter);
        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $client->postAsync('sendMessage')->wait();

        $this->assertSame('{"ok":true,"result":{}}', (string)$response->getBody());
    }

    /**
     * @test
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware
     */
    public function it_passes_when_cache_key_is_missing(): void
    {
        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"ok":true,"result":{}}'),
        ]));
        $handlerStack->unshift(
            new RetryAfterMiddleware(new Repository(new ArrayStore())),
            'retry_after',
        );
        $client = new Client([
            'base_uri' => 'https://sometest.com/',
            'handler' => $handlerStack,
        ]);

        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $client->postAsync('sendMessage')->wait();

        $this->assertSame('{"ok":true,"result":{}}', (string)$response->getBody());
    }

    /**
     * @test
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\MissingRetryAfterCacheKeyException
     */
    public function it_throws_an_exception_when_cache_key_is_empty_string(): void
    {
        $this->expectException(MissingRetryAfterCacheKeyException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage(
            'Request option ' . RetryAfterMiddleware::REQUEST_OPTION . ' must be a non empty string, empty string given.'
        );

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"ok":true,"result":{}}'),
        ]));
        $handlerStack->unshift(
            new RetryAfterMiddleware(new Repository(new ArrayStore())),
            'retry_after',
        );
        $client = new Client([
            'base_uri' => 'https://sometest.com/',
            'handler' => $handlerStack,
            RetryAfterMiddleware::REQUEST_OPTION => '',
        ]);

        $client->postAsync('sendMessage')->wait();
    }

    /**
     * @test
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\RetryAfterMiddleware
     * @covers \PoorPlebs\GuzzleRetryAfterMiddleware\MissingRetryAfterCacheKeyException
     */
    public function it_throws_an_exception_when_cache_key_is_not_a_string(): void
    {
        $this->expectException(MissingRetryAfterCacheKeyException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage(
            'Request option ' . RetryAfterMiddleware::REQUEST_OPTION . ' must be a non empty string, integer given.'
        );

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"ok":true,"result":{}}'),
        ]));
        $handlerStack->unshift(
            new RetryAfterMiddleware(new Repository(new ArrayStore())),
            'retry_after',
        );
        $client = new Client([
            'base_uri' => 'https://sometest.com/',
            'handler' => $handlerStack,
            RetryAfterMiddleware::REQUEST_OPTION => 123,
        ]);

        $client->postAsync('sendMessage')->wait();
    }

    public function retryAfterDateDataProvider(): array
    {
        return [
            'success_trigger' => [
                new MockHandler([
                    new Response(200, ['Retry-After' => self::RETRY_AFTER_DATE], '{"ok":true,"result":{}}'),
                    new Response(200, [], '{"ok":true,"result":{}}'),
                ]),
            ],
            'failure_trigger' => [
                new MockHandler([
                    new ClientException(
                        'Client error: `POST https://api.telegram.org/bot**********/sendMessage` resulted in a `429 Too Many Requests` response: {"ok":false,"error_code":429,"description":"Too Many Requests: retry after ' . self::RETRY_AFTER_DATE . '","parameters":{"retry_after":"' . self::RETRY_AFTER_DATE . '"}}',
                        new Request('POST', 'sendMessage', [], '{"chat_id": "**********", "text": "Test message"}'),
                        new Response(
                            429,
                            [
                                'Retry-After' => self::RETRY_AFTER_DATE,
                            ],
                            '{"ok":false,"error_code":429,"description":"Too Many Requests: retry after 9","parameters":{"retry_after":"' . self::RETRY_AFTER_DATE . '"}}',
                        ),
                    ),
                    new Response(200, ['Content-Type' => 'application/json'], '{"ok":true,"result":{}}'),
                ]),
            ],
        ];
    }

    public function retryAfterSecondsDataProvider(): array
    {
        return [
            'success_trigger' => [
                new MockHandler([
                    new Response(200, ['Retry-After' => self::RETRY_AFTER_SECONDS], '{"ok":true,"result":{}}'),
                    new Response(200, [], '{"ok":true,"result":{}}'),
                ]),
            ],
            'failure_trigger' => [
                new MockHandler([
                    new ClientException(
                        'Client error: `POST https://api.telegram.org/bot**********/sendMessage` resulted in a `429 Too Many Requests` response: {"ok":false,"error_code":429,"description":"Too Many Requests: retry after ' . self::RETRY_AFTER_SECONDS . '","parameters":{"retry_after":' . self::RETRY_AFTER_SECONDS . '}}',
                        new Request('POST', 'sendMessage', [], '{"chat_id": "**********", "text": "Test message"}'),
                        new Response(
                            429,
                            [
                                'Retry-After' => self::RETRY_AFTER_SECONDS,
                            ],
                            '{"ok":false,"error_code":429,"description":"Too Many Requests: retry after ' . self::RETRY_AFTER_SECONDS . '","parameters":{"retry_after":' . self::RETRY_AFTER_SECONDS . '}}',
                        ),
                    ),
                    new Response(200, ['Content-Type' => 'application/json'], '{"ok":true,"result":{}}'),
                ]),
            ],
        ];
    }
}
