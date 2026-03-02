<?php

namespace Geotab\Models\Errors;

class HTTP500ResponseException extends HTTPResponseException {
    public function __construct(string $url) {
        parent::__construct(
            code: 500,
            url: $url,
            message: "Internal Server Error: The Geotab server encountered an unexpected error processing the request to '{$url}'. This is likely a transient issue."
        );
    }
}
