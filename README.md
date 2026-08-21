# 🦈 Shark Tank — Dive In If You Dare

**[Play it live → sharkgame.ashiraf.cc](https://sharkgame.ashiraf.cc)**

A fast, atmospheric survival game built with vanilla JS and HTML5 Canvas. Dive through 24 increasingly deadly levels of the deep ocean, dodge sharks that get smarter and faster as you descend, collect pearls before your oxygen runs out, and see how deep you can go before you're consumed.

![Status](https://img.shields.io/badge/status-live-brightgreen)
![Made with](https://img.shields.io/badge/made%20with-JavaScript-yellow)
![License](https://img.shields.io/badge/license-MIT-blue)

## Gameplay

- **24 handcrafted levels** — from *The Shallows* to *Final Frontier*, each with its own shark count, speed, color palette, and shark AI type (patrol, chase, pack, swarm, hunter, phantom, quantum, chaos, and more)
- **Oxygen management** — every level drains faster and starts you with less air than the last
- **Pearl collection** — gather enough pearls per level to progress
- **Progressive difficulty** — shark behavior evolves from simple patrols to coordinated hunting packs
- **Progress saving** — your run is saved locally so you can pick up where you left off
- **Leaderboard & analytics** — optional PHP/MySQL backend tracks scores, sessions, and online players

## Controls

`WASD` / Arrow Keys or Mouse — move · Dodge sharks · Collect pearls

## Tech stack

| Layer | Tech |
|---|---|
| Game engine | Vanilla JavaScript + HTML5 Canvas (single-file, no build step) |
| Backend API | PHP (`api/index.php`) |
| Database | MySQL (`setup.sql` — players, scores, sessions, analytics, daily stats) |
| Hosting | Static frontend on a custom domain via `CNAME`, API on cPanel |

## Project structure

```
shark-game/
├── index.html      # the entire game — canvas, engine, UI, levels
├── api/
│   ├── index.php    # score/session/leaderboard/analytics endpoints
│   └── config.php    # DB connection config (reads from env vars)
├── setup.sql        # MySQL schema + leaderboard/online-count views
├── favicon.svg
├── robots.txt
├── sitemap.xml
└── CNAME            # sharkgame.ashiraf.cc
```

## Running it locally

The game itself is a single static file — no build tools needed:

```bash
git clone https://github.com/Ashi145/shark-game.git
cd shark-game
python3 -m http.server 8000
# open http://localhost:8000
```

That's enough to play. The leaderboard/analytics features need the PHP API and a MySQL database:

1. Create a MySQL database and import `setup.sql`
2. Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` as environment variables for the PHP process (see `api/config.php`)
3. Serve `api/` with PHP (e.g. `php -S localhost:8001 -t api`)

## Deployment

The site deploys as a static site (`CNAME` points it at `sharkgame.ashiraf.cc`) with the PHP API hosted separately on cPanel.

## License

MIT — do whatever you like with it, credit is appreciated.
