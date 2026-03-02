<?php

namespace Geotab\Models\Errors;

class HTTP503ResponseException extends HTTPResponseException {
    public function __construct(string $url) {
        parent::__construct(
            code: 503,
            url: $url,
            message: "Service Unavailable: The Geotab server at '{$url}' is temporarily unavailable. This may indicate a regional outage or scheduled maintenance."
        );
    }
}
