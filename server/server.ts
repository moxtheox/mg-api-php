import type { Subprocess } from 'bun';
import { Elysia } from 'elysia'
import { staticPlugin } from "@elysiajs/static";

const app = new Elysia()
    .use(staticPlugin({
        assets: './server/public',
        prefix: '/'
    }))

    .ws('/geotab-feed', {

        async open(ws) {
            console.log("WebSocket opened. Starting PHP Feed Observer...");

            // Spawn the PHP process
            const proc = Bun.spawn(["php", "./indexFeed.php"], {
                stdout: "pipe",
                stdin:  "pipe",   // needed to send signals via stdin if desired
                stderr: "inherit",
            });

            const wsData = ws.data as any;
            wsData.proc      = proc;
            wsData.suspended = false;  // backpressure flag

            // Stream processing loop — runs independently of the WS message handler
            (async () => {
                const reader = proc.stdout
                    .pipeThrough(new TextDecoderStream())
                    .getReader();

                let buffer = "";

                try {
                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;

                        // If the client has asked us to suspend, discard incoming
                        // data until they signal RESUME. This keeps the PHP process
                        // alive (it keeps running its feed loop) but stops forwarding
                        // to the browser — effectively acting as a backpressure valve.
                        if ((ws.data as any).suspended) continue;

                        buffer += value;
                        const lines = buffer.split("\n");
                        buffer = lines.pop() || "";

                        for (const line of lines) {
                            if (line.trim()) {
                                ws.send(line);
                            }
                        }
                    }
                } catch (e) {
                    console.error("Stream read error:", e);
                }
            })();
        },

        // Handle backpressure signals from the client
        message(ws, msg) {
            const proc = (ws.data as any).proc as Subprocess;
            const signal = typeof msg === 'string' ? msg.trim() : '';

            if (signal === 'SUSPEND') {
                console.log("Suspending PHP feed via SIGUSR1");
                proc.kill("SIGUSR1");
            } else if (signal === 'RESUME') {
                console.log("Resuming PHP feed via SIGUSR2");
                proc.kill("SIGUSR2");
            }
        },

        close(ws) {
            console.log("WebSocket closed. Killing PHP process...");
            const proc = (ws.data as any).proc as Subprocess;
            proc.kill("SIGINT");
        }
    })
    .listen(3000);