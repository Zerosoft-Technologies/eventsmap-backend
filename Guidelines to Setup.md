----------

# 🚀 OTAP CI/CD Deployment Guide

**Project:** Laravel Backend (EventsMap)  
**CI/CD:** GitHub Actions → Podman Server  
**OTAP Environments:** `test`, `stage`, `prod`

----------

## 📌 Overview

This guide explains the **complete OTAP (Test → Stage → Production)** CI/CD pipeline for the Laravel backend.  
The workflow automatically:

1.  Detects the pushed branch (`test`, `stage`, `master`)
    
2.  Builds the Docker image
    
3.  Pushes it to GHCR
    
4.  SSH deploys to the correct server folder
    
5.  Pulls + recreates the Podman containers
    
6.  Runs Laravel migrations automatically
    

This doc is designed so **even a junior developer** can follow it.

----------

# 🧱 Project Structure Requirements

Make sure the Laravel backend repo contains:

```
dockerfile
podman-compose.test.yml
podman-compose.stage.yml
podman-compose.prod.yml
```

Example expected server folders:

```
/home/minaxi/eventsmap-backend-test
/home/minaxi/eventsmap-backend-stage
/home/minaxi/eventsmap-backend-prod
```

Each folder will contain:

```
.env
podman-compose.<env>.yml
Containers created by podman
```

----------

# 🔑 Required GitHub Secrets

Create these secrets in:

**GitHub Repo → Settings → Secrets → Actions**

Secret Name

Purpose

`DEPLOY_SSH_PRIVATE_KEY`

SSH private key to connect to server

`SSH_KNOWN_HOSTS`

Server fingerprint

`DEPLOY_USER`

SSH username

`DEPLOY_SERVER`

SSH host/IP

`DEPLOY_PORT`

SSH port

`GHCR_PAT`

GitHub Personal Access Token for container push

`LARAVEL_ENV_FILE_TEST`

Full `.env` content for test

`LARAVEL_ENV_FILE_STAGE`

Full `.env` content for stage

`LARAVEL_ENV_FILE`

Full `.env` content for production

----------

# 🐳 Expected Podman Compose Files

### Example: `podman-compose.stage.yml`

```yaml
services:
  app:
    image: ghcr.io/zerosoft-technologies/eventsmap-backend:stage
    container_name: eventsmap-backend-stage
    env_file: .env
    ports:
      - "8200:8000"
    restart: always
```

(Repeat similarly for test & prod)

----------

# 🔄 CI/CD Pipeline Flow

This workflow triggers on push to:

```
dev → no deploy  
test → deploy to test  
stage → deploy to stage  
master → deploy to production  
```

----------

# 🧪 Branch Mapping (OTAP)

Git Branch

Server Folder

Docker Tag

Compose File

`test`

eventsmap-backend-test

`test`

podman-compose.test.yml

`stage`

eventsmap-backend-stage

`stage`

podman-compose.stage.yml

`master`

eventsmap-backend-prod

`latest`

podman-compose.prod.yml

----------

# ⚙️ Complete GitHub Actions Workflow

Below is the final clean version of your working pipeline:

