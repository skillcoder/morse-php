<?php
// Project source paths for linting, scanning and Rector.
// Must stay PHP 5.6 compatible: lint.php runs it inside old PHP images.
//
// CLI: php paths.php [project-root]   -> JSON array of paths relative to the root

function phpm_excluded_dirs()
{
    return array('vendor', '.git', '.claude', 'node_modules', '.idea', '.vscode', 'build', 'var', 'cache');
}

function phpm_paths($root)
{
    $root = rtrim($root, '/');
    $paths = array();
    $wholeRoot = false;

    $composerFile = $root . '/composer.json';
    if (is_file($composerFile)) {
        $composer = json_decode(file_get_contents($composerFile), true);
        foreach (array('autoload', 'autoload-dev') as $section) {
            if (empty($composer[$section]) || !is_array($composer[$section])) {
                continue;
            }
            foreach (array('psr-4', 'psr-0') as $standard) {
                if (empty($composer[$section][$standard])) {
                    continue;
                }
                foreach ($composer[$section][$standard] as $dirs) {
                    foreach ((array) $dirs as $dir) {
                        $paths[] = $dir;
                    }
                }
            }
            foreach (array('classmap', 'files') as $key) {
                if (!empty($composer[$section][$key])) {
                    foreach ((array) $composer[$section][$key] as $path) {
                        $paths[] = $path;
                    }
                }
            }
        }
    }

    // Test directories are often missing from autoload-dev. Compare against the real listing:
    // is_dir('Tests') is also true for 'tests' on case-insensitive filesystems.
    foreach (array_intersect(scandir($root), array('tests', 'test', 'Tests')) as $dir) {
        if (is_dir($root . '/' . $dir)) {
            $paths[] = $dir;
        }
    }

    $clean = array();
    foreach ($paths as $path) {
        $path = trim(preg_replace('#^\./#', '', $path), '/');
        if ($path === '' || $path === '.') {
            $wholeRoot = true;
            continue;
        }
        $first = explode('/', $path);
        if (in_array($first[0], phpm_excluded_dirs(), true) || !file_exists($root . '/' . $path)) {
            continue;
        }
        $clean[$path] = true;
    }

    if ($wholeRoot || !$clean) {
        // Fall back to every top-level entry that holds PHP code.
        foreach (scandir($root) as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, phpm_excluded_dirs(), true)) {
                continue;
            }
            if ((is_file($root . '/' . $entry) && substr($entry, -4) === '.php')
                || (is_dir($root . '/' . $entry) && phpm_php_files($root, array($entry), 1))) {
                $clean[$entry] = true;
            }
        }
    }

    $result = array_keys($clean);
    sort($result);
    return $result;
}

// All *.php files below the given paths, relative to the root. $limit > 0 stops early.
function phpm_php_files($root, $paths, $limit = 0)
{
    $files = array();
    foreach ($paths as $path) {
        $full = $root . '/' . $path;
        if (is_file($full)) {
            $files[] = $path;
            continue;
        }
        if (!is_dir($full)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
                function ($current) {
                    return !($current->isDir() && in_array($current->getFilename(), phpm_excluded_dirs(), true));
                }
            )
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
                if ($limit > 0 && count($files) >= $limit) {
                    return $files;
                }
            }
        }
    }
    sort($files);
    return array_values(array_unique($files));
}

if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $root = isset($argv[1]) ? $argv[1] : getcwd();
    echo json_encode(phpm_paths($root)), "\n";
}
