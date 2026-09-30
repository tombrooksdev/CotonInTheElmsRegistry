.DEFAULT_GOAL := help

.PHONY: help setup build up restart deploy down stop logs ps shell db-shell admin-hash backup restore clean

help: ## Show this list of commands
	@echo "Coton in the Elms Business Register"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*##' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; {printf "  make %-12s %s\n", $$1, $$2}'

setup: ## First-time setup: create .env from the template (safe to re-run, won't overwrite an existing .env)
	@test -f .env || cp .env.example .env
	@echo "Now edit .env and fill in: DB_PASS, DB_ROOT_PASS, IP_SALT, ADMIN_PASSWORD_HASH (see 'make admin-hash'),"
	@echo "and the MICROSOFT_TENANT_ID / MICROSOFT_CLIENT_ID / MICROSOFT_CLIENT_SECRET / MICROSOFT_SENDER_EMAIL values."
	@echo "Then run: make up"

build: ## Build (or rebuild) the web image - run this after changing PHP/HTML/Dockerfile
	docker compose build

up: ## Start the whole stack (web + db) in the background
	docker compose up -d

restart: build ## Rebuild the web image and recreate just the web container
	docker compose up -d web

deploy: ## Pull latest code and restart (run this ON THE SERVER)
	git pull
	$(MAKE) restart

down: ## Stop the stack (keeps the database volume/data)
	docker compose down

stop: down ## Alias for 'down'

logs: ## Follow the web container's logs
	docker compose logs -f web

ps: ## Show container status
	docker compose ps

shell: ## Open a shell in the web container
	docker compose exec web bash

db-shell: ## Open a MySQL shell in the db container
	docker compose exec db sh -c 'exec mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

admin-hash: ## Generate a bcrypt hash for ADMIN_PASSWORD_HASH (usage: make admin-hash PASS=yourpassword)
	@test -n "$(PASS)" || (echo "Usage: make admin-hash PASS=yourpassword" && exit 1)
	@docker compose run --rm web php -r "echo password_hash('$(PASS)', PASSWORD_DEFAULT), PHP_EOL;"
	@echo "Paste that into .env as ADMIN_PASSWORD_HASH - escape every \$$ in it as \$$\$$, or compose will mangle it."

backup: ## Dump the database to a timestamped .sql file in ./backups/
	@mkdir -p backups
	docker compose exec -T db sh -c 'exec mysqldump --no-tablespaces -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"' > backups/coton-register-$$(date +%Y%m%d-%H%M%S).sql
	@echo "Backup written to backups/"

restore: ## Restore the database from a .sql file - OVERWRITES current data (usage: make restore FILE=backups/xxx.sql)
	@test -n "$(FILE)" || (echo "Usage: make restore FILE=backups/xxx.sql" && exit 1)
	@test -f "$(FILE)" || (echo "File not found: $(FILE)" && exit 1)
	docker compose exec -T db sh -c 'exec mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"' < $(FILE)
	@echo "Restored from $(FILE)"

clean: ## Stop the stack AND permanently delete the database volume (irreversible - all signups are lost)
	docker compose down -v
