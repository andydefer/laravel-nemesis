# ===================================================
# PHP/Laravel Package Development Makefile
# ===================================================
# This Makefile provides utilities for package development,
# including code quality checks, version management, and file tracking.
# ===================================================

# ---------------------------------------------------
# Tool Executables
# ---------------------------------------------------
PINT = ./vendor/bin/pint
PHPSTAN = ./vendor/bin/phpstan
RECTOR = ./vendor/bin/rector
PSALM = ./vendor/bin/psalm

# ---------------------------------------------------
# Source Configuration
# ---------------------------------------------------
SOURCE_DIRS = src config database tests
IGNORED_FILES = CHANGED_FILES.md FILES_CHECKLIST.md psalm.md phpstan.md pint-test.md Makefile pint.md .gitkeep

# ---------------------------------------------------
# Version Control Operations
# ---------------------------------------------------

.PHONY: pre-commit
pre-commit:
	@echo "🔍 Running pre-commit checks..."
	@rm -f all.txt diff.txt
	@make lint-all-fix-md
	@make test
	@echo "✅ Pre-commit checks passed"

.PHONY: toggle-prompts
toggle-prompts:
	@if grep -q '^prompts/' .gitignore; then \
		# Il est décommenté → on commente \
		sed -i.bak 's/^prompts\//#prompts\//' .gitignore; \
		echo "✅ prompts/ commented in .gitignore"; \
	else \
		# Il est commenté → on décommente \
		sed -i.bak 's/^#\s*prompts\//prompts\//' .gitignore; \
		echo "✅ prompts/ uncommented in .gitignore"; \
	fi

.PHONY: git-commit-push
git-commit-push: pre-commit
	@make toggle-prompts
	@read -p "Enter commit message: " commit_message; \
	if [ -z "$$commit_message" ]; then \
		echo "❌ Error: Commit message cannot be empty"; \
		exit 1; \
	fi; \
	git add .; \
	git commit -m "$$commit_message"; \
	git push
	@make toggle-prompts


.PHONY: git-tag
git-tag:
	@bash -c '\
	read -p "Tag type (major/minor/patch): " tag_type; \
	last_tag=$$(git tag --sort=-v:refname | head -n 1); \
	if [ -z "$$last_tag" ]; then last_tag="0.0.0"; fi; \
	major=$$(echo $$last_tag | cut -d. -f1); \
	minor=$$(echo $$last_tag | cut -d. -f2); \
	patch=$$(echo $$last_tag | cut -d. -f3); \
	case "$$tag_type" in \
		major) major=$$((major + 1)); minor=0; patch=0;; \
		minor) minor=$$((minor + 1)); patch=0;; \
		patch) patch=$$((patch + 1));; \
		*) echo "❌ Invalid tag type: $$tag_type"; exit 1;; \
	esac; \
	new_tag="$$major.$$minor.$$patch"; \
	git tag -a "$$new_tag" -m "Release $$new_tag"; \
	git push origin "$$new_tag"; \
	echo "✅ Released new tag: $$new_tag"; \
	'



# ---------------------------------------------------
# 📝 WORK SUMMARY & AI DIFF
# ---------------------------------------------------

work-create-summary: ## Crée un résumé de travail en Markdown
	@read -p "📝 Nom du résumé : " NAME; \
	DATE=$$(date +%Y-%m-%d); \
	TIME=$$(date +%H-%M-%S); \
	FILENAME="docs/work-summaries/$${DATE}T$${TIME}-$${NAME}.md"; \
	mkdir -p docs/work-summaries; \
	echo "📋 Colle le contenu Markdown (CTRL+D) :"; \
	cat > "$$FILENAME"; \
	echo "✅ Fichier créé : $$FILENAME"

