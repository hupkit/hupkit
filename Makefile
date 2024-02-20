include vendor/rollerscapes/standards/Makefile

phpunit:
	./vendor/bin/phpunit --disallow-test-output --verbose

dist: install cs-full phpstan test
lint: install security-check cs-full phpstan

phpstan:
	php -d memory_limit=1G vendor/bin/phpstan analyse

security-check: ensure
	sh -c "${QA_DOCKER_COMMAND} local-php-security-checker"

.PHONY: security-check
