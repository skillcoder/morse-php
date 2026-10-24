<?php
// Summarise Rector's --output-format=json report.
// CLI: php summarize-rector.php <rector.json>  -> JSON; exit 0 no changes, 1 changes, 2 errors

$report = json_decode((string) @file_get_contents($argv[1] ?? ''), true);
if (!is_array($report)) {
    fwrite(STDERR, "rector report is missing or not JSON: " . ($argv[1] ?? '') . "\n");
    exit(2);
}

$rules = [];
$files = [];
foreach ($report['file_diffs'] ?? [] as $diff) {
    $applied = array_map(fn (string $rule) => substr(strrchr('\\' . $rule, '\\'), 1), $diff['applied_rectors'] ?? []);
    foreach ($applied as $rule) {
        $rules[$rule] = ($rules[$rule] ?? 0) + 1;
    }
    $files[] = ['file' => preg_replace('#^/app/#', '', $diff['file']), 'rules' => $applied];
}
arsort($rules);

$errors = array_map(
    fn (array $error) => ($error['file'] ?? '') . ': ' . ($error['message'] ?? ''),
    $report['errors'] ?? []
);

echo json_encode([
    'changed_files' => $report['totals']['changed_files'] ?? count($files),
    'errors' => count($errors),
    'rules' => $rules ?: new stdClass(), // always a JSON object
    'files' => array_slice($files, 0, 50),
    'error_messages' => array_slice($errors, 0, 20),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";

exit($errors ? 2 : ($files ? 1 : 0));
