<?php
declare(strict_types=1);

namespace Geotab\Models\Security;

use Geotab\Models\Response;

/** * Handles the unique structure of the Authenticate method return.
 * Source: LoginResponse.php
 */
class LoginResponse extends Response {
    public static function build(array $json, ?string $modelClass = null): static {
        $result = $json['result'] ?? throw new \RuntimeException("No login data in response.");
        return new static([$result]);
    }

    public function getSessionId(): string {
        return $this->data[0]['credentials']['sessionId'] 
            ?? $this->data[0]['sessionId'] 
            ?? throw new \RuntimeException("Could not find sessionId in LoginResult.");
    }

    public function getServerPath(): ?string {
        return $this->data[0]['path'] ?? null;
    }
}