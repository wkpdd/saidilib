<?php

/**
 * Loads production configuration from an external file kept OUTSIDE the web
 * root — one directory above the application folder. On cPanel this is:
 *
 *     /home/USERNAME/config_saidi.php          <- secrets (DB, APP_KEY, URL)
 *     /home/USERNAME/saidi.h47.io/             <- this application
 *
 * The file (if present) populates environment variables before Laravel boots,
 * so no .env is needed in production. Harmless in local dev (file absent).
 */
$__saidiConfig = dirname(__DIR__, 2) . '/config_saidi.php';

if (is_file($__saidiConfig)) {
    require $__saidiConfig;
}