generate-ai-diff: ## Generate clean diff for AI review
	@read -p "📁 Chemins (vide = tous) : " DIR_PATHS; \
	DATE=$$(date +%Y-%m-%d); \
	TIME=$$(date +%H-%M-%S); \
	DIFF_FILENAME="docs/diffs/$${DATE}T$${TIME}-diff.md"; \
	mkdir -p docs/diffs; \
	echo "Tu es un expert en revue de code et en conventions de commits (Conventional Commits)." > $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "À partir du diff Git ci-dessous, fais les choses suivantes :" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "1. Propose un nom de fichier pour le work summary" >> $$DIFF_FILENAME; \
	echo "   (ex: api-standardization, contact-message-refactoring, geo-helper-improvement)" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "2. Propose un nom de commit clair et concis en anglais" >> $$DIFF_FILENAME; \
	echo "   avec le format <type>(<scope>): <description>," >> $$DIFF_FILENAME; \
	echo "   en respectant les Conventional Commits" >> $$DIFF_FILENAME; \
	echo "   (ex: feat:, fix:, refactor:, test:, chore:, docs:)." >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "3. Rédige un résumé du travail effectué en quelques phrases," >> $$DIFF_FILENAME; \
	echo "   orienté métier et technique. (en français sauf le nom du commit)" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "4. Donne une liste d'exemples concrets de changements, en t'appuyant sur le diff :" >> $$DIFF_FILENAME; \
	echo "   - méthodes ajoutées, modifiées ou supprimées" >> $$DIFF_FILENAME; \
	echo "   - responsabilités déplacées ou clarifiées" >> $$DIFF_FILENAME; \
	echo "   - améliorations de validation, de logique ou de structure" >> $$DIFF_FILENAME; \
	echo "   - impacts fonctionnels éventuels" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "Contraintes :" >> $$DIFF_FILENAME; \
	echo "   - Ne décris que ce qui est réellement visible dans le diff" >> $$DIFF_FILENAME; \
	echo "   - Sois précis, factuel et structuré" >> $$DIFF_FILENAME; \
	echo "   - Évite les suppositions" >> $$DIFF_FILENAME; \
	echo "   - Utilise un ton professionnel" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "5. SI et SEULEMENT SI les changements sont cassants (breaking changes) :" >> $$DIFF_FILENAME; \
	echo "   - Génère une entrée de CHANGELOG conforme à Keep a Changelog et SemVer." >> $$DIFF_FILENAME; \
	echo "   - Le changelog doit apparaître APRES les recommandations ci-dessus." >> $$DIFF_FILENAME; \
	echo "   - Utilise STRICTEMENT la structure suivante :" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "     ## [X.0.0] - YYYY-MM-DD" >> $$DIFF_FILENAME; \
	echo "     ### Changed" >> $$DIFF_FILENAME; \
	echo "     - Description claire du changement cassant" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "     ### Removed (si applicable)" >> $$DIFF_FILENAME; \
	echo "     - API, méthode ou comportement supprimé" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "     ### Security (si applicable)" >> $$DIFF_FILENAME; \
	echo "     - Impact sécurité lié au changement" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "   - Ne génère PAS de changelog si aucun breaking change n'est détecté." >> $$DIFF_FILENAME; \
	echo "   - N'invente PAS de version." >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "Voici le diff :" >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo '```diff' >> $$DIFF_FILENAME; \
	if [ -z "$$DIR_PATHS" ]; then \
		git diff HEAD -- . ':!*.phpunit.result.cache' ':!diff.txt' ':!docs/*' >> $$DIFF_FILENAME; \
	else \
		git diff HEAD -- $$DIR_PATHS ':!*.phpunit.result.cache' ':!diff.txt' ':!docs/*' >> $$DIFF_FILENAME; \
	fi; \
	echo '```' >> $$DIFF_FILENAME; \
	echo "" >> $$DIFF_FILENAME; \
	echo "✅ Diff généré : $$DIFF_FILENAME"
