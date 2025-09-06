<?php

namespace BassamShoukry\LaravelChatwoot\Exceptions;

use Exception;

class ChatwootApiException extends Exception
{
    protected array $responseData;
    protected int $httpCode;

    public function __construct(
        string $message = 'Chatwoot API error',
        int $code = 500,
        int $httpCode = 500,
        array $responseData = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->responseData = $responseData;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
