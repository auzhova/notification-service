-include .env
export

DOCKER_COMPOSE = docker-compose

CONTAINER_NAME ?= notification-service
APP_CONTAINER = $(CONTAINER_NAME)-app

APP = docker exec $(APP_CONTAINER)
APP_TTY = docker exec -it $(APP_CONTAINER)

COMPOSER = $(APP) composer
ARTISAN = $(APP) php artisan


.PHONY: help info install build up down restart logs bash migrate seed test queue-work rabbit-setup


info:
	@echo "App: http://localhost:${APP_PORT}"
	@echo "Swagger: http://localhost:${SWAGGER_PORT}"
	@echo "RabbitMQ: http://localhost:${RABBITMQ_MGMT_PORT}"


help:
	@echo ""
	@echo "Available commands:"
	@echo "  make build         - Build containers"
	@echo "  make up            - Start containers"
	@echo "  make down          - Stop containers"
	@echo "  make restart       - Restart containers"
	@echo "  make logs          - Show logs"
	@echo "  make bash          - Enter app container"
	@echo ""
	@echo "  make install       - Full project setup"
	@echo "  make migrate       - Run migrations"
	@echo "  make test          - Run tests"
	@echo ""
	@echo "  make queue-work    - Run worker"
	@echo "  make rabbit-setup  - Setup RabbitMQ queue"
	@echo ""


install: build up composer-install migrate
	@echo "Project is ready. Run worker: make queue-work"

build:
	$(DOCKER_COMPOSE) build


up:
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down

restart: down up

logs:
	$(DOCKER_COMPOSE) logs -f

bash:
	$(APP_TTY) bash


composer-install:
	$(COMPOSER) install

migrate:
	$(ARTISAN) migrate

test:
	$(ARTISAN) test

queue-work:
	$(ARTISAN) queue:work rabbitmq --queue=$(RABBITMQ_QUEUE) --sleep=20 --tries=3 --timeout=90


# =========================================
# RabbitMQ setup (local dev only)
# =========================================
rabbit-setup:
	@echo "Setting up RabbitMQ queue with priority support..."
	@curl -u guest:guest -X PUT http://localhost:15672/api/queues/%2F/notifications \
		-H "Content-Type: application/json" \
		-d '{"durable":true,"arguments":{"x-max-priority":10}}'
	@curl -u guest:guest -X POST http://localhost:15672/api/bindings/%2F/e/laravel_exchange/q/notifications \
		-H "Content-Type: application/json" \
		-d '{"routing_key":"notifications"}'
	@echo "RabbitMQ ready."
