# syntax=docker/dockerfile:1
FROM node:20-alpine

WORKDIR /app

ENV NODE_ENV=development

# copy package files
COPY package*.json ./

# install dependencies
RUN npm install --no-audit --no-fund

# copy source code
COPY . .

EXPOSE 3000
