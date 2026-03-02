<?php
namespace Geotab\Models;

class Device implements Hydratable {
    // PHP 8.4+ asymmetric visibility
    public function __construct(
        public private(set) string $id,
        public private(set) string $name,
        public private(set) array $groups = []
    ) {}

    public static function fromArray(array $data): static {
        return new self(
            id: (string)($data['id'] ?? ''),
            name: (string)($data['name'] ?? ''),
            groups: (array)($data['groups'] ?? [])
        );
    }
}