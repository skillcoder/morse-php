<?php
// Run `php -l` with every error level on each project file, using the PHP that runs this script.
// Must stay PHP 5.6 compatible: it runs inside the image of the version being checked.
//
// CLI: php lint.php [project-root]   -> JSON summary; exit 0 clean, 1 problems

require __DIR__ . '/paths.php';

$root = isset($argv[1]) ? rtrim($argv[1], '/') : getcwd();
$files = phpm_php_files($root, phpm_paths($root));
$problems = array();

foreach ($files as $file) {
    $output = array();
    $command = escapeshellarg(PHP_BINARY)
        . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -l '
        . escapeshellarg($root . '/' . $file) . ' 2>&1';
    exec($command, $output, $code);

    $messages = array();
    foreach ($output as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, 'No syntax errors detected') === 0) {
            continue;
        }
        $messages[] = str_replace($root . '/', '', $line);
    }
    if ($code !== 0 || $messages) {
        $problems[] = array('file' => $file, 'exit' => $code, 'messages' => array_slice($messages, 0, 10));
    }
}

$summary = array(
    'php' => PHP_VERSION,
    'files' => count($files),
    'problem_files' => count($problems),
    'problems' => array_slice($problems, 0, 50),
);
echo json_encode($summary, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0), "\n";
exit($problems ? 1 : 0);
