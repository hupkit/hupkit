QA_DOCKER_IMAGE=jakzal/phpqa:1.76-php8.1-alpine
QA_DOCKER_COMMAND=docker run --init -t --rm --user "$(shell id -u):$(shell id -g)" --volume /tmp/tmp-phpqa-$(shell id -u):/tmp --volume "$(shell pwd):/project" --workdir /project ${QA_DOCKER_IMAGE}

dist: install cs-full phpstan test-full
lint: install security-check cs-full phpstan

install:
	composer install --no-progress --no-interaction --no-suggest --optimize-autoloader --prefer-dist --ansi

test:
	./vendor/bin/phpunit --disallow-test-output --verbose

# Linting tools
security-check: ensure
	sh -c "${QA_DOCKER_COMMAND} local-php-security-checker"

phpstan:
	php -d memory_limit=1G vendor/bin/phpstan analyse

cs: ensure
	sh -c "${QA_DOCKER_COMMAND} php-cs-fixer fix -vvv --diff"

cs-full: ensure
	sh -c "${QA_DOCKER_COMMAND} php-cs-fixer fix -vvv --using-cache=no --diff"

cs-full-check: ensure
	sh -c "${QA_DOCKER_COMMAND} php-cs-fixer fix -vvv --using-cache=no --diff --dry-run"

ensure:
	mkdir -p ${HOME}/.composer /tmp/tmp-phpqa-$(shell id -u)
	docker pull jakzal/phpqa:1.76-php8.1-alpine

.PHONY: install test security-check phpstan cs cs-full cs-full-check
