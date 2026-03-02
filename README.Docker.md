# mg-api-php — Demo Image

A live telemetry visualization powered by a non-blocking async PHP SDK for the MyGeotab and Geotab Ace APIs.

This image runs a self-contained demo: a PHP feed observer streams GPS log records from your MyGeotab database through a Bun/Elysia WebSocket server to an interactive browser visualization. Both services run in a single container.

---

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) or Docker Engine with Compose
- A MyGeotab account with API access
- A modern browser

---

## Quick Start

**1. Create a `compose.yml` file:**

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

**2. Create a `.env` file in the same directory:**

```dotenv
GEOTAB_USERNAME=your@email.com
GEOTAB_PASSWORD=yourpassword
GEOTAB_DATABASE=your_database_name
```

**3. Start the container:**

```bash
docker compose up
```

**4. Open your browser:**

```
http://localhost:3000
```

The intro sequence will play, the WebSocket will connect, and live telemetry from your database will begin streaming to the visualization within seconds.

---

## Configuration

| Variable | Description |
|---|---|
| `GEOTAB_USERNAME` | Your MyGeotab login email |
| `GEOTAB_PASSWORD` | Your MyGeotab password |
| `GEOTAB_DATABASE` | Your MyGeotab database name (e.g. `my_company`) |

The demo database used during development was `demo_tsvl_las` — any valid MyGeotab database with LogRecord data will work.

---

## Session Persistence

Authentication credentials are cached in a named Docker volume (`geotab_sessions`). On first run the SDK authenticates against the MyGeotab API and stores the session token. Subsequent runs reuse the cached session — no re-authentication required unless the session expires.

To force re-authentication:

```bash
docker compose down -v
docker compose up
```

The `-v` flag removes the named volume and clears the session cache.

---

## Controls

Once the visualization is running, the following keyboard controls are available:

| Key | Action |
|---|---|
| `SPACE` | Pause / resume the feed and animation |
| `D` | Detached mode — suspends the feed, animation continues through cache |
| `−` | Slow down the animation |
| `=` | Speed up the animation |

---

## Port Conflicts

The demo runs on port `3000` by default. If that port is in use, update the `compose.yml`:

```yaml
ports:
  - "3001:3000"   # change 3001 to any available port
```

Then open `http://localhost:3001` instead.

---

## Stopping the Demo

```bash
docker compose down
```

The container handles `SIGTERM` gracefully — the PHP feed observer will complete its current request cycle before exiting cleanly.

---

## Source Code

The full SDK source, server code, and submission documentation are available at:

**[https://github.com/moxtheox/mg-api-php](https://github.com/moxtheox/mg-api-php)**

---

*mg-api-php — Geotab Vibe Coding Competition 2026 — moxtheox*