.PHONY: dev help up down logs composer-install migrate reset bash-php bash-db test lint

dev: submodule-update up composer-install migrate
	@echo "✅  Environment ready! Open http://localhost/"

submodule-update:
	git submodule sync --recursive
	git submodule update --init --recursive --remote

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

composer-install:
	@if [ ! -d pde-idx-app/vendor ]; then \
		echo "Installing Composer dependencies..."; \
		docker compose exec --user root php php composer install --no-dev --ignore-platform-reqs; \
	else \
		echo "vendor/ already exists – skipping Composer install (run 'make dev' again to force)"; \
	fi

migrate:
	docker compose exec php php yii migrate/up --interactive=0

reset: down
	docker volume rm -f $$(docker volume ls -qf "name=pde-b2b-web-id_db_data") 2>/dev/null || true
	$(MAKE) dev

bash-php:
	docker compose exec php bash

bash-db:
	docker compose exec db bash

test:
	docker compose exec php vendor/bin/codecept run

lint:
	docker compose exec php vendor/bin/php-cs-fixer fix --diff --verbose

help:
	@echo "Available make targets:"
	@echo "  dev          – full setup (submodule, up, composer, migrate)"
	@echo "  up           – start containers (docker compose up -d)"
	@echo "  down         – stop and remove containers"
	@echo "  logs         – follow container logs"
	@echo "  composer-install – install Composer dependencies (if vendor missing)"
	@echo "  migrate      – run Yii migrations"
	@echo "  reset        – remove containers and volume, then re-run dev"
	@echo "  bash-php     – open shell in PHP container"
	@echo "  bash-db      – open shell in DB container"
	@echo "  test         – run Codeception tests"
	@echo "  lint         – run PHP CS fixer"
	@echo "  help         – show this help"