all: dirs-build git php js db

# --- production safety --------------------------------------------------
# /.docker-production-build only exists inside the image built by
# .docker/web/prod.Dockerfile (`RUN touch /.docker-production-build`, baked in at build time -
# unlike an ENV var, a `docker run -e` / compose `environment:` cannot undo it). Any target that
# can delete/recreate the schema, run migrations or wipe vendor/node_modules/temp depends on this
# guard, so it refuses to run inside a production container no matter how it is invoked.
guard-not-production:
	@if [ -f /.docker-production-build ]; then \
		echo "Refusing to run: this is the production image (/.docker-production-build exists)."; \
		echo "Migrations, fixtures, and schema drop/recreate must never run this way in production."; \
		exit 1; \
	fi

git:
	git config --global --add safe.directory /var/www/html

php: composer.json composer.lock
	#php bin/console nette:cache:purge
	composer validate
	composer install

js: package.json package-lock.json
	npm install
	npm run build

db: guard-not-production
	php bin/console database:create
	php bin/console orm:schema-tool:drop --force --full-database
	php bin/console nette:cache:purge
	php bin/console migrations:migrate --no-interaction
	php bin/console doctrine:fixtures:load --no-interaction

dirs-build: create-dirs chmod-dirs

create-dirs: guard-not-production
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

cached:
	rm -rf temp/web/cache
	mkdir -p temp/web/cache
	chmod 777 temp/web/cache -R

	rm -rf temp/console/cache
	mkdir -p temp/console/cache
	chmod 777 temp/console/cache -R

migration:
	php bin/console migrations:generate
	chmod 777 /var/www/html/app/Database/Migrations -R

migrate: guard-not-production
	php bin/console migrations:migrate --no-interaction

update:
	git config --global --add safe.directory /var/www/html
	composer update
	npm update
	npm run build

lint:
	php vendor/bin/latte-lint app
	php vendor/bin/neon-lint config
	find app -name "*.php" -print0 | xargs -0 -n1 -P4 php -l > /dev/null
	composer validate

phpstan: dirs-build git php
	php vendor/bin/phpstan analyse -vvv


# --- tests (Codeception; Functional/Acceptance need the running web container, Acceptance also selenium-chrome) ---
test-build:
	php vendor/bin/codecept clean
	php vendor/bin/codecept build

unit: test-build
	php vendor/bin/codecept run --steps -v Unit

functional: test-build
	php vendor/bin/codecept run --steps -v Functional

acceptance: test-build
	php vendor/bin/codecept run --steps -v Acceptance

test: test-build
	php vendor/bin/codecept run --steps -v
