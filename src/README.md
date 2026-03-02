# mg-api-php SDK

A non-blocking, async-capable PHP SDK for the MyGeotab and Geotab Ace APIs.

Built on native PHP 8.5 Fibers and cURL multi — no external dependencies, no framework requirements, no extensions beyond what ships with PHP. Drop it into any PHP project and make concurrent, non-blocking API calls with a familiar pattern.

---

## Requirements

- PHP 8.5 (confirmed) / PHP 8.4 (likely compatible, untested)
- Extensions: `curl`, `pcntl`
- Composer (for autoloading)

---

## Installation

```bash
composer install
```

---

## Quick Start

### Authentication

```php
use Geotab\Client;
use Geotab\Models\Security\FileSessionProvider;

Client::create('your_database', function(Client $sdk) {
    $sdk->setSessionProvider(new FileSessionProvider('/path/to/sessions'));
    $sdk->authenticate();

    // Your code here
});
```

`Client::create` boots the Reactor event loop and yields the client into a Fiber context. All SDK calls must originate from within this callback.

`FileSessionProvider` caches the authenticated session to disk. Subsequent runs reuse the cached token — no re-authentication until the session expires. On session expiry the SDK detects the invalid session error, clears the cache, and re-authenticates transparently up to two times before propagating the exception.

### Making API Calls

```php
$result = $sdk->call('Get', [
    'typeName'     => 'Device',
    'resultsLimit' => 10
]);

echo $result->count;
```

`call()` maps directly to the MyGeotab JSON-RPC API surface. Any method available in the API is accessible here — `Get`, `Add`, `Set`, `Remove`, `GetFeed`, and any other valid method name. The pattern is intentionally close to `mg-api-js`: if you know the API, you know how to call it.

### Concurrent Requests — waitAll

```php
$data = Reactor::getInstance()->waitAll([
    'users'   => fn() => $sdk->call('Get', ['typeName' => 'User',   'resultsLimit' => 5]),
    'devices' => fn() => $sdk->call('Get', ['typeName' => 'Device', 'resultsLimit' => 5]),
    'groups'  => fn() => $sdk->call('Get', ['typeName' => 'Group',  'resultsLimit' => 5]),
]);

foreach ($data as $key => $result) {
    if ($result instanceof \Throwable) {
        echo "Error in {$key}: " . $result->getMessage() . PHP_EOL;
        continue;
    }
    echo "Count for {$key}: " . $result->count . PHP_EOL;
}
```

`waitAll` fires all tasks concurrently and suspends the calling Fiber until every task completes. Results are returned keyed by the original task key. Failures are captured as `Throwable` instances in the results array — one task failing does not affect the others.

### Streaming Data — FeedObserver

```php
use Geotab\Services\FeedObserver;
use Geotab\Models\Errors\HTTP503ResponseException;

$observer = new FeedObserver($sdk, 'LogRecord', fromVersion: '0');

pcntl_signal(SIGUSR1, fn() => $observer->suspend());
pcntl_signal(SIGUSR2, fn() => $observer->resume());

try {
    $observer->start(function(array $records) {
        foreach ($records as $record) {
            echo json_encode($record) . PHP_EOL;
        }
    }, resultsLimit: 1000);
} catch (HTTP503ResponseException $e) {
    echo "Feed terminated: {$e->url}" . PHP_EOL;
    echo "Resume from version: {$observer->fromVersion}" . PHP_EOL;
}
```

`FeedObserver` runs a long-polling loop against the `GetFeed` API endpoint. It manages the version token internally, applies adaptive throttling based on payload size, and participates in graceful shutdown via the Reactor's `$stopping` flag.

The observer can be suspended and resumed externally via `suspend()` and `resume()` — useful for backpressure management when a downstream consumer needs to signal the feed to pause without terminating the process.

`fromVersion` is preserved on `HTTP503ResponseException` so a consumer can restart the feed from exactly where it left off after a backoff period.

### Geotab Ace — AceChat

```php
use Geotab\Services\AceChat;
use Geotab\Models\Errors\AceException;

$ace = new AceChat($sdk);

try {
    $ace->ask(
        prompt: 'How many vehicles exceeded the speed limit today?',
        onMessage: function($message) {
            match($message->type) {
                'AssistantMessage'  => print("[Ace] " . $message->content . "\n\n"),
                'UserDataReference' => print("[Data] " . $message->reasoning . "\n\n"),
                default             => null
            };
        }
    );
} catch (AceException $e) {
    echo "Ace Error [{$e->code}]: " . $e->getMessage() . PHP_EOL;
}
```

`AceChat` provides natural language access to Geotab Ace from PHP. Ask a question, receive a response — everything else is abstracted. Chat creation, prompt delivery, message group polling, and status management are handled internally.

Calling `ask()` again on the same `$ace` instance continues the same conversation thread. The `chatId` is preserved between calls.

---

## Error Handling

The SDK uses a typed exception hierarchy that separates SDK-level failures from PHP-level failures and Geotab API errors from HTTP transport errors.

```
\Exception
└── SDKError                        # Base class for all SDK exceptions
    └── GeotabError                 # Geotab API error response
        ├── GeotabAuthException     # Authentication and session errors
        └── AceException            # Geotab Ace specific errors
    └── HTTPResponseException       # HTTP transport errors
        ├── HTTP404ResponseException
        ├── HTTP429ResponseException
        ├── HTTP500ResponseException
        └── HTTP503ResponseException
```

