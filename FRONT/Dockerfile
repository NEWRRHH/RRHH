# syntax=docker/dockerfile:1

# --- builder: installs deps + builds site ---
FROM node:20-alpine AS builder
WORKDIR /app
ENV NODE_ENV=development

# copy lockfile/package first for better caching
COPY package*.json ./

# install dependencies (use npm install to avoid lockfile mismatch issues in dev)
RUN npm install --no-audit --no-fund

# copy source and build
COPY . .
RUN npm run build


# --- runner: production image that runs the Nuxt server ---
FROM node:20-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production

# non-root user
RUN addgroup -S app && adduser -S app -G app

# copy only what runtime needs from the builder
COPY --from=builder /app/.output /app/.output
COPY --from=builder /app/node_modules /app/node_modules
COPY --from=builder /app/package.json /app/package.json

EXPOSE 3000
USER app

# start Nuxt production server
CMD ["node", ".output/server/index.mjs"]
