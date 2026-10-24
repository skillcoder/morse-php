<?php
// Summarise a PHPUnit run from its JUnit XML and console output.
// CLI: php summarize-tests.php <junit.xml> <console.txt> <phpunit exit code> <php version> <phpunit version>

[$_, $junitFile, $consoleFile, $exitCode, $php, $phpunit] = $argv + [null, '', '', '2', '', ''];

$summary = [
    'php' => $php,
    'phpunit' => $phpunit,
    'exit' => (int) $exitCode,
    'passed' => (int) $exitCode === 0,
    'tests' => null,
    'assertions' => null,
    'failures' => null,
    'errors' => null,
    'skipped' => null,
    'failed_tests' => [],
    'result_lines' => [],
    'issues' => [],
];

if (is_file($junitFile) && ($xml = @simplexml_load_file($junitFile)) !== false) {
    $suite = $xml->testsuite ?? $xml;
    foreach (['tests', 'assertions', 'failures', 'errors', 'skipped'] as $key) {
        $summary[$key] = isset($suite[$key]) ? (int) $suite[$key] : null;
    }
    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
        $problem = $case->failure ?? $case->error;
        $first = strtok(trim((string) $problem), "\n");
        $summary['failed_tests'][] = $case['class'] . '::' . $case['name'] . ' — ' . $first;
        if (count($summary['failed_tests']) >= 30) {
            break;
        }
    }
}

if (is_file($consoleFile)) {
    $console = preg_replace('/\e\[[0-9;]*m/', '', (string) file_get_contents($consoleFile));
    // PHPUnit's verdict lines, and the PHP errors printed by display_errors (project and vendor).
    $lines = preg_split('/\R/', $console);
    $kind = 'issue';
    foreach ($lines as $i => $line) {
        if (preg_match('/^(OK\b|OK, but|Tests: |FAILURES!|ERRORS!|WARNINGS!|DEPRECATIONS!|NOTICES!|No tests executed)/', $line)) {
            $summary['result_lines'][] = trim($line);
        }
        // PHPUnit >= 10: "1 test triggered 1 PHP deprecation:" then "1) /app/src/File.php:19" + message line.
        if (preg_match('/triggered \d+ (PHP )?(deprecation|warning|notice|error)s?/i', $line, $m)) {
            $kind = ucfirst(strtolower($m[2]));
        }
        if (preg_match('#^\d+\) (/\S+):(\d+)$#', $line, $m) && isset($lines[$i + 1]) && trim($lines[$i + 1]) !== '') {
            $where = str_contains($m[1], '/vendor/') ? 'vendor' : 'project';
            $summary['issues'][] = "[$where] $kind: " . trim($lines[$i + 1]) . ' in '
                . str_replace('/app/', '', $m[1]) . ':' . $m[2];
        }
        if (preg_match('/^(PHP )?(Deprecated|Warning|Notice|Fatal error|Parse error):\s+(.*)$/', trim($line), $m)) {
            $where = str_contains($m[3], '/vendor/') ? 'vendor' : 'project';
            $summary['issues'][] = "[$where] $m[2]: " . str_replace('/app/', '', $m[3]);
        }
    }
    $summary['issues'] = array_slice(array_values(array_unique($summary['issues'])), 0, 30);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
