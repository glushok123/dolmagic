include docker.env

PHP_CLI_SERVICE=dolmagic-php-cli
NODE_CLI_SERVICE=dolmagic-node-cli
MARIA_DB_CONTAINER=dolmagic-mariadb
SPHINX_CONTAINER=dolmagic-sphinx

up: docker-up
down: docker-down
restart: docker-down docker-up
build: docker-build
ps: docker-ps

init:
	$(MAKE) artisan-run cmd=""

docker-ps:
	@docker-compose --env-file ./docker.env ps

docker-up:
	@docker-compose --env-file ./docker.env up -d

docker-down:
	@docker-compose --env-file ./docker.env down --remove-orphans

docker-build: \
	docker-build-dolmagic-nginx \
	docker-build-dolmagic-php-fpm \
	docker-build-dolmagic-php-cli

docker-build-dolmagic-nginx:
	@docker build -t dolmagic-nginx:${IMAGE_TAG} ./docker/${ENV}/nginx

docker-build-dolmagic-php-fpm:
	@docker build -t dolmagic-php-fpm:${IMAGE_TAG} ./docker/${ENV}/php-fpm

docker-build-dolmagic-php-cli:
	@docker build -t dolmagic-php-cli:${IMAGE_TAG} ./docker/${ENV}/php-cli

php-cli:
	@docker-compose --env-file ./docker.env run --rm ${PHP_CLI_SERVICE} $(args)

artisan-run:
	$(MAKE) php-cli args="php artisan $(cmd)"

migrate-create:
	$(MAKE) -s artisan-run cmd="make:migration $(name)"
	@$(MAKE) -s chown

migrate-up:
	$(MAKE) artisan-run cmd="migrate"

migrate-down:
	$(MAKE) artisan-run cmd="migrate:rollback"

composer-install:
	$(MAKE) composer run="install $(args)" 

composer-require:
	$(MAKE) composer run="require $(args)" 

composer-remove:
	$(MAKE) composer run="remove $(args)" 

composer:
	@docker-compose --env-file ./docker.env run --rm ${PHP_CLI_SERVICE} composer $(run)

node:
	@docker-compose --env-file ./docker.env run --rm ${NODE_CLI_SERVICE} sh

npm-install:
	$(MAKE) npm run="install"

npm-build:
	$(MAKE) -s npm run="run build"

npm-watch:
	$(MAKE) -s npm run="run watch"

npm:
	@docker-compose --env-file ./docker.env run --rm ${NODE_CLI_SERVICE} npm $(run)

indexer:
	@docker-compose --env-file ./docker.env run --rm ${SPHINX_CONTAINER} indexer --all

indexer-rotate:
	@docker-compose --env-file ./docker.env run --rm ${SPHINX_CONTAINER} indexer --all --rotate

dump-db:
	@docker exec ${MARIA_DB_CONTAINER} /usr/bin/mysqldump -u root -p$(password) ${MYSQL_DATABASE} $(options) | gzip > `date +./docker/dumps/%Y-%m-%d_%H-%M-%S-test-dump.sql.gz`

restore-db-gzip:
	gunzip < ./docker/dumps/$(filename) | docker exec -i ${MARIA_DB_CONTAINER} /usr/bin/mysql -u root -p$(password) ${MYSQL_DATABASE} $(options)

shell:
	$(MAKE) -s php-cli args="/bin/sh"

chown:
	$(MAKE) php-cli args="chown -R 1000:1000 ./"