<?php
declare(strict_types=1);

namespace Geotab\Models\Security;

/**
 * Credentials used for initial authentication.
 * Source: AuthenticationCredentials.php
 */
class AuthenticationCredentials implements Credentials {
    public function __construct(
        public private(set) string $database,
        public private(set) string $userName,
        public private(set) string $password
    ) {}

    public function toArray(): array {
        return [
            'database' => $this->database,
            'userName' => $this->userName,
            'password' => $this->password,
        ];
    }
}