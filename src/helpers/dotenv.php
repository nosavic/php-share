<?php

// Include Composer's autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

/**
 * Load environment variables from the .env file.
 */
function loadEnv()
{
    // Adjust the path to point to the root directory
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');

    // Load the .env file
    $dotenv->load();
}

/**
 * Get an environment variable.
 *
 * @param string $key The environment variable name.
 * @param mixed $default The default value to return if the variable is not found.
 * @return mixed The environment variable value or the default value.
 */
function getEnvVar($key, $default = null)
{
    // Return the environment variable value or the default value
    return $_ENV[$key] ?? $default;
}
