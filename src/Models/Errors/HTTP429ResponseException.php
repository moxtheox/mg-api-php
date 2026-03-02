<?php

namespace Geotab\Models\Errors;

class HTTP429ResponseException extends HTTPResponseException {
    public function __construct(string $url, public readonly ?int $retryAfter = null) {
        $retryMsg = $retryAfter ? " Retry after {$retryAfter} seconds." : '';
        parent::__construct(
            code: 429,
            url: $url,
            message: "Rate Limit Exceeded: Too many requests sent to '{$url}'.{$retryMsg}"
        );
    }
}
