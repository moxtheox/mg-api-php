<?php

namespace Geotab\Models\Errors;

class HTTPResponseException extends SDKError {
    public function __construct(public readonly int $code,
        public readonly string $url,
        string $message = ''
    ) {
        parent::__construct($message ?: "HTTP {$code} error for URL: {$url}");
    }

    public static function fromStatusCode(int $code, string $url): static {
    return match($code) {
        404 => new HTTP404ResponseException($url),
        429 => new HTTP429ResponseException($url),
        500 => new HTTP500ResponseException($url),
        503 => new HTTP503ResponseException($url),
        default => new static($code, $url)
        };
    }
}

?>