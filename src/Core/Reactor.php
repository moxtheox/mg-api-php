<?php
declare(strict_types=1);

namespace Geotab\Core;

use Fiber;
use Geotab\Models\Errors\HTTPResponseException;
use RuntimeException;

final class Reactor {
    private static ?self $instance = null;
    private mixed $mh;
    private array $map = [];    // Handle ID -> Fiber
    private array $timers = []; // [['at' => float, 'fiber' => Fiber]]
    private array $handlePool = []; // cURL handle pool
    private array $hostMap = []; // Handle ID -> host string
    private bool $isStopping = false;

    private function __construct() {
        $this->mh = curl_multi_init();

        if (function_exists('pcntl_async_signals')) {
            $msg = "SIGTERM Detected. Shutting down." . PHP_EOL;
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function() use ($msg) {
                echo $msg;
                $this->isStopping = true;
            });
            pcntl_signal(SIGINT, function() use ($msg){
                echo $msg;
                $this->isStopping = true;
            });
        }
    }

    public static function getInstance(): self {
        return self::$instance ??= new self();
    }

    /**
     * Exposes the stopping state publicly so consumers can monitor it in their own loops.
     * Check this in any infinite loop to participate in graceful shutdown:
     *   while (!Reactor::getInstance()->stopping) { ... }
     */
    public bool $stopping {
        get => $this->isStopping;
    }

    /**
     * Entry point: Start a new Fiber under the Reactor's management.
     */
    public function register(callable $task): void {
        $fiber = new Fiber($task);
        $fiber->start();
    }

    /**
     * Non-blocking sleep: yields control until $seconds have passed.
     */
    public function sleep(float $seconds): void {
        $fiber = Fiber::getCurrent() ?? throw new RuntimeException("Must sleep within a Fiber.");
        $this->timers[] = [
            'at' => microtime(true) + $seconds,
            'fiber' => $fiber
        ];
        Fiber::suspend();
    }

    public function await(mixed $ch): string {
        $fiber = Fiber::getCurrent() ?? throw new RuntimeException("Async ops must run in a Fiber.");
        
        curl_multi_add_handle($this->mh, $ch);
        $this->map[(int)$ch] = $fiber;

        $active = 0;
        curl_multi_exec($this->mh, $active);
        
        $url = curl_getinfo($ch)['url'];
        $this->hostMap[(int)$ch] = parse_url($url, PHP_URL_HOST) ?: 'default';
        
        Fiber::suspend();

        try {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code >= 400) {
                throw HTTPResponseException::fromStatusCode($code, $url);
            }
            $content = curl_multi_getcontent($ch);
            return (string)($content ?? '');
        } finally {
            curl_multi_remove_handle($this->mh, $ch);
        }
    }

    public function run(): void {
        $active = 0;
        // Get the initial parent PID when the reactor starts.
        $initialParent = posix_getppid();
        while (!$this->isStopping && ($active > 0 || !empty($this->map) || !empty($this->timers))) {
            
        // CRITICAL: Check if Bun is still alive
        // If the parent changes to 1 (init), the Bun process has died.
        if (posix_getppid() !== $initialParent) {
            file_put_contents('php://stderr', "Orchestrator lost. Shutting down." . PHP_EOL);
            $this->isStopping = true;
            break; 
        }

            curl_multi_exec($this->mh, $active);

            // Resume I/O Fibers
            while ($info = curl_multi_info_read($this->mh)) {
                $id = (int)$info['handle'];
                $fiber = $this->map[$id] ?? null;
                if ($fiber) {
                    unset($this->map[$id]);
                    // We delay the resume slightly to ensure curl_multi_getcontent is ready
                    $fiber->resume();
                }
            }

            // Resume Timer Fibers
            $now = microtime(true);
            foreach ($this->timers as $key => $timer) {
                if ($now >= $timer['at']) {
                    $fiber = $timer['fiber'];
                    unset($this->timers[$key]);
                    $fiber->resume();
                }
            }

            // Calculate next wake-up based on nearest timer
            $timeout = 0.1; // Default 100ms
            if (!empty($this->timers)) {
                $nextTimer = min(array_column($this->timers, 'at'));
                $timeout = max(0, $nextTimer - microtime(true));
            }
            
            if ($active > 0 || !empty($this->timers)) {
                curl_multi_select($this->mh, $timeout);
            }
        }
    }

    /**
     * Executes multiple callables concurrently and suspends the caller 
     * until all are completed.
     * @param array<string, callable> $tasks
     * @return array<string, mixed> The results keyed by the original task keys.
     */
    public function waitAll(array $tasks): array {
        $parentFiber = Fiber::getCurrent() ?? throw new RuntimeException("waitAll must run in a Fiber.");
        $results = [];
        $remaining = count($tasks);

        foreach ($tasks as $key => $task) {
            $this->register(function() use ($task, $key, &$results, &$remaining, $parentFiber) {
                try {
                    $results[$key] = $task();
                } catch (\Throwable $e) {
                    $results[$key] = $e; // Capture exceptions to prevent one failure from killing the set
                } finally {
                    $remaining--;
                    if ($remaining === 0 && $parentFiber->isSuspended()) {
                        $parentFiber->resume($results);
                    }
                }
            });
        }

        // Suspend the main script fiber until the last child finishes
        return Fiber::suspend();
    }

    public function getHandle(string $url): \CurlHandle {
        $host = parse_url($url, PHP_URL_HOST);
        // Reuse a handle for this specific host if available
        if (!empty($this->handlePool[$host])) {
            return array_pop($this->handlePool[$host]);
        }
        return curl_init();
    }

    public function releaseHandle(\CurlHandle $ch): void {
        $host = $this->hostMap[(int)$ch] ?? 'default';
        unset($this->hostMap[(int)$ch]);
        // Reset the handle so it's clean for the next user but keeps the connection
        curl_reset($ch);
        $this->handlePool[$host][] = $ch;
    }
}