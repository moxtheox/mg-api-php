<?php
declare(strict_types=1);

namespace Geotab\Models;

use Geotab\Models\Errors\AceException;

/**
 * Response handler for GetAceResults API calls.
 * Unpacks the Ace-specific apiResult envelope that wraps all Ace responses.
 * Standard EntityResponse cannot be used here as Ace nests results under
 * result.apiResult.results rather than result directly.
 */
class AceResponse extends Response {
    public static function build(array $json, ?string $modelClass = null): static {
        $apiResult = $json['result']['apiResult'] 
            ?? throw new \RuntimeException('AceResponse: missing apiResult in response.');

        // Surface apiResult-level errors before attempting to unpack results
        $errors = $apiResult['errors'] ?? [];
        if (!empty($errors) && isset($errors[0])) {
            throw AceException::fromApiError('', $errors[0]);
        }

        $results = $apiResult['results'] ?? [];

        return new static($results);
    }
}