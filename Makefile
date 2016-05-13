lint:
	find . -name "*.php" -print0 | xargs -0 -n1 -P 5 php -d display_errors=1 -l

.PHONY: lint
