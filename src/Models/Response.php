<?php
declare(strict_types=1);

namespace Geotab\Models;

/** * Base Response class
 * @template T 
 */
abstract class Response implements \IteratorAggregate {
    public function __construct(
        public readonly array $data,
        public readonly ?string $toVersion = null
    ) {}

    /** Allows: foreach ($response as $item) */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->data);
    }

    public int $count {
        get => count($this->data);
    }

    public bool $isEmpty {
    get => empty($this->data);
    }

    abstract public static function build(array $json, ?string $modelClass): static;
}