Errors are constructed via factory methods — `GeotabError::fromResponse()` and `HTTPResponseException::fromStatusCode()` — which inspect the response and return the most specific subtype available. Callers can catch at any level of the hierarchy depending on how granular their handling needs to be.

`GeotabAuthException` distinguishes between an expired session (`Invalid session @` in the error message) and invalid credentials. Expired sessions are retryable — the SDK clears the session cache, re-authenticates, and retries the original call up to two times automatically. Invalid credentials are not retryable and propagate immediately.

`HTTP503ResponseException` is the designated signal for Geotab service unavailability. `FeedObserver` catches it specifically to preserve the `fromVersion` token and allow a clean restart after a backoff period.

---

## Architecture — For Those Who Want to Know How It Works

### The Reactor

The `Reactor` is a singleton event loop built on three native PHP primitives: Fibers, cURL multi, and `pcntl` signals. It has no external dependencies.

```
Reactor::run()
├── curl_multi_exec()        — drives all in-flight HTTP requests
├── curl_multi_info_read()   — detects completed requests, resumes waiting Fibers
├── timer resolution         — resumes Fibers whose sleep duration has elapsed
└── curl_multi_select()      — blocks until the next I/O event or timer expiry
```

Each API call follows this path:

1. `Client::call()` obtains a cURL handle from the per-host pool
2. The handle is added to the multi handle and the calling Fiber suspends via `Fiber::suspend()`
3. The Reactor loop detects completion via `curl_multi_info_read()`
4. The Fiber is resumed with the response content
5. The handle is reset and returned to the pool — preserving the underlying TCP connection where possible

No request blocks the process. While one Fiber waits for a response, the Reactor is free to drive other in-flight requests and resume other Fibers.

### waitAll vs ExecuteMulticall

The MyGeotab API supports `ExecuteMulticall` — a single HTTP transaction containing multiple API calls. This SDK deliberately omits it in favor of `waitAll`.

`ExecuteMulticall` is fail-fast: if any call in the batch fails, the entire request fails. `waitAll` fires each call as an independent concurrent request. Individual failures are captured as exceptions in the results array without affecting other calls in the set. For application code where partial results are more useful than all-or-nothing, `waitAll` is the more resilient pattern.

The trade-off is network efficiency: `waitAll` produces one HTTP transaction per task rather than one for the entire set. The async nature of the Reactor means the practical latency difference is small for typical fleet data queries, but it is a real cost worth understanding.

### cURL Handle Pool

The Reactor maintains a per-host pool of reused cURL handles. `curl_reset()` clears the handle configuration between uses without closing the underlying connection. For workloads that make repeated calls to the same host — which describes every Geotab integration — this means persistent TCP connections and meaningful reduction in connection overhead over time.

### Type Safety

Every file in the SDK carries `declare(strict_types=1)`. Method parameters, return types, and class properties are fully typed. Passing the wrong type produces an immediate, specific `TypeError` at the call site rather than a silent coercion that surfaces as corrupted data downstream. This is a deliberate design choice informed by the pain of debugging type coercion failures in loosely typed PHP codebases.

### No External Dependencies

The SDK has no Composer runtime dependencies beyond its own autoloader. No ReactPHP, no Swoole, no amphp, no utility packages of any kind. Every capability is implemented using PHP's native standard library.

This is intentional. Every dependency is a vulnerability surface, a potential breaking change, and a maintenance obligation. PHP 8.5 Fibers provide everything needed for a capable async event loop. There is no engineering reason to reach for a library when the language itself is sufficient.

### Signal Handling

The Reactor installs `SIGTERM` and `SIGINT` handlers on construction using `pcntl_async_signals(true)`. Both signals set the `$stopping` flag, which all long-running loops check at their cycle boundary. This guarantees that `Ctrl+C` or a Docker stop signal produces a clean exit rather than an abrupt termination mid-request.

The Reactor also monitors its parent process via `posix_getppid()`. If the parent process changes — indicating the orchestrating process has died — the Reactor sets `$stopping` and exits. This prevents orphaned PHP processes when the parent Bun server terminates unexpectedly.

---

## AceChat — Implementation Notes

`AceChat` wraps the `GetAceResults` API endpoint with a stateful polling model:

1. **Lazy chat creation** — no API call is made until the first `ask()`. The `chatId` is null until then.
2. **Prompt delivery** — `send-prompt` returns a `message_group_id` used to track the response.
3. **Polling** — `get-message-group` is polled on an 8-second interval until status is `DONE` or `FAILED`. A `seen` map prevents duplicate message delivery across polls.
4. **Verbosity** — `COTMessage` types (chain-of-thought) are filtered by default. Pass `verbose: true` to the constructor to receive them.
5. **Conversation continuation** — calling `ask()` again on the same instance reuses the existing `chatId`, continuing the same Ace conversation thread.

Note: the 8-second poll interval is a demo value chosen to make async behavior observable. A production implementation should treat this as a configurable parameter.

---

*mg-api-php — Geotab Vibe Coding Competition 2026 — moxtheox*