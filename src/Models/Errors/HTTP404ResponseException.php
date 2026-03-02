<?php

namespace Geotab\Models\Errors;

class HTTP404ResponseException extends HTTPResponseException {
    public function __construct(string $url) {
        parent::__construct(
            code: 404,
            url: $url,
            message: "Not Found: The requested resource could not be located at '{$url}'. Verify the endpoint and database name are correct."
        );
    }
}
