composer:
	composer validate
	composer update --no-interaction --prefer-dist

cs:
	vendor/bin/phpcs --standard=./ruleset.xml --cache=${HOME}/phpcs-cache/.phpcs-cache --encoding=utf-8 -sp src tests/KdybyTests
	vendor/bin/parallel-lint -e php,phpt --exclude vendor .

phpstan:
	vendor/bin/phpstan analyse src tests/KdybyTests

tester:
	vendor/bin/tester -s -c ./tests/php.ini-unix ./tests/KdybyTests/
