# Static PHP Docker Image Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a minimal production Docker image built FROM scratch containing only a statically compiled PHP binary and application code.

**Architecture:** Multi-stage build using static-php-cli (SPC) to compile PHP statically with required extensions (pcntl, json, xml, yaml, curl, openssl), then copy to scratch image.

**Tech Stack:** static-php-cli, Docker multi-stage builds, GitHub Actions

---

## Project Context

This is a Webman (Workerman) PHP HTTP service that checks if it's dark at any location. Current image uses `php:8.4-cli-alpine` (~150MB+). Target: ~30-50MB with `FROM scratch`.

**Key Requirements:**
- Webman requires `pcntl` extension (process control)
- API needs json, xml, yaml for format support
- curl and openssl for HTTPS
- Non-root user execution
- Health check endpoint

---

## Task 1: Update craft.yml with Required Extensions

**Files:**
- Modify: `craft.yml`

**Step 1: Update craft.yml**

Replace current content with:

```yaml
# Configuration for static-php-cli
# Build minimal PHP binary for IsItDarkApi

php:
  version: "8.4"
  extensions:
    required:
      - ctype
      - curl
      - filter
      - json
      - mbstring
      - openssl
      - pcntl
      - pcre
      - posix
      - session
      - sockets
      - tokenizer
      - xml
      - yaml
    optional:
      - fileinfo
      - iconv
      - intl
      - readline
      - zlib

# Build settings
build:
  os: linux
  arch: x86_64
  
# Output
output:
  name: "php"
  path: "./build/php"
```

**Step 2: Commit**

```bash
git add craft.yml
git commit -m "chore: update craft.yml with all required extensions for static build

Add pcntl (Webman requirement), curl, yaml, and posix extensions.
Update to PHP 8.4 for consistency with current Dockerfile."
```

---

## Task 2: Create Multi-Stage Dockerfile.scratch

**Files:**
- Create: `Dockerfile.scratch`

**Step 1: Create Dockerfile.scratch**

```dockerfile
# Multi-stage build for minimal static PHP image
# Stage 1: Build static PHP binary using static-php-cli
FROM crazyleo233/static-php-cli:latest AS builder

WORKDIR /app

# Copy SPC configuration
COPY craft.yml ./craft.yml

# Build static PHP binary with all extensions
RUN mkdir -p build && \
    php bin/spc build \
    --config=craft.yml \
    --build-dir=build \
    --no-interaction

# Stage 2: Install Composer dependencies
FROM composer:latest AS vendor

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev, optimized autoloader)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --ignore-platform-reqs

# Stage 3: Final minimal image (FROM scratch)
FROM scratch

WORKDIR /app

# Copy static PHP binary from builder
COPY --from=builder /app/build/php /usr/local/bin/php

# Copy application code
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Copy CA certificates for HTTPS (needed by curl/openssl)
COPY --from=builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/

# Create non-root user (using numeric ID for scratch compatibility)
# Note: scratch doesn't have useradd, we'll run as numeric UID

# Create runtime directory
RUN mkdir -p runtime && chmod 777 runtime

# Expose port
EXPOSE 8787

# Health check (using PHP script since no curl/wget in scratch)
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD ["/usr/local/bin/php", "-r", "exit(file_get_contents('http://localhost:8787/') ? 0 : 1);"]

# Run as non-root user
USER 1000:1000

# Start the application
ENTRYPOINT ["/usr/local/bin/php"]
CMD ["start.php", "start"]
```

**Step 2: Commit**

```bash
git add Dockerfile.scratch
git commit -m "feat: add multi-stage Dockerfile for scratch image

Create minimal Docker image using static-php-cli.
Three stages:
1. Builder: Compiles static PHP binary with all extensions
2. Vendor: Installs Composer dependencies
3. Final: FROM scratch with only PHP binary and app code

Includes health check and non-root user execution."
```

---

## Task 3: Create GitHub Actions Workflow for Scratch Build

**Files:**
- Create: `.github/workflows/build-scratch.yml`

**Step 1: Create build-scratch.yml**

