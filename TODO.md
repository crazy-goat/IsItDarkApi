# TODO - GitHub Issue #17: Minimal Production Docker Image

## Goal
Create minimal production Docker image using static-php-cli (SPC) with `FROM scratch`.
Target: reduce image size from ~150MB to ~30-50MB.

## Completed ✅

- [x] Task 1: Updated craft.yml with required extensions and SPC-compatible format
- [x] Task 2: Created Dockerfile.scratch (multi-stage build structure)
- [x] Task 3: Created .github/workflows/build-scratch.yml
- [x] Task 4: Updated README.md with scratch image documentation
- [x] Downloaded SPC binary locally (`./spc`)
- [x] Ran SPC doctor (environment checks pass)
- [x] Task 5: Build static PHP binary with SPC
- [x] Task 6: Test final scratch image

## Results 🎉

**Final Image Size: 16.3MB** (89% reduction from original ~150MB)

**Extensions included:** pcntl, json, xml, yaml, curl, openssl, ctype, filter, mbstring, posix, session, sockets, tokenizer, phar

**Test Results:**
- Container starts successfully
- HTTP requests respond correctly
- Application runs as nobody user (UID 65534)

## Build Instructions

1. **Ensure GITHUB_TOKEN is set:**
   ```bash
   export GITHUB_TOKEN=your_github_token_here
   ```

2. **Build static PHP binary:**
   ```bash
   ./spc craft
   ```

3. **Build Docker image:**
   ```bash
   docker build -f Dockerfile.scratch -t isitdark:scratch .
   ```

4. **Test the image:**
   ```bash
   docker run --rm -p 8787:8787 isitdark:scratch
   curl http://localhost:8787/
   ```

## Key Configuration

**Required extensions in craft.yml:**
- pcntl, json, xml, yaml, curl, openssl
- ctype, filter, mbstring, posix
- session, sockets, tokenizer, phar

**Image Features:**
- Uses `FROM scratch` for minimal size
- Concatenates micro.sfx with start.php for self-executing binary
- Runs as unprivileged user (nobody/nogroup)
- No shell or unnecessary files

**Reference:** https://github.com/crazy-goat/symfony-static-build

## Notes

- Original image: ~150MB
- Alpine-based solution: 49.9MB (67% reduction)
- **SPC scratch solution: 16.3MB (89% reduction)** ✅
- SPC prefers pre-built packages: `prefer-pre-built: true` in craft.yml
- Binary downloaded from: https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64
