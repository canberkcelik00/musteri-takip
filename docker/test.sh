#!/bin/sh
# Runs the test suite (`composer test`). In the local app container the project is a Windows
# bind mount, where every file read is slow, so the suite runs in parallel from a copy on the
# container's own disk (T-134). Elsewhere (CI) it runs in place, in a single process.
set -e

php artisan config:clear --ansi

if [ -f /.dockerenv ] && [ "$(pwd)" = /var/www/html ]; then
    rm -rf /tmp/test-run
    mkdir /tmp/test-run
    tar --exclude=./node_modules --exclude=./.git -cf - . | tar -xf - -C /tmp/test-run
    cd /tmp/test-run
    exec php artisan test --parallel "$@"
fi

exec php artisan test "$@"
