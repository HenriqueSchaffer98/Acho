SAIL = ./vendor/bin/sail

# Passa user/group do host para o container (evita arquivos criados como root)
export WWWUSER ?= $(shell id -u)
export WWWGROUP ?= $(shell id -g)

.PHONY: up down fresh test lint analyze

up: ## Sobe os containers em background (Sail)
	$(SAIL) up -d

down: ## Para os containers
	$(SAIL) down

fresh: ## Reset completo do banco + executa seeders
	$(SAIL) artisan migrate:fresh --seed

test: ## Roda a suíte de testes (Pest)
	$(SAIL) artisan test

lint: ## Pint + Larastan (PHP) e ESLint + Prettier (TS)
	$(SAIL) exec laravel.test ./vendor/bin/pint
	$(SAIL) exec laravel.test ./vendor/bin/phpstan analyse
	npx eslint .
	npx prettier --check .

analyze: ## Apenas análise estática Larastan nível 8
	$(SAIL) exec laravel.test ./vendor/bin/phpstan analyse
