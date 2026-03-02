<?php 

namespace Geotab\Models;

class FeedResponse extends Response {

    static public function build(array $json, ?string $modelClass):static{
        return new static($json['result']['data'], $json['result']['toVersion']);
    }
}

?>