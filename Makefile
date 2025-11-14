# DB Wipe Module Development Makefile
# Run 'make help' for available commands

.PHONY: help install clean test phpcs phpcbf phpstan analyze fix all ci release

# Colors for output
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

# Variables
COMPOSER := composer
PHPCS := vendor/bin/phpcs
PHPCBF := vendor/bin/phpcbf
PHPSTAN := vendor/bin/phpstan
PHPUNIT := vendor/bin/phpunit
MODULE_NAME := db_wipe
VERSION ?= $(shell grep "version:" db_wipe.info.yml | cut -d "'" -f 2)

# Default target
.DEFAULT_GOAL := help

## Help
help:
	@echo "$(GREEN)DB Wipe Module - Development Commands$(NC)"
	@echo ""
	@echo "$(YELLOW)Setup & Installation:$(NC)"
	@echo "  make install        - Install composer dependencies"
	@echo "  make clean          - Clean up generated files and dependencies"
	@echo ""
	@echo "$(YELLOW)Code Quality:$(NC)"
	@echo "  make phpcs          - Run PHP CodeSniffer"
	@echo "  make phpcbf         - Fix coding standards automatically"
	@echo "  make phpstan        - Run PHPStan static analysis"
	@echo "  make analyze        - Run all static analysis tools"
	@echo "  make fix            - Fix all auto-fixable issues"
	@echo ""
	@echo "$(YELLOW)Testing:$(NC)"
	@echo "  make test           - Run all tests"
	@echo "  make test-unit      - Run unit tests only"
	@echo "  make test-kernel    - Run kernel tests only"
	@echo "  make test-functional- Run functional tests only"
	@echo ""
	@echo "$(YELLOW)Development:$(NC)"
	@echo "  make watch          - Watch for changes and run checks"
	@echo "  make validate       - Validate composer.json"
	@echo "  make security       - Check for security vulnerabilities"
	@echo ""
	@echo "$(YELLOW)CI/CD:$(NC)"
	@echo "  make ci             - Run full CI pipeline locally"
	@echo "  make release        - Create release package"
	@echo ""
	@echo "$(YELLOW)Drupal Integration:$(NC)"
	@echo "  make drupal-install - Install module in Drupal"
	@echo "  make drupal-enable  - Enable all module components"
	@echo "  make drupal-disable - Disable all module components"
	@echo "  make drupal-uninstall - Uninstall module from Drupal"

## Install composer dependencies
install:
	@echo "$(GREEN)Installing composer dependencies...$(NC)"
	$(COMPOSER) install --prefer-dist --no-progress
	@echo "$(GREEN)✓ Dependencies installed$(NC)"

## Clean up
clean:
	@echo "$(YELLOW)Cleaning up...$(NC)"
	rm -rf vendor/
	rm -rf .phpcs-cache
	rm -rf .phpstan/
	rm -f composer.lock
	@echo "$(GREEN)✓ Cleanup complete$(NC)"

## Validate composer.json
validate:
	@echo "$(GREEN)Validating composer.json...$(NC)"
	$(COMPOSER) validate --strict
	@echo "$(GREEN)✓ Composer validation passed$(NC)"

## Run PHP CodeSniffer
phpcs:
	@echo "$(GREEN)Running PHP CodeSniffer...$(NC)"
	$(PHPCS) --standard=phpcs.xml.dist
	@echo "$(GREEN)✓ Coding standards check complete$(NC)"

## Fix coding standards automatically
phpcbf:
	@echo "$(GREEN)Fixing coding standards...$(NC)"
	$(PHPCBF) --standard=phpcs.xml.dist || true
	@echo "$(GREEN)✓ Automatic fixes applied$(NC)"

## Run PHPStan
phpstan:
	@echo "$(GREEN)Running PHPStan analysis...$(NC)"
	$(PHPSTAN) analyze --memory-limit=256M
	@echo "$(GREEN)✓ Static analysis complete$(NC)"

## Run PHPStan with baseline
phpstan-baseline:
	@echo "$(GREEN)Generating PHPStan baseline...$(NC)"
	$(PHPSTAN) analyze --generate-baseline --memory-limit=256M
	@echo "$(GREEN)✓ Baseline generated$(NC)"

## Run all analysis tools
analyze: validate phpcs phpstan
	@echo "$(GREEN)✓ All analysis complete$(NC)"

## Fix all auto-fixable issues
fix: phpcbf
	@echo "$(GREEN)Organizing imports...$(NC)"
	@# Add any other auto-fix tools here
	@echo "$(GREEN)✓ All fixes applied$(NC)"

## Run all tests
test:
	@echo "$(GREEN)Running all tests...$(NC)"
	@if [ -f phpunit.xml ] || [ -f phpunit.xml.dist ]; then \
		$(PHPUNIT); \
	else \
		echo "$(YELLOW)No PHPUnit configuration found. Skipping tests.$(NC)"; \
	fi
	@echo "$(GREEN)✓ All tests complete$(NC)"