work-create-summary-from-diff: ## Crée un work summary (génère d'abord le diff, puis attend la réponse IA)
	@# 1. Générer le diff
	$(MAKE) generate-ai-diff
	@echo ""
	@echo "📄 Le fichier diff a été généré dans docs/diffs/"
	@echo "📋 Envoie ce fichier à l'IA pour analyse"
	@echo ""
	@read -p "Appuie sur ENTRÉE quand tu as reçu la réponse de l'IA..." ENTER
	@echo ""
	@read -p "📝 Nom du fichier work summary : " NAME; \
	DATE=$$(date +%Y-%m-%d); \
	TIME=$$(date +%H-%M-%S); \
	FILENAME="docs/work-summaries/$${DATE}T$${TIME}-$${NAME}.md"; \
	mkdir -p docs/work-summaries; \
	echo "📋 Colle la réponse de l'IA (CTRL+D pour terminer) :"; \
	echo "─────────────────────────────────────────"; \
	cat > "$$FILENAME"; \
	echo "─────────────────────────────────────────"; \
	echo "✅ Work summary créé : $$FILENAME"; \
	echo ""; \
	read -p "📝 Message de commit (proposé par l'IA) : " COMMIT_MSG; \
	if [ -n "$$COMMIT_MSG" ]; then \
		git add .; \
		git commit -m "$$COMMIT_MSG"; \
		echo "✅ Commit créé"; \
		echo ""; \
		read -p "🚀 Pousser le commit ? (o/N) : " PUSH_CONFIRM; \
		if [ "$$PUSH_CONFIRM" = "o" ] || [ "$$PUSH_CONFIRM" = "O" ]; then \
			git push origin $(GIT_BRANCH); \
			echo "✅ Commit poussé"; \
		else \
			echo "⏭️ Push annulé (par défaut: non)"; \
		fi \
	else \
		echo "⏭️ Aucun commit créé"; \
	fi

.PHONY: git-tag-republish
git-tag-republish:
	@bash -c '\
	last_tag=$$(git tag --sort=-v:refname | head -n 1); \
	if [ -z "$$last_tag" ]; then echo "❌ No tags found!"; exit 1; fi; \
	echo "Republishing last tag: $$last_tag"; \
	git push origin "$$last_tag" --force; \
	echo "✅ Tag $$last_tag republished"; \
	'

# ---------------------------------------------------
# File Management Operations
# ---------------------------------------------------

.PHONY: update-checklist
update-checklist:
	@echo "📋 Updating FILES_CHECKLIST.md..."
	@if [ -f FILES_CHECKLIST.md ]; then \
		grep -E '^[0-9]+\. .* \[[ xX]\]$$' FILES_CHECKLIST.md > .existing_checklist.tmp; \
		awk -F' ' '{ \
			file_path=""; \
			for(i=2;i<NF;i++) { \
				if(i>2) file_path=file_path" "; \
				file_path=file_path$$i; \
			} \
			checkmark_state=$$NF; \
			print file_path " " checkmark_state \
		}' .existing_checklist.tmp > .existing_files.tmp; \
	else \
		touch .existing_files.tmp; \
		touch FILES_CHECKLIST.md; \
	fi; \
	echo "# Project File Checklist" > FILES_CHECKLIST.md; \
	echo "*Last updated: $$(date)*" >> FILES_CHECKLIST.md; \
	echo "" >> FILES_CHECKLIST.md; \
	echo "## Previously Checked Files" >> FILES_CHECKLIST.md; \
	file_count=1; \
	grep '\[x\]' .existing_files.tmp | sort | uniq | while read -r line; do \
		file_path=$$(echo "$$line" | awk '{$$NF=""; print $$0}' | sed 's/ $$//'); \
		echo "$$file_count. $$file_path [x]" >> FILES_CHECKLIST.md; \
		file_count=$$((file_count + 1)); \
	done; \
	previously_checked_files=$$(grep '\[x\]' .existing_files.tmp | awk '{$$NF=""; print $$0}' | sed 's/ $$//'); \
	echo "" >> FILES_CHECKLIST.md; \
	echo "## Other Files" >> FILES_CHECKLIST.md; \
	file_count=1; \
	find $(SOURCE_DIRS) -type f | sort | while read -r file_path; do \
		if ! echo "$$previously_checked_files" | grep -Fxq "$$file_path" 2>/dev/null; then \
			echo "$$file_count. $$file_path [ ]" >> FILES_CHECKLIST.md; \
			file_count=$$((file_count + 1)); \
		fi; \
	done; \
	rm -f .existing_checklist.tmp .existing_files.tmp; \
	echo "✅ FILES_CHECKLIST.md updated successfully"

