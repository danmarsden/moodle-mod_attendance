#!/usr/bin/env bash
# .devcontainer/setup.sh
#
# Sets up a Moodle development environment inside the devcontainer.
# This script is run once after the container is created (postCreateCommand).
# It is safe to run multiple times — each step is idempotent.

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
MOODLE_DIR=/var/www/html
MOODLE_DATA=/var/www/moodledata
PLUGIN_DIR=/workspaces/moodle-mod_attendance

# Moodle branch to clone.  Change to a newer stable branch as needed.
# See https://github.com/moodle/moodle/branches for available branches.
MOODLE_BRANCH="MOODLE_500_STABLE"

DB_HOST="${MOODLE_DOCKER_DBHOST:-db}"
DB_NAME="${MOODLE_DOCKER_DBNAME:-moodle}"
DB_USER="${MOODLE_DOCKER_DBUSER:-moodle}"
DB_PASS="${MOODLE_DOCKER_DBPASS:-moodle}"
WWWROOT="${MOODLE_DOCKER_WWWROOT:-http://localhost:8080}"

ADMIN_USER="admin"
ADMIN_PASS="Admin1!"
ADMIN_EMAIL="admin@example.com"

# ---------------------------------------------------------------------------
# Helper: print a section header
# ---------------------------------------------------------------------------
step() { echo; echo "==> $*"; }

# ---------------------------------------------------------------------------
# 1. Wait for the database to accept connections
# ---------------------------------------------------------------------------
step "Waiting for the database at $DB_HOST ..."
until mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      -e "SELECT 1" &>/dev/null; do
    echo "  database not ready yet — retrying in 3 s"
    sleep 3
done
echo "  database is ready."

# ---------------------------------------------------------------------------
# 2. Clone Moodle core (skipped if the directory already contains Moodle)
# ---------------------------------------------------------------------------
step "Checking for Moodle core in $MOODLE_DIR ..."
if [ ! -f "$MOODLE_DIR/version.php" ]; then
    echo "  Cloning Moodle branch $MOODLE_BRANCH (this may take a few minutes) ..."
    git clone \
        --depth=1 \
        --branch="$MOODLE_BRANCH" \
        https://github.com/moodle/moodle.git \
        "$MOODLE_DIR"
    echo "  Clone complete."
else
    echo "  Moodle core already present — skipping clone."
fi

# ---------------------------------------------------------------------------
# 3. Symlink the plugin into Moodle's mod directory
# ---------------------------------------------------------------------------
step "Linking plugin into Moodle ..."
PLUGIN_TARGET="$MOODLE_DIR/mod/attendance"

if [ -L "$PLUGIN_TARGET" ]; then
    echo "  Symlink already exists — skipping."
elif [ -d "$PLUGIN_TARGET" ]; then
    echo "  WARNING: $PLUGIN_TARGET is a real directory; replacing with symlink."
    rm -rf "$PLUGIN_TARGET"
    ln -s "$PLUGIN_DIR" "$PLUGIN_TARGET"
else
    ln -s "$PLUGIN_DIR" "$PLUGIN_TARGET"
    echo "  Symlinked $PLUGIN_DIR -> $PLUGIN_TARGET"
fi

# ---------------------------------------------------------------------------
# 4. Create the moodledata directory and set permissions
# ---------------------------------------------------------------------------
step "Preparing moodledata directory ..."
mkdir -p "$MOODLE_DATA"
chown -R www-data:www-data "$MOODLE_DATA"

# ---------------------------------------------------------------------------
# 5. Install Moodle via CLI (skipped if config.php already exists)
# ---------------------------------------------------------------------------
step "Checking Moodle installation status ..."
if [ ! -f "$MOODLE_DIR/config.php" ]; then
    echo "  Running Moodle CLI installer ..."
    php "$MOODLE_DIR/admin/cli/install.php" \
        --lang=en \
        --wwwroot="$WWWROOT" \
        --dataroot="$MOODLE_DATA" \
        --dbtype=mariadb \
        --dbhost="$DB_HOST" \
        --dbname="$DB_NAME" \
        --dbuser="$DB_USER" \
        --dbpass="$DB_PASS" \
        --fullname="Moodle Development Site" \
        --shortname="dev" \
        --adminuser="$ADMIN_USER" \
        --adminpass="$ADMIN_PASS" \
        --adminemail="$ADMIN_EMAIL" \
        --non-interactive \
        --agree-license

    echo "  Moodle installation complete."
else
    echo "  config.php already exists — skipping installation."
fi

# ---------------------------------------------------------------------------
# 6. Install Composer dependencies for the plugin (if composer.json exists)
# ---------------------------------------------------------------------------
step "Installing Composer dependencies for the plugin ..."
if [ -f "$PLUGIN_DIR/composer.json" ]; then
    cd "$PLUGIN_DIR"
    if ! command -v composer &>/dev/null; then
        echo "  Downloading Composer ..."
        php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
        php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
        rm /tmp/composer-setup.php
    fi
    composer install --no-interaction --no-progress 2>/dev/null || true
    echo "  Composer dependencies installed."
else
    echo "  No composer.json found — skipping."
fi

# ---------------------------------------------------------------------------
# 7. Enable developer mode and disable caches for a better dev experience
# ---------------------------------------------------------------------------
step "Configuring Moodle for development ..."
php "$MOODLE_DIR/admin/cli/cfg.php" --name=debugdisplay --set=1 2>/dev/null || true
php "$MOODLE_DIR/admin/cli/cfg.php" --name=debug --set=32767 2>/dev/null || true
php "$MOODLE_DIR/admin/cli/cfg.php" --name=cachejs --set=0 2>/dev/null || true
php "$MOODLE_DIR/admin/cli/cfg.php" --name=themedesignermode --set=1 2>/dev/null || true

# ---------------------------------------------------------------------------
# 8. Configure PHPUnit
# ---------------------------------------------------------------------------
PHPUNIT_DATA=/var/www/phpunit_moodledata

step "Configuring PHPUnit ..."
mkdir -p "$PHPUNIT_DATA"

# Inject phpunit settings into config.php before the require_once line if not already present.
if ! grep -q 'phpunit_dataroot' "$MOODLE_DIR/config.php"; then
    sed -i "s|require_once(__DIR__ . '/lib/setup.php');|\$CFG->phpunit_dataroot = '$PHPUNIT_DATA';\n\$CFG->phpunit_prefix   = 'phpu_';\n\nrequire_once(__DIR__ . '/lib/setup.php');|" \
        "$MOODLE_DIR/config.php"
    echo "  Added phpunit settings to config.php."
else
    echo "  phpunit settings already present — skipping."
fi

# Initialise the PHPUnit test database tables and dataroot (safe to re-run).
php "$MOODLE_DIR/admin/tool/phpunit/cli/init.php"
echo "  PHPUnit initialised."

step "Setting up permissions..."
chown -R www-data:www-data "$MOODLE_DIR"
chown -R www-data:www-data "$PHPUNIT_DATA"
# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
echo
echo "========================================================"
echo "  Setup complete!"
echo "  Moodle URL : $WWWROOT"
echo "  Admin user : $ADMIN_USER"
echo "  Admin pass : $ADMIN_PASS"
echo "========================================================"
