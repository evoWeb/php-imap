MAKEFLAGS += --warn-undefined-variables
SHELL := /bin/bash
.EXPORT_ALL_VARIABLES:
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c
.SILENT:

# use the rest as arguments for "run"
_ARGS := $(wordlist 2, $(words $(MAKECMDGOALS)), $(MAKECMDGOALS))
# ...and turn them into do-nothing targets
$(eval $(_ARGS):;@:)

##@
##@ Prepare testing and code quality assurance actions
##@

.PHONY: install
install:
	ddev composer install


.PHONY: cleanup
cleanup:
	rm -rf .cache
	rm -rf vendor
	rm -f .php-cs-fixer.cache
	rm -f composer.lock


##@
##@ Code quality actions
##@


.PHONY: php-cs-fixer-check
php-cs-fixer-check: ##@ Check php code style
	ddev composer run php-cs-fixer-check


.PHONY: php-cs-fixer-fix
php-cs-fixer-fix: ##@ Fix php code style
	ddev composer run php-cs-fixer-fix


.PHONY: phpmd
phpmd: ##@ Run php mess detection
	ddev composer run phpmd


.PHONY: composer-require-checker
composer-require-checker: ##@ Run composer require checker
	ddev composer run composer-require-checker


##@
##@ Testing actions
##@


.PHONY: phpstan
phpstan: ##@ Check php with phpstan
	ddev composer run phpstan


.PHONY: phpunit
phpunit: ##@ Run phpunit tests
	ddev composer run phpunit


.PHONY: phpunit-coverage
phpunit-coverage: ##@ Run phpunit tests with code coverage
	ddev composer run phpunit-coverage


.PHONY: static-analysis
static-analysis: ##@ Run all code qualities at once
static-analysis: php-cs-fixer-check
static-analysis: composer-require-checker
static-analysis: phpmd
static-analysis: php-cs-fixer-fix

.PHONY: tests
tests: ##@ Run all tests
tests: phpstan
tests: phpunit

help:
	@printf "\nUsage: make \033[32m<command>\033[0m\n"
	grep -F -h "##@" $(MAKEFILE_LIST) | \
	grep -F -v grep -F | \
	grep -F -v awk -F | \
	awk 'BEGIN {FS = ":*[[:space:]]*##@[[:space:]]*"}; \
	{ \
		if ($$2 == "") \
			printf ""; \
		else if ($$0 ~ /^#/) \
			printf "\n%s\n\n", $$2; \
		else if ($$1 == "") \
			printf "     %-30s%s\n", "", $$2; \
		else \
			printf "    \033[32m%-30s\033[0m %s\n", $$1, $$2; \
	}'
.DEFAULT_GOAL := help
