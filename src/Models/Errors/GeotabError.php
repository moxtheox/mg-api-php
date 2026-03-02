<?php

namespace Geotab\Models\Errors;
use Geotab\Models\Errors\SDKError;
use RuntimeException;

class GeotabError extends SDKError{
    private const UNKNOWN_ERROR = 'unknown';
    public string $errorType;
    public function __construct($jsonResponse)
    {    
        if(isset($jsonResponse['error'])){
            $this->errorType = GeotabError::errorTypeFromResponse($jsonResponse['error']);
        } else {
            throw new RuntimeException('$jsonResponse is missing [\'error\']');
        }
    }


    protected static function errorTypeFromResponse(array $jsonResponseError):string {
        return ($jsonResponseError['data']['type']) ?? GeotabError::UNKNOWN_ERROR;
    }

    public static function fromResponse(array $jsonResponse):static {
        $type = GeotabError::errorTypeFromResponse($jsonResponse);
        return match(strtolower($type)) {
            'invaliduserexception'=> new GeotabAuthException($jsonResponse),
            default => new static($jsonResponse)
        };
    }
}

?>