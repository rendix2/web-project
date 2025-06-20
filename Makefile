all: dirs-build git php js db

git:
	git config --global --add safe.directory /var/www/html

php: composer.json composer.lock
	composer validate
	composer install

js: package.json package-lock.json
	npm install
	npm run build

db:
	php bin/console database:create
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
	mkdir -p temp/web/mails

	mkdir -p temp/console
	mkdir -p temp/console/cache

	mkdir -p log/web
	mkdir -p log/console

chmod-dirs:
	chmod 777 temp -R

	chmod 777 temp/web/cache
	chmod 777 temp/web/mails

	chmod 777 temp/console/cache

	chmod 777 log -R

	chmod 777 log/web
	chmod 777 log/console

migration:
	php bin/console migrations:generate
	chmod 777 /var/www/html/app/Database/Migrations -R

migrate:
	php bin/console migrations:migrate --no-interaction

update:
	git config --global --add safe.directory /var/www/html
	composer update
	npm update
	npm run build

