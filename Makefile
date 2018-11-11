QA_DOCKER_IMAGE=jakzal/phpqa:alpine
QA_DOCKER_COMMAND=docker run -it --rm -v "$(shell pwd):/project" -w /project ${QA_DOCKER_IMAGE}

dist: install cs-full phpstan test-full
lint: install security-check cs-full phpstan

install:
	composer install --no-progress --no-interaction --no-suggest --optimize-autoloader --prefer-dist --ansi

test:
	./vendor/bin/phpunit --disallow-test-output --verbose

# Linting tools
security-check:
	sh -c "${QA_DOCKER_COMMAND} security-checker security:check ./composer.lock"

phpstan:
	sh -c "${QA_DOCKER_COMMAND} phpstan analyse --configuration phpstan.neon --level 5 src bin"

cs:
	sh -c "${QA_DOCKER_COMMAND} php-cs-fixer fix -vvv --diff"

cs-full:
	sh -c "${QA_DOCKER_COMMAND} php-cs-fixer fix -vvv --using-cache=false --diff"

cs-full-check:
	sh -c "${QA_DOCKER_COMMAND} php-cs-fixer fix -vvv --using-cache=false --diff --dry-run"

.PHONY: install test security-check phpstan cs cs-full cs-full-check
