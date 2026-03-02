# mg-api-php — Bun/Elysia Server

A lightweight Bun/Elysia server that spawns a PHP feed observer as a child process, pipes its stdout through a WebSocket, and serves the visualization frontend as static files.

---

## Stack

- [Bun](https://bun.sh) runtime
- [Elysia](https://elysiajs.com) web framework
- `@elysiajs/static` for frontend asset serving
- PHP 8.5 CLI (bundled in the Docker image)

---

## Structure

```
server/
├── server.ts        # Elysia app — WebSocket endpoint and static plugin
├── public/
│   └── index.html   # Visualization frontend
├── package.json
└── bun.lock
```

The PHP source lives in `src/` at the project root. The server spawns `indexFeed.php` as a child process on each WebSocket connection.

---

## WebSocket Endpoint

**`/geotab-feed`**

On connection open, the server spawns a PHP child process:

```typescript
const proc = Bun.spawn(["php", "./indexFeed.php"], {
    stdout: "pipe",
    stderr: "inherit",
});
```

PHP stdout is piped through a `TextDecoderStream`, line-buffered, and forwarded directly to the connected WebSocket client. Each newline-delimited JSON string from the PHP feed observer becomes one WebSocket message.

---

## Backpressure Signal Chain

The client sends plaintext control messages over the WebSocket:

| Client → Server | Server → PHP |
|---|---|
| `SUSPEND` | `SIGUSR1` |
| `RESUME` | `SIGUSR2` |

The server's `message` handler delivers these as POSIX signals to the PHP child process via `proc.kill()`. The PHP feed observer installs signal handlers for both and suspends or resumes its polling loop at the next cycle boundary.

On WebSocket close, the server sends `SIGINT` to the PHP process, triggering a graceful shutdown of the Reactor event loop.

---

## Running Locally

```bash
bun install
bun server.ts
```

PHP 8.5 CLI must be available on `PATH`. The server listens on port `3000`.

---

## Note

This server is a demo component. It is not hardened for production use. The architecture — single PHP process per WebSocket connection, direct signal delivery, no authentication — is intentional for clarity and simplicity in a competition context.

---

*mg-api-php — Geotab Vibe Coding Competition 2026 — moxtheox*