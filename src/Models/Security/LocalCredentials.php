<?php
namespace Geotab\Models\Security;

class LocalCredentials {
    public function __construct(
        public readonly SessionCredentials $credentials,
        public readonly string $serverPath = 'my.geotab.com'
    )
    {}
}

?>