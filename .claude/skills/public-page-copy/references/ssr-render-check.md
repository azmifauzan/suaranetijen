# Check rendered copy locally

Use this to count words or check links in the HTML a visitor gets, not in the .vue source.

1. Confirm Postgres is up: `pg_isready -h 127.0.0.1`. SSR rendering reads the database, so a dead Postgres makes the page fail or render thin.
2. Build the bundle: `timeout 300 npm run build`. Read the tail of its output and check the exit code.
3. Start the SSR server and the app in the background, logging to the session scratch dir: `php artisan inertia:start-ssr` and `php artisan serve --port=8123`. Save each PID with `$!`.
4. Fetch the page: `curl -s -b x=1 localhost:8123/category/atlet`. The session sent a cookie with `-b`; its effect on the public page cache is not confirmed, so check the response shape before trusting it.
5. Count words inside `<main>`:
   `curl -s -b x=1 localhost:8123/category/atlet | python3 -c 'import sys,re;h=sys.stdin.read();m=re.search(r"(?s)<main.*?</main>",h);print(len(re.sub(r"<[^>]+>"," ",m.group(0)).split()) if m else "nomain")'`
   Check robots with `grep -o 'name="robots" content="[^"]*"'`.
6. Stop the processes by saved PID only. Do not `pkill -f` a pattern from the same command line that launched them.
