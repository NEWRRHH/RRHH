# syntax=docker/dockerfile:1
# Multi-stage image:
#   dev  -> Nuxt dev server (source bind-mounted, hot reload)   [docker-compose.yml]
#   prod -> compiled Nitro server (.output), no sources/devDeps  [docker-compose.prod.yml]

FROM node:20-alpine AS deps
WORKDIR /app
COPY package*.json ./
RUN npm ci --no-audit --no-fund

# --- development ---------------------------------------------------------------
FROM deps AS dev
ENV NODE_ENV=development
COPY . .
EXPOSE 3000

# --- production build ----------------------------------------------------------
FROM deps AS build
ENV NODE_ENV=production
COPY . .
RUN npm run build

# --- production runtime --------------------------------------------------------
FROM node:20-alpine AS prod
WORKDIR /app
ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=3000
COPY --from=build --chown=node:node /app/.output ./.output
USER node
EXPOSE 3000
CMD ["node", ".output/server/index.mjs"]
