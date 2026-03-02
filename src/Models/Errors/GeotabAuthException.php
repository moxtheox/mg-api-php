<?php

namespace Geotab\Models\Errors;

use RuntimeException;

class GeotabAuthException extends GeotabError {
    private int $rtc = 0;
    private const MAX_RETRY = 2;
    private bool $retryAllowed = false;
    public string $errorType;
    function __construct(array $jsonResponse)
    {
        parent::__construct($jsonResponse);
        $msg = $jsonResponse['error']['message'] ?? throw new RuntimeException('Geotab error is missing message');
        $this->retryAllowed = str_contains($msg, "Invalid session @");
    }

    public bool $isRetryable {
        get => ($this->retryAllowed && 
            $this->rtc < GeotabAuthException::MAX_RETRY) ? true : false;
    }

    public int $retryCount {
        get => $this->rtc;
        set => $this->rtc++;
    }
}

?>