```yaml
name: Backend CI/CD (OTAP)

on:
  push:
    branches:
      - dev
      - test
      - stage
      - master

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: zerosoft-technologies/eventsmap-backend

jobs:
  build:
    runs-on: ubuntu-latest
    outputs:
      env: ${{ steps.envname.outputs.env }}
    steps:
      - uses: actions/checkout@v4

      - name: Set environment name
        id: envname
        run: |
          BRANCH="${GITHUB_REF##*/}"
          if [[ "$BRANCH" == "dev" ]]; then echo "env=dev" >> $GITHUB_OUTPUT; fi
          if [[ "$BRANCH" == "test" ]]; then echo "env=test" >> $GITHUB_OUTPUT; fi
          if [[ "$BRANCH" == "stage" ]]; then echo "env=stage" >> $GITHUB_OUTPUT; fi
          if [[ "$BRANCH" == "master" ]]; then echo "env=production" >> $GITHUB_OUTPUT; fi

      - name: Set TAG
        id: sett
        run: |
          BRANCH="${GITHUB_REF##*/}"
          if [[ "$BRANCH" == "master" ]]; then echo "tag=latest" >> $GITHUB_OUTPUT; else echo "tag=$BRANCH" >> $GITHUB_OUTPUT; fi

      - name: Install PHP deps (composer)
        uses: php-actions/composer@v6
        with:
          php_version: "8.2"
          command: install --no-dev --optimize-autoloader
          progress: yes
          php_extensions: mbstring pdo pdo_mysql

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GHCR_PAT }}

      - name: Build & push image
        run: |
          IMAGE=${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:${{ steps.sett.outputs.tag }}
          docker build --no-cache -t $IMAGE .
          docker push $IMAGE

  deploy:
    needs: build
    runs-on: ubuntu-latest
    environment: ${{ needs.build.outputs.env }}

    steps:
      - uses: actions/checkout@v4

      - name: Setup SSH
        uses: shimataro/ssh-key-action@v2
        with:
          key: ${{ secrets.DEPLOY_SSH_PRIVATE_KEY }}
          known_hosts: ${{ secrets.SSH_KNOWN_HOSTS }}

      - name: Deploy to server
        run: |
          BRANCH="${GITHUB_REF##*/}"

          if [[ "$BRANCH" == "test" ]]; then 
            TARGET_DIR="eventsmap-backend-test"
            TAG="test"
            COMPOSE="podman-compose.test.yml"
          fi

          if [[ "$BRANCH" == "stage" ]]; then 
            TARGET_DIR="eventsmap-backend-stage"
            TAG="stage"
            COMPOSE="podman-compose.stage.yml"
          fi

          if [[ "$BRANCH" == "master" ]]; then 
            TARGET_DIR="eventsmap-backend-prod"
            TAG="latest"
            COMPOSE="podman-compose.prod.yml"
          fi

          if [[ -z "$TARGET_DIR" ]]; then 
            echo "No deploy for this branch"
            exit 0
          fi

          # Upload compose file
          scp -P ${{ secrets.DEPLOY_PORT }} "$COMPOSE" \
            ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_SERVER }}:/home/minaxi/$TARGET_DIR/

          ssh -p ${{ secrets.DEPLOY_PORT }} ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_SERVER }} "
            echo '${{ secrets.GHCR_PAT }}' | podman login ghcr.io -u ${{ github.actor }} --password-stdin &&
            cd /home/minaxi/$TARGET_DIR &&
            podman pull ghcr.io/${{ env.IMAGE_NAME }}:$TAG &&
            podman-compose -f $COMPOSE up -d --force-recreate &&
            podman exec -i \$(podman ps -a --format '{{.Names}}' | grep eventsmap-backend) php artisan migrate --force
          "
```

----------

# 🧪 How to Test the Pipeline

### 1️⃣ Push to test branch

```
git checkout test
git push
```

→ Deploys to: `/home/<username>/eventsmap-backend-test`

### 2️⃣ Push to stage

```
git checkout stage
git push
```

→ Deploys to: `/home/<username>/eventsmap-backend-stage`

### 3️⃣ Push to master

```
git checkout master
git push
```

→ Deploys to: `/home/<username>/eventsmap-backend-prod`

----------

# 🛠️ Developer Checklist

### **Before pushing code**

☑ Dockerfile exists  
☑ Podman compose file exists for each env  
☑ `.env` uploaded manually to server

### **Before running CI/CD**

☑ SSH works  
☑ Secrets correctly configured  
☑ Git branch correct (`test/stage/master`)

----------

# 🎯 Result

After this full setup, your pipeline becomes **100% automated**:

-   No need to touch `.env` again
    
-   No need SSH manually
    
-   No need to run migrations manually
    
-   No need to restart containers manually
    

**Push = Deploy ✔️**

----------
