<?php

declare(strict_types=1);

namespace PoorPlebs\GuzzleRetryAfterMiddleware;

use GuzzleHttp\Exception\RequestException;

class RetryAfterException extends RequestException
{
}
