BUILD_ID ?= 20
COMPOSER_BIN ?= $(shell which composer)
ESBUILD_TARGET_DIRECTORY ?= public/build
PHP_BIN ?= $(shell which php)
SHELL_PWD := $(shell pwd)

# -----------------------------------------------------------------------------
# Real targets
# -----------------------------------------------------------------------------

tools/php-cs-fixer/vendor/bin/php-cs-fixer:
	$(MAKE) -C tools/php-cs-fixer vendor

tools/psalm/vendor/bin/psalm:
	$(MAKE) -C tools/psalm vendor

vendor: composer.lock
	${PHP_BIN} ${COMPOSER_BIN} install --no-interaction --prefer-dist --optimize-autoloader;
	touch vendor;

# -----------------------------------------------------------------------------
# Phony targets
# -----------------------------------------------------------------------------

.PHONY: fmt
fmt: php-cs-fixer

.PHONY: php-cs-fixer
php-cs-fixer: tools/php-cs-fixer/vendor/bin/php-cs-fixer
	./tools/php-cs-fixer/vendor/bin/php-cs-fixer --allow-risky=yes fix

.PHONY: phpunit
phpunit: vendor
	./vendor/bin/phpunit

.PHONY: psalm
psalm: tools/psalm/vendor/bin/psalm
	./tools/psalm/vendor/bin/psalm \
		--no-cache \
		--show-info=true \
		--root=$(CURDIR)

.PHONY: psalm.watch
psalm.watch: node_modules vendor
	./node_modules/.bin/nodemon \
		--ext ini,php \
		--signal SIGTERM \
		--watch ./src \
		--exec '$(MAKE) psalm || exit 1'