.PHONY: list-modified-files
list-modified-files:
	@echo "📝 Updating CHANGED_FILES.md..."
	@previously_checked_files=$$(grep -E '^[0-9]+\. .* \[[xX]\]' FILES_CHECKLIST.md | sed 's/^[0-9]\+\. //' | sed 's/ *\[[xX]\]$$//'); \
	modified_file_count=0; \
	all_files=$$( (git diff --name-only; git ls-files --others --exclude-standard) | sort -u ); \
	echo "# Changed and Untracked Files" > CHANGED_FILES.md; \
	echo "*Updated: $$(date)*" >> CHANGED_FILES.md; \
	echo "" >> CHANGED_FILES.md; \
	echo "## Files to Review (modifications on checked files)" >> CHANGED_FILES.md; \
	for file_path in $$all_files; do \
		if echo "$$previously_checked_files" | grep -Fxq "$$file_path"; then \
			modified_file_count=$$((modified_file_count + 1)); \
			echo "$$modified_file_count. $$file_path [x]" >> CHANGED_FILES.md; \
		fi; \
	done; \
	if [ $$modified_file_count -eq 0 ]; then \
		echo "*(No modified files in this category)*" >> CHANGED_FILES.md; \
	fi; \
	echo "" >> CHANGED_FILES.md; \
	echo "## Other Modified Files" >> CHANGED_FILES.md; \
	modified_file_count=0; \
	for file_path in $$all_files; do \
		should_skip_file=0; \
		for ignored_file in $$(echo -e "$(IGNORED_FILES)"); do \
			if [ "$$file_path" = "$$ignored_file" ]; then should_skip_file=1; break; fi; \
		done; \
		if [ $$should_skip_file -eq 0 ] && ! echo "$$previously_checked_files" | grep -Fxq "$$file_path"; then \
			modified_file_count=$$((modified_file_count + 1)); \
			echo "$$modified_file_count. $$file_path [ ]" >> CHANGED_FILES.md; \
		fi; \
	done; \
	if [ $$modified_file_count -eq 0 ]; then \
		echo "*(No modified files in this category)*" >> CHANGED_FILES.md; \
	fi; \
	echo "✅ CHANGED_FILES.md updated successfully"

.PHONY: update-all
update-all: update-checklist list-modified-files
	@echo "✅ All file management updates completed"

.PHONY: concat-all
concat-all:
	@read -p "📁 Enter the source directory path to scan (leave empty for default './app ./database ./routes'): " SOURCE_PATH; \
	if [ -z "$$SOURCE_PATH" ]; then \
		SOURCE_DIRS="./app ./database ./routes"; \
		echo "🔗 Concatenating all PHP files from default directories: $${SOURCE_DIRS} into all.txt..."; \
	else \
		SOURCE_DIRS="$$SOURCE_PATH"; \
		echo "🔗 Concatenating all PHP files from directory: $${SOURCE_DIRS} into all.txt..."; \
	fi; \
	find $${SOURCE_DIRS} -type f -name "*.php" -exec sh -c 'echo ""; echo "// ==== {} ==="; echo ""; cat {}' \; > all.txt; \
	echo "✅ File all.txt generated successfully from: $${SOURCE_DIRS}"