```yaml
name: Build and Deploy Scratch Image

on:
  push:
    tags: ['v*']
  workflow_dispatch:

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build-scratch:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          flavor: |
            suffix=-scratch
          tags: |
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=semver,pattern={{major}}
            type=raw,value=latest-scratch

      - name: Build and push Docker image
        uses: docker/build-push-action@v5
        with:
          context: .
          file: ./Dockerfile.scratch
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

      - name: Show image size
        run: |
          docker images ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }} --format "table {{.Repository}}:{{.Tag}}\t{{.Size}}"

      - name: Test image
        run: |
          # Run container in background
          docker run -d --name test-app -p 8787:8787 ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:${{ github.ref_name }}-scratch
          
          # Wait for startup
          sleep 5
          
          # Test health endpoint
          curl -f http://localhost:8787/ || exit 1
          
          # Test API endpoint
          curl -f "http://localhost:8787/api/v1/is-dark?lat=52.2297&lng=21.0122" || exit 1
          
          # Cleanup
          docker stop test-app
          docker rm test-app
```

**Step 2: Commit**

```bash
git add .github/workflows/build-scratch.yml
git commit -m "feat: add CI/CD workflow for scratch image builds

Automated build and deployment of minimal scratch images on tag push.
- Builds multi-arch scratch image using Dockerfile.scratch
- Publishes to GHCR with -scratch suffix
- Includes image size reporting
- Runs smoke tests (health check + API endpoint)"
```

---

## Task 4: Update README.md with New Image Usage

**Files:**
- Modify: `README.md`

**Step 1: Add new section after Docker section**

After line 130 (after the Docker section), insert:

```markdown
### Docker (Minimal - FROM scratch)

For a minimal image (~30-50MB vs ~150MB):

```bash
# Pull pre-built image
docker pull ghcr.io/crazy-goat/isitdarkapi:latest-scratch

# Run container
docker run -p 8787:8787 ghcr.io/crazy-goat/isitdarkapi:latest-scratch
```

**Benefits of scratch image:**
- ~70% smaller (30-50MB vs 150MB+)
- No shell, no package manager - minimal attack surface
- Faster deployments
- Only PHP binary + application code

**Building scratch image locally:**

```bash
# Build
docker build -f Dockerfile.scratch -t isitdarkapi:scratch .

# Run
docker run -p 8787:8787 isitdarkapi:scratch

# Check size
docker images isitdarkapi:scratch --format "{{.Size}}"
```
```

**Step 2: Update Tech Stack section**

Change line 183 from:
```markdown
- **Container**: Docker with static PHP binary
```

to:
```markdown
- **Container**: Docker with static PHP binary (scratch image ~30-50MB)
```

**Step 3: Commit**

```bash
git add README.md
git commit -m "docs: document minimal scratch image usage

Add section on using the minimal FROM scratch image.
Include benefits, usage examples, and build instructions.
Update tech stack description to mention image size."
```

---

## Task 5: Test Build Locally

**Step 1: Build the image**

```bash
docker build -f Dockerfile.scratch -t isitdarkapi:scratch .
```

Expected: Build completes successfully in ~10-15 minutes

**Step 2: Check image size**

```bash
docker images isitdarkapi:scratch --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}"
```

Expected: Size < 50MB

**Step 3: Run and test**

```bash
# Run container
docker run -d --name test-scratch -p 8787:8787 isitdarkapi:scratch

# Wait for startup
sleep 5

# Test health endpoint
curl http://localhost:8787/

# Test API
curl "http://localhost:8787/api/v1/is-dark?lat=52.2297&lng=21.0122"

# Stop and remove
docker stop test-scratch
docker rm test-scratch
```

Expected: Both endpoints return valid responses

**Step 4: Commit (if changes needed)**

```bash
git add .
git commit -m "chore: fix Dockerfile.scratch after local testing"
```

---

## Summary

After completing all tasks:

1. ✅ `craft.yml` updated with all required extensions
2. ✅ `Dockerfile.scratch` creates minimal FROM scratch image
3. ✅ `.github/workflows/build-scratch.yml` automates builds
4. ✅ `README.md` documents new image usage
5. ✅ Local build tested and working

**Next Steps:**
- Push changes: `git push origin feature/static-docker`
- Create PR for review
- After merge, tag release to trigger scratch image build
