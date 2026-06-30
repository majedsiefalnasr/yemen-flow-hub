# Yemen Flow Hub

Docker-first setup for running the full project locally and preparing separate backend/frontend images for deployment.

## Repository Structure

This repository is a single monorepo. It tracks `docs/`, `backend/`, `frontend/`, Docker configuration, and root project guidance as normal files in the root Git history.

Clone it with:

```bash
git clone git@github.com:majedsiefalnasr/yemen-flow-hub.git
```

No submodule initialization is required. Changes under `backend/` and `frontend/` are committed directly in the root repository.

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

## Build deployable images

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
