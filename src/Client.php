<?php
declare(strict_types=1);

namespace Geotab;

use Geotab\Core\Reactor;
use Geotab\Models\Response;
use Geotab\Models\EntityResponse;
use Geotab\Models\Security\{
    Credentials, 
    AuthenticationCredentials, 
    SessionCredentials, 
    LoginResponse, 
    FileSessionProvider
};
use Geotab\Models\Errors\{GeotabError, GeotabAuthException};
use RuntimeException;

class Client {
    private ?string $userName;
    private ?Credentials $credentials = null;
    private string $currentServer = 'my.geotab.com';
    private ?FileSessionProvider $sessionProvider = null;

    /**
     * The principal's entry point.
     * Starts the Reactor and yields the client instance into a Fiber context.
     */
    public static function create(string $database, callable $script): void {
        $client = new self($database);
        $reactor = Reactor::getInstance();

        $reactor->register(function() use ($client, $script) {
            $script($client);
        });

        $reactor->run();
    }

    private function __construct(private readonly string $database) {
        $this->userName = ($env = getenv('GEOTAB_USERNAME')) !== false ? $env : null;
    }

    /**
     * Configures a local filesystem session provider for persistence.
     */
    public function setSessionProvider(FileSessionProvider $provider): void {
        $this->sessionProvider = $provider;
    }

    /**
     * Resumes a session from cache or performs a fresh authentication.
     */
    public function authenticate(?string $password = null, ?string $userName = null): void {
        $resolvedUser = $userName ?? $this->userName;

        // 1. Try to load from local storage first
        if ($this->sessionProvider) {
            $cached = $this->sessionProvider->load($this->database, $resolvedUser);
            if ($cached) {
                $this->credentials = $cached->credentials;
                if ($cached->serverPath) {
                    $this->currentServer = $cached->serverPath;
                }
                return; // Early exit: Session resumed
            }
        }

        $resolvedPassword = $password ?? getenv('GEOTAB_PASSWORD') ?: null;

        if (!$resolvedUser || !$resolvedPassword) {
            throw new RuntimeException("Missing credentials for authentication.");
        }

        $auth = new AuthenticationCredentials($this->database, $resolvedUser, $resolvedPassword);

        /** @var LoginResponse $res */
        $res = $this->call(
            method: 'Authenticate',
            params: $auth->toArray(),
            responseClass: LoginResponse::class,
            isAuthCall: true
        );

        $path = $res->getServerPath();
        $this->currentServer = ($path && strtolower($path) !== 'thisserver') ? $path : $this->currentServer;

        $this->credentials = new SessionCredentials(
            database: $this->database,
            userName: $resolvedUser,
            sessionId: $res->getSessionId(),
            serverPath: $this->currentServer
        );

        // 2. Persist the new session
        $this->sessionProvider?->save($this->credentials, $this->currentServer);
    }

    public function call(
        string $method, 
        array $params, 
        string $responseClass = EntityResponse::class,
        ?string $modelClass = null,
        bool $isAuthCall = false
    ): Response {
        $url = $this->geotabApiURL;
        $reactor = Reactor::getInstance();

        $ch = $reactor->getHandle($url);

        $rpcParams = $params;

        if (!$isAuthCall) {
            if (!$this->credentials) {
                throw new RuntimeException("Client not authenticated.");
            }
            $rpcParams['credentials'] = $this->credentials->toArray();
        }

        $body = json_encode(['method' => $method, 'params' => $rpcParams]);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30, 
        ]);

        try {
            $data = $reactor->await($ch);
            
            $json = json_decode($data, true);

            return $this->processResponse($json, $responseClass, $modelClass);

        } catch (GeotabAuthException $e){
            if($e->isRetryable){
                $e->retryCount++;
                $this->sessionProvider?->clear($this->database, $this->userName);
                $this->credentials = null;
                $this->authenticate();
                return $this->call($method, $params, $responseClass, $modelClass, $isAuthCall);
            } else {
                throw $e;
            }
        
        } catch (\Throwable $e) {
            // Log the specific context before re-throwing
            // As an architect, you might want to wrap this in a custom GeotabSdkException
            throw $e; 
        } finally {
            // CRITICAL: The handle is returned to the pool regardless of success or failure.
            // This prevents "handle leaks" that would eventually exhaust your file descriptors.
            $reactor->releaseHandle($ch);
        }
    }

    private function processResponse(array $json, string $responseClass, ?string $modelClass): Response {
        if (isset($json['error'])) {
            throw GeotabError::fromResponse($json);
        }
        return $responseClass::build($json, $modelClass);
    }

    public function wait(float $seconds): void {
        Reactor::getInstance()->sleep($seconds);
    }

    public string $geotabApiURL {
        get=> "https://{$this->currentServer}/apiv1";
    }
}