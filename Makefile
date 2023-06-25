all: php-build js-build migrations

php-build: composer.json composer.lock
	composer validate
	composer install

js-build: package.json package-lock.json
	npm install
	npm run build

migrations:
	php bin/console migrations:migrate --no-interaction

create-dirs:
	mkdir -p mkdir log
	mkdir -p temp
	mkdir -p temp/web
	mkdir -p temp/web/cache
	mkdir -p temp/console
	mkdir -p temp/console/cache


