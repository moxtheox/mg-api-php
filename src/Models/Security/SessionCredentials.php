<?php
declare(strict_types=1);

namespace Geotab\Models\Security;

/**
 * Session-based credentials derived from an authentication call.
 */
readonly class SessionCredentials implements Credentials {
    public function __construct(
        public string $database,
        public string $userName,
        public string $sessionId,
        public ?string $serverPath = null
    ) {}

    /**
     * @return array{database: string, userName: string, sessionId: string}
     */
    public function toArray(): array {
        return [
            'database' => $this->database,
            'userName' => $this->userName,
            'sessionId' => $this->sessionId,
        ];
    }
}