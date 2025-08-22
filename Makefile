# Makefile for JWT Auth Package Automation
.PHONY: help install test update patch minor major push release status clean

# Colors
GREEN=\033[0;32m
YELLOW=\033[1;33m
RED=\033[0;31m
NC=\033[0m

# Variables
PACKAGE_NAME=andydefer/jwt-auth
CURRENT_VERSION=$(shell grep -oP '"version":\s*"\K[0-9]+\.[0-9]+\.[0-9]+' composer.json 2>/dev/null || echo "0.0.0")
BRANCH=master

help: ## Affiche ce help.
	@echo "Utilisation :"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS=":.*?## "}; {printf "  %-20s -> %s\n", $$1, $$2}'

install: ## Installe les dépendances Composer.
	@echo "$(YELLOW)Installing dependencies...$(NC)"
	composer install

test: ## Exécute les tests (si disponibles).
	@echo "$(YELLOW)Running tests...$(NC)"
	# Add your test commands here if you have tests
	# php vendor/bin/phpunit
	@echo "$(GREEN)No tests configured$(NC)"

update: ## Ajoute tous les changements, commit et push.
	@echo "$(YELLOW)Adding all changes...$(NC)"
	git add .
	@read -p "Commit message: " msg; \
	git commit -m "$$msg" || true
	@echo "$(YELLOW)Pushing changes...$(NC)"
	git push origin $(BRANCH)

patch: ## Release patch version (x.x.1).
	@$(eval NEW_VERSION=$(shell echo $(CURRENT_VERSION) | awk -F. '{$$3 = $$3 + 1; OFS="."; print $$1,$$2,$$3}'))
	@make release VERSION=$(NEW_VERSION)

minor: ## Release minor version (x.1.0).
	@$(eval NEW_VERSION=$(shell echo $(CURRENT_VERSION) | awk -F. '{$$2 = $$2 + 1; $$3 = 0; OFS="."; print $$1,$$2,$$3}'))
	@make release VERSION=$(NEW_VERSION)

major: ## Release major version (1.0.0).
	@$(eval NEW_VERSION=$(shell echo $(CURRENT_VERSION) | awk -F. '{$$1 = $$1 + 1; $$2 = 0; $$3 = 0; OFS="."; print $$1,$$2,$$3}'))
	@make release VERSION=$(NEW_VERSION)

push: ## Push vers le remote avec tags.
	@echo "$(YELLOW)Pushing to remote...$(NC)"
	git push origin $(BRANCH)
	git push --tags

release: ## Release une version spécifique (usage: make release VERSION=x.x.x).
ifndef VERSION
	$(error VERSION is not set. Usage: make release VERSION=x.x.x)
endif
	@echo "$(YELLOW)Starting release process for version $(VERSION)...$(NC)"

	# Vérifier si la version existe déjà dans composer.json
	@if ! grep -q '"version":' composer.json; then \
		echo "$(YELLOW)Adding version field to composer.json...$(NC)"; \
		sed -i '/"name":/a\    "version": "$(VERSION)",' composer.json; \
	else \
		echo "$(YELLOW)Updating version in composer.json...$(NC)"; \
		sed -i 's/"version": "[^"]*"/"version": "$(VERSION)"/' composer.json; \
	fi

	# Add all changes
	@echo "$(YELLOW)Adding changes to git...$(NC)"
	git add .

	# Commit with version message
	@echo "$(YELLOW)Creating commit...$(NC)"
	git commit -m "🚀 release: bump version to v$(VERSION)" || true

	# Create tag
	@echo "$(YELLOW)Creating tag v$(VERSION)...$(NC)"
	git tag -a "v$(VERSION)" -m "Version $(VERSION)"

	# Push everything
	@echo "$(YELLOW)Pushing to remote...$(NC)"
	git push origin $(BRANCH)
	git push --tags

	@echo "$(GREEN)✅ Release v$(VERSION) completed successfully!$(NC)"
	@echo "$(YELLOW)Current version: $(VERSION)$(NC)"

status: ## Affiche le statut actuel et la version.
	@echo "$(YELLOW)Current version: $(CURRENT_VERSION)$(NC)"
	@echo "$(YELLOW)Branch: $(BRANCH)$(NC)"
	git status

clean: ## Nettoie les dépendances et fichiers générés.
	@echo "$(YELLOW)Cleaning up...$(NC)"
	rm -rf vendor composer.lock
	@echo "$(GREEN)Clean complete!$(NC)"

# Alias for common commands
up: update ## Alias pour update.
p: patch ## Alias pour patch.
m: minor ## Alias pour minor.
M: major ## Alias pour major.
r: release ## Alias pour release.

# Initialisation de la version si elle n'existe pas
init-version: ## Initialise la version à 0.1.0 si elle n'existe pas.
	@if ! grep -q '"version":' composer.json; then \
		echo "$(YELLOW)Adding initial version to composer.json...$(NC)"; \
		sed -i '/"name":/a\    "version": "0.1.0",' composer.json; \
		echo "$(GREEN)Version initialized to 0.1.0$(NC)"; \
	else \
		echo "$(YELLOW)Version already exists: $(CURRENT_VERSION)$(NC)"; \
	fi