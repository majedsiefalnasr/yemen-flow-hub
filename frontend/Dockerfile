FROM node:22-alpine AS build

WORKDIR /app
COPY package*.json ./
COPY scripts/ ./scripts/
RUN npm install

COPY . .
RUN npm run build

FROM node:22-alpine AS runtime

WORKDIR /app
ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=3000
ENV NUXT_HOST=0.0.0.0
ENV NUXT_PORT=3000

COPY --from=build /app/.output ./.output

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD wget -qO- http://127.0.0.1:3000 >/dev/null || exit 1

CMD ["node", ".output/server/index.mjs"]
