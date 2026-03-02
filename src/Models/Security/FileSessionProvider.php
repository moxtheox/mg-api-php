<?php
declare(strict_types=1);

namespace Geotab\Models\Security;

use Geotab\Models\Security\SessionCredentials;
use Geotab\Models\Security\LocalCredentials;

class FileSessionProvider {
    private string $storagePath;

    public function __construct(?string $storagePath = null) {
        // Default to a temp dir if no path is provided
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/geotab_vibe';
        
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
    }

    private function getPath(string $database, string $userName): string {
        $hash = md5($database . $userName);
        return "{$this->storagePath}/sess_{$hash}.json";
    }

    public function save(SessionCredentials $creds, string $serverPath): void {
        file_put_contents(
            $this->getPath($creds->database, $creds->userName),
            json_encode([
                'serverPath'=> $serverPath,
                'credentials'=>$creds->toArray()
            ])
        );
    }

    public function load(string $database, string $userName): ?LocalCredentials {
        $path = $this->getPath($database, $userName);
        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        return new LocalCredentials(
            new SessionCredentials(
            database: $data['credentials']['database'],
            userName: $data['credentials']['userName'],
            sessionId: $data['credentials']['sessionId']
            ), $data['serverPath']
         );
    }

    public function clear(string $database, string $userName):void {
        $path = $this->getPath($database, $userName);
        if(file_exists($path)){
            unlink($path);
        }
    }
}