<?php

declare(strict_types=1);

/*
 * Runtime bootstrap for static analysis (see phpstan.neon.dist -> bootstrapFiles).
 *
 * This package has no Laravel application, so two things the framework normally
 * provides must be supplied for Larastan to run:
 *
 *  1. LARAVEL_VERSION — Larastan reads it to pick version-specific stubs. We
 *     derive it from the installed illuminate/support version.
 *  2. The global path helpers — Larastan calls some of them during analysis.
 *     We define no-op fallbacks only when the real framework isn't present.
 *     (Their signatures for analysing OUR code live in stubs/laravel-helpers.stub.)
 */

if (! defined('LARAVEL_VERSION')) {
    $version = '12.0.0';

    if (class_exists(\Composer\InstalledVersions::class)
        && \Composer\InstalledVersions::isInstalled('illuminate/support')) {
        $version = ltrim((string) \Composer\InstalledVersions::getPrettyVersion('illuminate/support'), 'v');
    }

    define('LARAVEL_VERSION', $version);
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return __DIR__.($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return __DIR__.'/app'.($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return __DIR__.'/config'.($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return __DIR__.'/database'.($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return __DIR__.'/storage'.($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }
}
