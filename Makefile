app_name=files_staticmimecontrol

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/var/www/htmlstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=1.2.3

all: appstore

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

clean:
	rm -rf $(build_dir)
	rm -rf node_modules

appstore: clean
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/: \
	--exclude=/build \
	--exclude=/composer.json \
	--exclude=/composer.lock \
	--exclude=/docs \
	--exclude=/translationfiles \
	--exclude=/.tx \
	--exclude=/tests \
	--exclude=/.git \
	--exclude=/screenshots \
	--exclude=/.github \
	--exclude=/l10n/l10n.pl \
	--exclude=/CONTRIBUTING.md \
	--exclude=/issue_template.md \
	--exclude=/README.md \
	--exclude=/.gitattributes \
	--exclude=/.gitignore \
	--exclude=/.scrutinizer.yml \
	--exclude=/.travis.yml \
	--exclude=/.drone.yml \
	--exclude=/.php-cs-fixer.cache \
	--exclude=/.php-cs-fixer.dist.php \
	--exclude=/Makefile \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64; \
	fi

# Install PHP Dependencies via Composer
composer-install:
	docker run --rm --name compose-maintainence --interactive \
    --volume $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST)))):/var/www/html \
    --user $(shell id -u):$(shell id -g) \
    jitesoft/composer:8.1 composer install --no-scripts

# require PHP Dependencies via Composer usage make composer-require module=modulename
composer-require:
	docker run --rm --name compose-maintainence --interactive \
    --volume $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST)))):/var/www/html \
    --user $(shell id -u):$(shell id -g) jitesoft/composer:8.1 composer require $(module) --no-scripts

# remove PHP Dependencies via Composer usage make composer-remove module=modulename
composer-remove:
	docker run --rm --name compose-maintainence --interactive \
    --volume $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST)))):/var/www/html \
    --user $(shell id -u):$(shell id -g) jitesoft/composer:8.1 composer remove $(module)

# check for outdated PHP Dependencies via Composer
composer-outdated:
	docker run --rm --name compose-maintainence --interactive \
    --volume $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST)))):/var/www/html \
    --user $(shell id -u):$(shell id -g) jitesoft/composer:8.1 composer outdated

# check for outdated PHP Dependencies via Composer
composer-update:
	docker run --rm --name compose-maintainence --interactive \
    --volume $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST)))):/var/www/html \
    --user $(shell id -u):$(shell id -g) jitesoft/composer:8.1 composer update --no-scripts

# lint via Composer
composer-lint:
	docker run --rm --name compose-maintainence --interactive \
    --volume $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST)))):/var/www/html \
    --user $(shell id -u):$(shell id -g) jitesoft/composer:8.1 composer run lint

# cs-fix via Composer
composer-cs-fix:
	docker run --rm --name compose-maintainence --interactive \
    --volume $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST)))):/var/www/html \
    --user $(shell id -u):$(shell id -g) jitesoft/composer:8.1 composer run cs:fix

