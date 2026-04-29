#!/usr/bin/env bash
# scripts/create_test_course.sh
#
# Convenience wrapper around the PHP CLI script that creates (or removes) a
# test course with mod_attendance configured for development and testing.
#
# Run from anywhere inside the devcontainer:
#
#   bash /workspaces/moodle-mod_attendance/scripts/create_test_course.sh
#   bash /workspaces/moodle-mod_attendance/scripts/create_test_course.sh --students=5
#   bash /workspaces/moodle-mod_attendance/scripts/create_test_course.sh --cleanup

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="${SCRIPT_DIR}/create_test_course.php"

exec php "${PHP_SCRIPT}" "$@"
