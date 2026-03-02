<?php

namespace Geotab\Models;

/**
 * Interface for models that handle their own hydration from Geotab's associative arrays.
 */
interface Hydratable {
    public static function fromArray(array $data): static;
}
?>