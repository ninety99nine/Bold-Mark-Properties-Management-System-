#!/bin/bash
# Container health check — called by Docker every 10s.
# Returns 0 (healthy) if PHP can bootstrap and run an artisan command.
# Returns 1 (unhealthy) if PHP-FPM or Laravel is broken.
php artisan inspire > /dev/null 2>&1 && exit 0 || exit 1