# ---------------------------------------------------
# Testing
# ---------------------------------------------------

.PHONY: test
test: clean-testbench-migrations
	@./vendor/bin/phpunit --testdox --display-notices

# ---------------------------------------------------
# Code Quality Tools (Console Output Versions)
# ---------------------------------------------------

.PHONY: lint-php
lint-php:
	@echo "🛠️  Running Pint code formatter..."
	@$(PINT) --test
	@echo "✅ Pint formatting check completed"

.PHONY: lint-php-fix
lint-php-fix:
	@echo "🛠️  Running Pint code formatter..."
	@$(PINT)
	@echo "✅ Pint formatting applied"

.PHONY: lint-phpstan
lint-phpstan:
	@echo "🔍 Running PHPStan static analysis..."
	@$(PHPSTAN) analyse src tests --level=max
	@echo "✅ PHPStan analysis completed"

.PHONY: lint-rector
lint-rector:
	@echo "🔄 Running Rector refactoring..."
	@$(RECTOR) process
	@echo "✅ Rector refactoring completed"

.PHONY: lint-psalm
lint-psalm:
	@echo "📖 Running Psalm static analysis..."
	@$(PSALM) --show-info=true
	@echo "✅ Psalm analysis completed"

# ---------------------------------------------------
# Code Quality Tools (Markdown Report Versions)
# ---------------------------------------------------

.PHONY: lint-php-md
lint-php-md:
	@echo "📊 Running Pint and saving report to pint.md..."
	@echo "# Pint Code Formatter Report" > pint.md
	@echo "*Generated: $$(date)*" >> pint.md
	@echo "" >> pint.md
	@$(PINT) --test --verbose 2>&1 >> pint.md || true
	@echo "✅ Pint report saved to pint.md"

.PHONY: lint-php-fix-md
lint-php-fix-md:
	@echo "📊 Running Pint formatting test and saving report to pint-test.md..."
	@echo "# Pint Formatting Test Report" > pint-test.md
	@echo "*Generated: $$(date)*" >> pint-test.md
	@echo "" >> pint-test.md
	@$(PINT) --test 2>&1 >> pint-test.md || true
	@echo "✅ Pint formatting test report saved to pint-test.md"

.PHONY: lint-phpstan-md
lint-phpstan-md:
	@echo "📊 Running PHPStan and saving report to phpstan.md..."
	@echo "# PHPStan Static Analysis Report" > phpstan.md
	@echo "*Generated: $$(date)*" >> phpstan.md
	@echo "" >> phpstan.md
	@$(PHPSTAN) analyse src tests --level=max --no-progress 2>&1 >> phpstan.md || true
	@echo "✅ PHPStan report saved to phpstan.md"

.PHONY: lint-rector-md
lint-rector-md:
	@echo "📊 Running Rector and saving report to rector.md..."
	@echo "# Rector Refactoring Report" > rector.md
	@echo "*Generated: $$(date)*" >> rector.md
	@echo "" >> rector.md
	@$(RECTOR) process --dry-run 2>&1 >> rector.md || true
	@echo "✅ Rector report saved to rector.md"

.PHONY: lint-psalm-md
lint-psalm-md:
	@echo "📊 Running Psalm and saving report to psalm.md..."
	@echo "# Psalm Static Analysis Report" > psalm.md
	@echo "*Generated: $$(date)*" >> psalm.md
	@echo "" >> psalm.md
	@$(PSALM) --show-info=true --no-progress 2>&1 >> psalm.md || true
	@echo "✅ Psalm report saved to psalm.md"

