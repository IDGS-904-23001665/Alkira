
FROM node:22-alpine AS compilacion

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

FROM caddy:2-alpine

WORKDIR /app

COPY Caddyfile /etc/caddy/Caddyfile
COPY --from=compilacion /app/dist /app/dist

CMD ["caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
