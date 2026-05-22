# Yemen Flow Hub

Docker-first setup for running the full project locally and preparing separate backend/frontend images for deployment.

## Repository Structure

This monorepo tracks all code: `docs/`, `backend/`, and `frontend/`. The `backend/` and `frontend/` directories are also maintained as independent git repositories for each team:

| Repo | Remote |
| ---- | ------ |
| Root (monorepo) | `git@github.com:majedsiefalnasr/yemen-flow-hub.git` |
| Backend team repo | `git@github.com:ultimate-eg/yemen-flow-hub-backend.git` |
| Frontend team repo | `git@github.com:ultimate-eg/yemen-flow-hub-frontend.git` |

The `backend/` and `frontend/` directories are git submodules pointing to their respective team repos. When cloning this repo, initialize them with:

```bash
git clone --recurse-submodules git@github.com:majedsiefalnasr/yemen-flow-hub.git
# or after a plain clone:
git submodule update --init
```

Every change to `backend/` or `frontend/` must be committed to **both** the team repo and this root monorepo (see `AGENTS.md` for the full dual-commit workflow).

## Run the full project with Docker

1. Start everything:

```bash
docker compose up -d --build
```

2. Run first-time backend setup:

```bash
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan l5-swagger:generate
```

3. Open:
- Frontend: http://localhost:3000
- Backend API: http://localhost:8000
- Swagger: http://localhost:8000/api/documentation

4. Stop:

```bash
docker compose down
```

To also remove MySQL data volume:

```bash
docker compose down -v
```

## Build deployable images (separate backend/frontend)

Build backend image:

```bash
docker build -t yemen-flow-hub-backend:latest ./backend
```

Build frontend image:

```bash
docker build -t yemen-flow-hub-frontend:latest ./frontend
```

These two images can be pushed to your registry and deployed independently.

## Useful commands

View logs:

```bash
docker compose logs -f backend frontend
```

Re-run backend migrations:

```bash
docker compose exec backend php artisan migrate --force
```