.PHONY: clean-testbench-migrations
clean-testbench-migrations:
	@echo "🧹 Cleaning Orchestra Testbench migrations..."
	@rm -f vendor/orchestra/testbench-core/laravel/database/migrations/*_create_roster_*_table.php || true
	@echo "✅ Testbench migrations cleaned"

# ---------------------------------------------------
# Batch Quality Checks (Non-blocking)
# ---------------------------------------------------

.PHONY: lint-all-md
lint-all-md:
	@echo "📦 Running all code quality checks and saving reports..."
	@make lint-php-md
	@make lint-phpstan-md
	@make lint-psalm-md
	@echo "✅ All code quality reports generated"
	@echo "📋 Reports:"
	@echo "  - pint.md (Pint formatting)"
	@echo "  - phpstan.md (PHPStan analysis)"
	@echo "  - psalm.md (Psalm analysis)"

.PHONY: lint-all-fix-md
lint-all-fix-md:
	@echo "📦 Running all code fixers and saving reports..."
	@make lint-php-fix-md
	@make lint-rector-md
	@echo "✅ All code fixer reports generated"
	@echo "📋 Reports:"
	@echo "  - pint-test.md (Pint formatting test)"
	@echo "  - rector.md (Rector refactoring)"

# ---------------------------------------------------
# Release Management Workflow
# ---------------------------------------------------

.PHONY: pre-release
pre-release:
	@echo "🚀 Running pre-release checks..."
	@echo "📊 Generating quality reports..."
	@make test
	@make lint-all-md
	@echo "✅ Pre-release checks completed"
	@echo "📋 Review reports before release:"
	@echo "  - pint.md (formatting issues)"
	@echo "  - phpstan.md (static analysis errors)"
	@echo "  - psalm.md (type checking issues)"

.PHONY: release
release: pre-release
	@echo "🚀 Creating release..."
	@make git-tag
	@echo "✅ Release created successfully"

.PHONY: post-release
post-release:
	@echo "🧹 Performing post-release cleanup..."
	@make update-all
	@echo "✅ Post-release cleanup completed"

# ---------------------------------------------------
# Help & Documentation
# ---------------------------------------------------

.PHONY: help
help:
	@echo "📚 Available commands:"
	@echo ""
	@echo "🚀 Version Control:"
	@echo "  git-commit-push       Commit and push all changes"
	@echo "  git-tag               Create and push a new version tag"
	@echo "  generate-ai-diff      Generate clean diff for AI review"
	@echo "  git-tag-republish     Force push the last tag"
	@echo ""
	@echo "📁 File Management:"
	@echo "  update-checklist      Update file checklist"
	@echo "  list-modified-files   List modified files"
	@echo "  update-all            Update checklist and modified files"
	@echo "  concat-all            Concatenate all PHP files"
	@echo ""
	@echo "🧪 Testing:"
	@echo "  test                  Run PHPUnit tests"
	@echo ""
	@echo "🔍 Code Quality (Console - fails on error):"
	@echo "  lint-php              Run Pint code formatter"
	@echo "  lint-php-fix          Apply formatting with Pint"
	@echo "  lint-phpstan          Run PHPStan static analysis"
	@echo "  lint-rector           Apply refactoring with Rector"
	@echo "  lint-psalm            Run Psalm analysis"
	@echo ""
	@echo "📊 Code Quality (Markdown - non-blocking):"
	@echo "  lint-php-md           Run Pint and save report"
	@echo "  lint-php-fix-md       Test formatting and save report"
	@echo "  lint-phpstan-md       Run PHPStan and save results"
	@echo "  lint-rector-md        Run Rector and save report"
	@echo "  lint-psalm-md         Run Psalm and save results"
	@echo "  lint-all-md           Run all linters (non-blocking)"
	@echo "  lint-all-fix-md       Run all fixers (non-blocking)"
	@echo ""
	@echo "🔄 Release Management:"
	@echo "  pre-release           Run all pre-release checks"
	@echo "  release               Create new release (includes pre-release)"
	@echo "  post-release          Clean up after release"
	@echo ""
	@echo "❓ Help:"
	@echo "  help                  Display this help message"

# ---------------------------------------------------
# Default Target
# ---------------------------------------------------
.DEFAULT_GOAL := help