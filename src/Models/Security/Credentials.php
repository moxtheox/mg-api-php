<?php
declare(strict_types=1);

namespace Geotab\Models\Security;

/**
 * Interface for MyGeotab authentication credentials.
 * Source: Credentials.php
 */
interface Credentials {
    public function toArray(): array;
}