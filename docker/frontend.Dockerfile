# Nuxt storefront (SSR). Build context: ./frontend
FROM node:22-alpine AS build
WORKDIR /app
RUN corepack enable
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
# Source maps go to Sentry when the release workflow passes a token (optional).
ARG SENTRY_ORG \
    SENTRY_FRONTEND_PROJECT \
    APP_VERSION=dev
RUN --mount=type=secret,id=sentry_auth_token \
    SENTRY_AUTH_TOKEN="$(cat /run/secrets/sentry_auth_token 2>/dev/null || true)" \
    SENTRY_RELEASE="widmungen@${APP_VERSION}" \
    pnpm build

FROM node:22-alpine
WORKDIR /app
ARG APP_VERSION=dev
ENV NODE_ENV=production \
    NUXT_PUBLIC_SENTRY_RELEASE=widmungen@${APP_VERSION} \
    HOST=0.0.0.0 \
    PORT=3000
COPY --from=build --chown=node:node /app/.output ./.output
USER node
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD wget -qO- http://127.0.0.1:3000/ >/dev/null || exit 1
CMD ["node", ".output/server/index.mjs"]