## Run unit tests only
test-unit:
	@echo "$(GREEN)Running unit tests...$(NC)"
	$(PHPUNIT) --testsuite=unit
	@echo "$(GREEN)✓ Unit tests complete$(NC)"

## Run kernel tests only
test-kernel:
	@echo "$(GREEN)Running kernel tests...$(NC)"
	$(PHPUNIT) --testsuite=kernel
	@echo "$(GREEN)✓ Kernel tests complete$(NC)"

## Run functional tests only
test-functional:
	@echo "$(GREEN)Running functional tests...$(NC)"
	$(PHPUNIT) --testsuite=functional
	@echo "$(GREEN)✓ Functional tests complete$(NC)"

## Check for security vulnerabilities
security:
	@echo "$(GREEN)Checking for security vulnerabilities...$(NC)"
	$(COMPOSER) audit
	@echo "$(GREEN)✓ Security check complete$(NC)"

## Run full CI pipeline
ci: install validate analyze test security
	@echo "$(GREEN)✓ CI pipeline complete$(NC)"

## Create release package
release:
	@echo "$(GREEN)Creating release package for version $(VERSION)...$(NC)"
	@# Create temp directory
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)
	@mkdir -p /tmp/$(MODULE_NAME)-$(VERSION)

	@# Copy files
	@cp -r . /tmp/$(MODULE_NAME)-$(VERSION)

	@# Remove development files
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)/.git
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)/.github
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)/.gitlab-ci.yml
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)/tests
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)/vendor
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)/.phpcs-cache
	@rm -f /tmp/$(MODULE_NAME)-$(VERSION)/phpstan.neon
	@rm -f /tmp/$(MODULE_NAME)-$(VERSION)/phpstan-bootstrap.php
	@rm -f /tmp/$(MODULE_NAME)-$(VERSION)/phpcs.xml.dist
	@rm -f /tmp/$(MODULE_NAME)-$(VERSION)/phpunit.xml.dist
	@rm -f /tmp/$(MODULE_NAME)-$(VERSION)/Makefile
	@rm -f /tmp/$(MODULE_NAME)-$(VERSION)/composer.lock

	@# Create archives
	@cd /tmp && tar -czf $(MODULE_NAME)-$(VERSION).tar.gz $(MODULE_NAME)-$(VERSION)/
	@cd /tmp && zip -qr $(MODULE_NAME)-$(VERSION).zip $(MODULE_NAME)-$(VERSION)/
	@mv /tmp/$(MODULE_NAME)-$(VERSION).tar.gz ./
	@mv /tmp/$(MODULE_NAME)-$(VERSION).zip ./

	@# Cleanup
	@rm -rf /tmp/$(MODULE_NAME)-$(VERSION)

	@echo "$(GREEN)✓ Release packages created:$(NC)"
	@echo "  - $(MODULE_NAME)-$(VERSION).tar.gz"
	@echo "  - $(MODULE_NAME)-$(VERSION).zip"

## Watch for changes and run checks
watch:
	@echo "$(GREEN)Watching for changes... (Press Ctrl+C to stop)$(NC)"
	@while true; do \
		find . -name "*.php" -o -name "*.module" -o -name "*.yml" | \
		grep -v vendor | \
		entr -d make analyze; \
	done

## Install module in Drupal
drupal-install:
	@echo "$(GREEN)Installing module in Drupal...$(NC)"
	@if command -v drush > /dev/null; then \
		drush pm:install $(MODULE_NAME) -y; \
		echo "$(GREEN)✓ Module installed$(NC)"; \
	else \
		echo "$(RED)✗ Drush not found$(NC)"; \
		exit 1; \
	fi

## Enable all module components
drupal-enable:
	@echo "$(GREEN)Enabling all module components...$(NC)"
	@if command -v drush > /dev/null; then \
		drush pm:enable $(MODULE_NAME) db_wipe_entity db_wipe_db db_wipe_ui -y; \
		echo "$(GREEN)✓ All modules enabled$(NC)"; \
	else \
		echo "$(RED)✗ Drush not found$(NC)"; \
		exit 1; \
	fi

## Disable all module components
drupal-disable:
	@echo "$(YELLOW)Disabling all module components...$(NC)"
	@if command -v drush > /dev/null; then \
		drush pm:uninstall db_wipe_ui db_wipe_db db_wipe_entity $(MODULE_NAME) -y; \
		echo "$(GREEN)✓ All modules disabled$(NC)"; \
	else \
		echo "$(RED)✗ Drush not found$(NC)"; \
		exit 1; \
	fi

## Uninstall module from Drupal
drupal-uninstall: drupal-disable
	@echo "$(GREEN)✓ Module uninstalled$(NC)"

## Quick development check
check: phpcs phpstan
	@echo "$(GREEN)✓ Quick check complete$(NC)"

## Generate documentation
docs:
	@echo "$(GREEN)Generating documentation...$(NC)"
	@# You can add documentation generation tools here
	@echo "$(YELLOW)Documentation generation not configured yet$(NC)"

# Shortcuts
.PHONY: i c a f t s r
i: install
c: clean
a: analyze
f: fix
t: test
s: security
r: release