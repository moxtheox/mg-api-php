<?php

namespace Geotab\Models;

/** * Standard response for 'Get' calls.
 * @template T; @extends Response<T> 
 */
class EntityResponse extends Response {
    public static function build(array $json, ?string $modelClass = \stdClass::class): static {
        $results = $json['result'] ?? [];
        $toVersion = $json['toVersion'] ?? null;

        // Ensure result is a list
        if (!is_array($results) || (isset($results['id']))) {
            $results = [$results];
        }

        $models = array_map(function($item) use ($modelClass) {
            $itemArray = (array)$item;
            
            // If the model class implements Hydratable, use its factory.
            if ($modelClass && is_subclass_of($modelClass, Hydratable::class)) {
                return $modelClass::fromArray($itemArray);
            }
            
            // Fallback: if it's a specific class but not hydratable, attempt splat 
            // (risky with Geotab's metadata, but works for simple DTOs).
            if ($modelClass && $modelClass !== \stdClass::class) {
                return new $modelClass(...$itemArray);
            }

            return (object)$itemArray;
        }, $results);

        return new static($models, $toVersion);
    }
}
?>