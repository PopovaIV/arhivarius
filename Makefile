DC        = docker compose
PHP       = $(DC) exec php
CONSOLE   = $(PHP) bin/console
COMPOSER  = $(DC) run --rm php composer

.DEFAULT_GOAL := help

##@ Запуск

up: ## Поднять все контейнеры
	$(DC) up -d

down: ## Остановить все контейнеры
	$(DC) down

restart: down up ## Перезапустить все контейнеры

build: ## Пересобрать образы
	$(DC) build

build-no-cache: ## Пересобрать образы без кэша
	$(DC) build --no-cache

logs: ## Логи всех контейнеров
	$(DC) logs -f

logs-php: ## Логи PHP
	$(DC) logs -f php

##@ Зависимости

install: ## composer install
	$(COMPOSER) install

update: ## composer update
	$(COMPOSER) update

require: ## Добавить пакет: make require p=vendor/package
	$(COMPOSER) require $(p)

require-dev: ## Добавить dev-пакет: make require-dev p=vendor/package
	$(COMPOSER) require --dev $(p)

##@ База данных

db-migrate: ## Применить миграции
	$(CONSOLE) doctrine:migrations:migrate -n

db-rollback: ## Откатить последнюю миграцию
	$(CONSOLE) doctrine:migrations:migrate prev -n

db-status: ## Статус миграций
	$(CONSOLE) doctrine:migrations:status

db-diff: ## Создать миграцию по diff схемы
	$(CONSOLE) doctrine:migrations:diff

db-generate: ## Создать пустую миграцию
	$(CONSOLE) doctrine:migrations:generate

db-fixtures: ## Загрузить фикстуры
	$(CONSOLE) doctrine:fixtures:load -n

db-reset: ## Дроп + создание БД + миграции
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate -n

##@ Разработка

cc: ## Очистить кэш
	$(CONSOLE) cache:clear

warmup: ## Прогреть кэш
	$(CONSOLE) cache:warmup

routes: ## Список маршрутов
	$(CONSOLE) debug:router

services: ## Список сервисов
	$(CONSOLE) debug:container

make-entity: ## Создать entity: make make-entity
	$(CONSOLE) make:entity

make-controller: ## Создать контроллер
	$(CONSOLE) make:controller

make-migration: ## Создать миграцию по diff
	$(CONSOLE) make:migration

stan: ## PHPStan анализ
	$(PHP) vendor/bin/phpstan analyse

##@ Оболочки

shell: ## Bash внутри PHP-контейнера
	$(DC) exec php sh

shell-db: ## psql в PostgreSQL
	$(DC) exec postgres psql -U genarchive genarchive

shell-redis: ## redis-cli
	$(DC) exec redis redis-cli

##@ Утилиты

ps: ## Статус контейнеров
	$(DC) ps

init: up install db-migrate ## Первый запуск: поднять, установить зависимости, применить миграции

##@ Помощь

help: ## Показать эту справку
	@awk 'BEGIN {FS = ":.*##"; printf "\nИспользование:\n  make \033[36m<цель>\033[0m\n"} \
	/^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 } \
	/^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) }' $(MAKEFILE_LIST)

.PHONY: up down restart build build-no-cache logs logs-php \
        install update require require-dev \
        db-migrate db-rollback db-status db-diff db-generate db-fixtures db-reset \
        cc warmup routes services make-entity make-controller make-migration stan \
        shell shell-db shell-redis ps init help
