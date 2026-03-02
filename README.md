# mg-api-php
## A Non-Blocking Async PHP SDK for MyGeotab and Geotab Ace
*by moxtheox*

---

I described PHP to my wife as two major highways sharing a one-lane bridge over a river, using traffic lights to make sure only one car crosses at a time.

She thought about it for a moment and said — *"so you removed the bridge and put in a roundabout?"*

That's exactly what this SDK does.

---

## TL;DR

- **[It Started at Connect](#it-started-at-connect)** — A breakout session, a nightclub, a TypeScript file written like C, and a library shipped before landing. Connect didn't give me a direction. It gave me a fire.
- **[Why PHP](#why-php)** — 72% of the web runs PHP. Nobody has provided those developers an async-capable Geotab SDK. Until now.
- **[The Detour](#the-detour)** — Writing TS as C taught me a language is only as limited as the assumptions you bring to it. That lesson didn't stay in one language.
- **[The Tools](#the-tools)** — Gemini 3.0 scaffolds. Claude synthesizes. One is not better than the other. A screwdriver makes a terrible hammer.
- **[What It Does](#what-it-does)** — Concurrent API calls that feel like Promise.all, a live telemetry feed with backpressure control, and Ace integration that no official SDK has implemented.
- **[The Engineering](#the-engineering)** — No external dependencies, strict types throughout, error handling as infrastructure, and a Reactor that earns its name by living in Core.
- **[A Note on the Shoulders We're Standing On](#a-note-on-the-shoulders-were-standing-on)** — Geotab built something solid enough to build on. That deserves to be said directly.
- **[The Demo](#the-demo)** — Three environment variables. One compose file. Live GPS telemetry falling down a screen. The demo is the proof. The SDK is the work.
- **[Practical Applications](#practical-applications)** — Database hydration, real-time dashboards, natural language fleet reporting, and a Bun server that's there to show off, not because it's required.
- **[What's Next](#whats-next)** — CLI AceChat, configurable timing, broader entity coverage. The foundation is built.

---

## It Started at Connect

3 PM. Boulevard Ballroom 166. Felipe Hoffa, Principal Data and AI Intelligence Advocate at Geotab, and Arron Demmers, Senior Manager of Insights and Integrations Consultancy, had just finished their session — *"From Vibe to Reality: Mastering the Geotab Hackathon."* I walked out thinking anything was possible. The only problem I had was delivering it in about two weeks.

That session did something else too. Watching Felipe demo Claude and Gemini side by side, I immediately spotted a gap in my own toolkit. I knew Gemini well. I had never seen Claude work. That gap needed closing before I could trust either tool with something that mattered. More on that shortly.

Before the SDK existed, the inspiration produced something else entirely. During the IoT Games outside the conference, an idea struck — not a TMS, not an SDK, but a solution to JSFuck's biggest problem: string size. I went back to my hotel room and spent the afternoon before the closing party working through it. The solution came from writing TypeScript the way you would write C: handle the data at the byte level, convert each character value to its hex representation, map to pre-compiled JSFuck strings, append to file rather than concatenate in memory. One byte, one conversion. Max string length becomes immaterial.

That approach — writing TS as C — produced something worth noting. A bit-perfect round trip transcoding of a Git installer from an `.exe`, about 63.1MB, to a `.hjsf` file, approximately 3.2GB in size, and back to a working `.exe`. Windows 11 ran it clean. The installer icon rendered. Every signature intact. No WASM. No extensions. Just a different mental model applied to a familiar tool.

That's hex-jsf. [github.com/moxtheox/hex-jsf](https://github.com/moxtheox/hex-jsf). It went to GitHub before I landed, vibe coded via Google Gemini 3.0 at the closing party at Solara while the room was very much alive around me.

That's the kind of inspiration Connect produced. Everything that follows came from the same fire.

---

## Why PHP

[72% of all websites with a known server-side language run PHP](https://w3techs.com/technologies/overview/programming_language#:~:text=Table_title:%20Usage%20statistics%20of%20server%2Dside%20programming%20languages,one%20server%2Dside%20programming%20language%20%7C%2072.0%25:%20%7C) as of March 2026, according to W3Techs. This is not a legacy footnote or tech debt accumulating. This is solid architecture waiting to be extended. PHP is the dominant server-side language on the web today, by a factor of ten over its nearest competitor.

Fleet management companies, logistics platforms, enterprise portals — a significant portion of the PHP-powered web has Geotab integration opportunities. Until now those developers had no async-capable PHP SDK and instead had to opt for languages and SDKs that supported it. Blocking calls, no native feed streaming, no Ace integration. The gap is real and the installed base is enormous.

Rust and Go were on the suggested ideas list. Both have async out of the box and neither carries PHP's reputation. That reputation is exactly why PHP was the right choice. Rust and Go developers expect async. An async SDK in either language is incremental news. Telling a PHP developer that their language can be genuinely non-blocking is a different conversation entirely. PHP 8.1 introduced native Fibers. Most PHP developers haven't touched them — not because they can't, but because nobody handed them a reason to yet. This SDK is that reason. Fibers are abstracted completely. You get async behavior through clean familiar patterns without ever writing a Fiber yourself.

Thirty years of muscle memory. Thirty years of tutorials teaching blocking patterns. Thirty years of developers learning to work around the bridge instead of replacing it. That is not a technical problem. That is a cultural one. Cultural problems are harder to solve than technical ones.

---

## The Detour

Writing TypeScript as C taught me something that outlasted hex-jsf: a language is only as limited as the assumptions you bring to it. JS and C don't belong in the same sentence — unless you stop asking what the language is known for and start asking what it is actually capable of. That question, once asked, doesn't stay in one language.

I was still thinking a TMS was the competition entry. Geotab has routes, zones, everything you need provided you bring the load data. The space felt crowded looking at what else was being submitted — good work, professional dashboards, solid fleet ops tools. And I had built hex-jsf in a nightclub in one night because I was inspired. A TMS felt like showing up to that with a spreadsheet.

It wasn't until Thursday, February 26, 2026 — about a week out — that I looked at the entry clearly and made the call. Back to the guide. SDK was on the list. One Google search — *does PHP have async operations*. I half remembered reading about Fibers once and needed to verify the assumption before committing. The search confirmed it.

The same question that unlocked hex-jsf unlocked this: what is this language actually capable of when you stop treating it like its reputation?

Off to the races.

---

## The Tools

Felipe's demo planted the seed. I had never seen Claude work before that session. I knew Gemini well. Watching them side by side, I immediately recognized I was an ape who had just been handed a screwdriver and didn't yet know it wasn't a hammer. Before I trusted either tool with serious architectural work, I needed to understand what each one actually was.

I spent 8 hours in a single unbroken conversation with Claude across topics including Roman civil administration, macroeconomics, history, sociology, proximity affinity in modern American society, the application of the basic laws of physics as a model for wider application in nature, and other diverse topics. I was testing how Claude responded to a systemic thinker — someone who questions assumptions including their own, and looks for consistency in pattern, design, and philosophy across domains. A sycophant is useless in that role. I needed a peer, not a yes man.

Then 20 questions — everything from Gravity as an abstract concept to physical objects on my desk. I ran the same tests against Gemini 3.0. I even engineered a multiple choice extension: A through D generated by the model, with E for "None of the above" and F for "All of the above" as static options on every answer, to try to guide the reasoning back on track when it drifted. Gemini 3.0 outperformed Claude Sonnet 4.6 in 20 questions across both yes/no only and multiple choice formats. Claude outperformed Gemini in long context synthesis by a significant margin.

Neither is better than the other. A screwdriver makes a terrible hammer.

Google Gemini 3.0 scaffolded the initial implementation. Claude Sonnet 4.6 handled the long context work where sustained reasoning across a complex codebase was what the job required. The SDK's architecture is mine — the Reactor pattern, the waitAll trade-off, the deliberate omission of a dedicated ExecuteMulticall wrapper, the error hierarchy. Those decisions came from experience and judgment. The LLMs helped me move fast in a language I was relearning against a specification I hadn't fully read yet.

That's what Vibe Coding looks like when it's working.

---

## What It Does

### Concurrent API Calls — waitAll

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

The above code demonstrates how to make three concurrent calls without blocking the main thread. If you have written TypeScript the pattern is immediately familiar — `Promise.all` with PHP's type system underneath it.

The MyGeotab API offers ExecuteMulticall for batching requests into a single network transaction. This SDK does not wrap it as a dedicated method — but it remains fully accessible via `$client->call("ExecuteMulticall", $paramsAsCalls)` for those who need the efficiency of a single network transaction and can handle the fail-fast behavior. The decision was not to make it the default, in favor of the concurrent pattern. Reactor->waitAll is resilient — each call is independent, failures are isolated, partial results are always available. ExecuteMulticall is fail-fast — one failure collapses the entire batch. Both tools exist. The choice is yours.

### Live Data Streaming — FeedObserver

```php
$observer = new FeedObserver($sdk, 'LogRecord', fromVersion: '0');

$observer->start(function(array $records) {
    foreach ($records as $record) {
        echo json_encode($record) . PHP_EOL;
    }
}, resultsLimit: 1000);
```

The FeedObserver service is a long-polling feed observer with adaptive throttling, graceful shutdown, external pause and resume signaling via `suspend()` and `resume()`, and version token preservation across restarts. Suitable for database hydration pipelines, real-time dashboards, exception alerting, or any workload that needs a continuous stream of Geotab telemetry.

### Geotab Ace — AceChat

```php
$ace = new AceChat($sdk);

$ace->ask(
    prompt: 'How many vehicles exceeded the speed limit today?',
    onMessage: function($message) {
        match($message->type) {
            'AssistantMessage'  => print("[Ace] " . $message->content . "\n"),
            'UserDataReference' => print("[Data] " . $message->reasoning . "\n"),
            default             => null
        };
    }
);
```

The word Ace appears three times in the official MyGeotab API documentation. In the words Interface, Surface, and Replace. No official `MyGeotab` SDK provides native Ace integration. This one does. Ask a question, receive a response. Chat creation, prompt delivery, message group polling, and status management are handled internally. Calling `ask()` again on the same instance continues the same conversation thread. That is the entire public interface. Everything else is under the hood where it belongs.

---

## The Engineering

### No External Dependencies

No ReactPHP. No Swoole. No amphp. Not even the infamous isOdd. PHP 8.5 Fibers provide everything needed for a complete async event loop and there is no engineering reason to reach for a library when the language itself is sufficient. Every dependency you don't have is a vulnerability surface that doesn't exist, a breaking change that can't affect you, and a maintenance burden you never carry. Supply chain hygiene as a design principle, not an afterthought.

### Type Safety

Every file carries `declare(strict_types=1)`. Method signatures, return types, class properties — all typed. Pass the wrong type in and you get an immediate `TypeError` at the call site. Not three layers downstream where the coercion has silently corrupted your data and you spend two hours finding out why your feed is producing garbage where a speed value should be. This SDK has been built by someone who has paid that bill personally and had no interest in paying it again.

### Error Handling as Infrastructure

A typed exception hierarchy across three layers — SDK errors separated from PHP errors, Geotab API errors separated from HTTP transport errors, typed subclasses per HTTP status code constructed via factory methods so the caller catches exactly what they need without inspecting message strings.

`GeotabAuthException` knows the difference between a stale session and bad credentials and handles them differently. Stale session retries twice, clears the cache, re-authenticates, continues. Bad credentials fail immediately and cleanly. Because those are not the same problem and pretending they are costs you time at the worst possible moment.

Most hackathon submissions treat error handling as the last thing added before submission. This one treats it as the foundation everything else is built on.

### The Reactor

It lives in `Core`. Because that is exactly what it is.

The Reactor is the big chief of this entire operation — a singleton event loop built on three native PHP primitives: Fibers, cURL multi, and `pcntl` signals. No framework. No extensions beyond what ships with standard PHP. Just the language, pushed to what it is actually capable of when someone stops apologizing for it and starts building with it.

Every API call suspends its Fiber and yields control back to the Reactor. The Reactor drives all in-flight HTTP requests concurrently via `curl_multi_exec`, detects completions, and resumes the appropriate Fiber with the response. While one call waits for a response, every other in-flight request is being driven forward. Nothing blocks. Nothing waits in line. The one-lane bridge is gone.

The cURL handle pool reuses handles per host, preserving TCP connections across repeated calls to the same endpoint. The Reactor monitors its parent process — if the orchestrating process dies, the Reactor detects the change and exits cleanly. No orphaned processes. No ghost workers haunting your container at 2 AM.

The Reactor core. It earns the name.

---

## A Note on the Shoulders We're Standing On

None of this works without what Geotab built underneath it. The API handles the volume — this SDK demo polls a thousand records at a time and the system handles it without complaint. The feed versioning system makes reliable streaming possible. The Ace infrastructure makes natural language fleet queries possible from PHP for the first time. I want to acknowledge the engineers who designed and built these systems directly and without irony. This SDK is proof that the foundation they built is solid.

---

## The Demo

The visualization exists for one reason: to make an abstract engineering claim visible and undeniable in thirty seconds. You cannot show someone a Reactor event loop and have it land. You can show them live GPS telemetry streaming to a screen at low host CPU and they understand immediately. The demo is the proof. The SDK is the work.

`compose.yml`-
```yaml
services:
  api-demo:
    image: cmox/mg-api-php-demo:latest
    env_file: .env
    ports:
      - "3000:3000"
    volumes:
      - geotab_sessions:/usr/src/app/sessions
    restart: unless-stopped

volumes:
  geotab_sessions:
```
`.env`-
```dotenv
GEOTAB_USERNAME=yourusername/email
GEOTAB_PASSWORD=yourpassword
GEOTAB_DATABASE=your_database_name
```
`PowerShell/Bash`-
```bash
docker compose up
```

Open Chrome, or your preferred browser (albeit I only tested in chrome). Every character falling down that screen is a character from a real JSON-encoded GPS log record streaming live from your database. The PHP feed observer is non-blocking. The Bun WebSocket server is non-blocking. Both run in the same container without competing for resources.

Open Chrome's network tab (F12 on Windows distros). Find the WebSocket connection. Watch the frames arrive. Press `SPACE` — the terminal shows `SUSPEND`, the PHP feed observer stops polling at its next cycle boundary. Press `SPACE` again — `RESUME`, the feed restarts. The full integration loop is visible in real time without a single slide.

Pressing `d` on the keyboard will force the display into "Detached" mode.  This will pause the feed while allowing the cache to drain and the display to enter it's failover mode.  The `SPACE` bar "Pause" is respectful of this detached state.

**On the metrics:** Docker's internal CPU display reports usage as a percentage of the container's allocated slice inside the Docker VM — not your host CPU. On Windows press `Ctrl+Shift+Esc` and find `VmmemWSL` in Task Manager. That is the Docker VM. That is where the real host resource story is told. On Linux use `top`. On Mac use Activity Monitor. The container lives inside the VM, not directly on your OS memory stack. What Docker reports and what your machine is actually doing are two different numbers. The one that matters is the one on your host.

---

## Practical Applications

The demo streams a visualization through a Bun WebSocket server. Bun is there to show what the SDK can do across systems and technology boundaries — it is not a requirement. PHP's own server architecture is fully capable of serving this data directly. The SDK is language-boundary agnostic by design.

Database hydration pipelines using FeedObserver as a reliable ETL ingestion engine. Real-time operational dashboards and live exception alerting. Ace-powered natural language reporting from any PHP application without requiring the developer to understand the underlying API surface. Background workers running concurrent API calls without blocking the main application thread. CLI tooling using the same stdin/stdout signal patterns already proven in the feed observer. The architecture is container-native — with a Kubernetes backend it scales horizontally with minimal rework.

---

## What's Next

An interactive CLI AceChat driven by stdin/stdout — the same signal and pipe patterns already established in the feed observer apply directly. Configurable timing parameters throughout — the 8 second AceChat poll interval and the FeedObserver backoff thresholds are demo values chosen to make async behavior observable, not production recommendations. Broader typed model coverage for common Geotab entities.

---

## Requirements

- PHP 8.5 confirmed / PHP 8.4 likely compatible, untested
- Extensions: `curl`, `pcntl`
- Composer

---

Here is an honest accounting: I barely wrote any of this code, making only the most critical changes. What I wrote was context. Specifications. Constraints. The engineering vision to look at a language with a thirty year reputation for blocking everything and say — no, there is a roundabout in here somewhere, and I know how to find it.

The LLM held the tools. I knew what to build and why. That distinction is the whole point of Vibe Coding done well.

*— moxtheox*