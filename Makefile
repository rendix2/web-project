all: dirs-build php js db

php: composer.json composer.lock
	composer validate
	composer install

js: package.json package-lock.json
	npm install
	npm run vite-build
	npm run webpack-build
	chmod 777 www/dist -R

db:
	php bin/console orm:schema-tool:drop --force --full-database
	php bin/console migrations:migrate --no-interaction
	php bin/console doctrine:fixtures:load --no-interaction

dirs-build: create-dirs chmod-dirs

create-dirs:
	rm temp -r -f
	rm vendor -r -f
	rm node_modules -r -f

	mkdir -p log backup temp

	mkdir -p temp/web
	mkdir -p temp/web/cache
	mkdir -p temp/web/proxies
	mkdir -p temp/web/sessions

	mkdir -p temp/console
	mkdir -p temp/console/cache
	mkdir -p temp/console/proxies
	mkdir -p temp/console/sessions

	mkdir -p log/web
	mkdir -p log/console

chmod-dirs:
	chmod 777 temp
	chmod 777 log

	chmod 777 temp/web/cache
	chmod 777 temp/web/proxies
	chmod 777 temp/web/sessions

	chmod 777 temp/console/cache
	chmod 777 temp/console/proxies
	chmod 777 temp/console/sessions

	chmod 777 log/web
	chmod 777 log/console

