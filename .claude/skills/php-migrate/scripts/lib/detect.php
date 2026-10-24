<?php
// Collect the signals that tell which PHP version a project currently targets.
// Runs in the official php:8.5-cli image with the project mounted read-only.
//
// CLI: php detect.php <project-root>   -> JSON

$root = rtrim($argv[1] ?? '/app', '/');
$signals = [];

function add_signal(array &$signals, string $source, string $value): void
{
    $signals[] = ['source' => $source, 'value' => trim($value)];
}

function read_json(string $file): ?array
{
    return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
}

// Lowest PHP version a constraint or version list allows ("^7.4 || ^8.0" -> 7.4).
function lowest_version(string $text): ?string
{
    if (!preg_match_all('/(?<![\d.])(\d+)\.(\d+)(?:\.\d+)?/', $text, $m, PREG_SET_ORDER)) {
        return null;
    }
    $versions = array_map(fn ($match) => (int) $match[1] * 100 + (int) $match[2], $m);
    $versions = array_filter($versions, fn ($id) => $id >= 500 && $id < 1000);
    if (!$versions) {
        return null;
    }
    $lowest = min($versions);
    return intdiv($lowest, 100) . '.' . ($lowest % 100);
}

$composer = read_json("$root/composer.json");
$lock = read_json("$root/composer.lock");
$project = [
    'composer_json' => $composer !== null,
    'composer_lock' => $lock !== null,
    'vendor' => is_dir("$root/vendor"),
    'require_php' => $composer['require']['php'] ?? null,
    'platform_php' => $composer['config']['platform']['php'] ?? null,
    'phpunit_constraint' => $composer['require-dev']['phpunit/phpunit'] ?? $composer['require']['phpunit/phpunit'] ?? null,
    'version_field' => $composer['version'] ?? null,
    'phpunit_config' => null,
    'phpunit_locked' => null,
    'lock_composer_plugin_api' => $lock['plugin-api-version'] ?? null,
];

if ($project['require_php'] !== null) {
    add_signal($signals, 'composer.json require.php', $project['require_php']);
}
if ($project['platform_php'] !== null) {
    add_signal($signals, 'composer.json config.platform.php', $project['platform_php']);
}
if ($lock !== null) {
    if (isset($lock['platform']['php'])) {
        add_signal($signals, 'composer.lock platform.php', $lock['platform']['php']);
    }
    if (isset($lock['platform-overrides']['php'])) {
        add_signal($signals, 'composer.lock platform-overrides.php', $lock['platform-overrides']['php']);
    }
    foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
        if ($package['name'] === 'phpunit/phpunit') {
            $project['phpunit_locked'] = $package['version'];
        }
    }
}

foreach (['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml'] as $config) {
    if (is_file("$root/$config")) {
        $project['phpunit_config'] = $config;
        break;
    }
}

// CI and container files: pick version-looking values on php-related keys and php: images.
$ciFiles = array_merge(
    glob("$root/.travis.yml") ?: [],
    glob("$root/.github/workflows/*.y*ml") ?: [],
    glob("$root/.gitlab-ci.yml") ?: [],
    glob("$root/bitbucket-pipelines.yml") ?: [],
    glob("$root/.circleci/config.yml") ?: [],
    glob("$root/Dockerfile*") ?: [],
    glob("$root/{docker-,}compose*.y*ml", GLOB_BRACE) ?: [],
);
foreach ($ciFiles as $file) {
    $name = substr($file, strlen($root) + 1);
    $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
    $inPhpList = false;
    foreach ($lines as $line) {
        if (preg_match('/\bphp:(\d+\.\d+(?:\.\d+)?)/i', $line, $m)) {
            add_signal($signals, "$name image", $m[1]);
        }
        if (preg_match('/^\s*-?\s*php[\w-]*\s*:\s*(.*)$/i', $line, $m)) {
            $inPhpList = trim($m[1]) === '';
            if (preg_match_all('/(?<![\d.])\d+\.\d+(?:\.\d+)?/', $m[1], $versions)) {
                foreach ($versions[0] as $version) {
                    add_signal($signals, "$name php", $version);
                }
            }
            continue;
        }
        if ($inPhpList && preg_match('/^\s*-\s*[\'"]?(\d+\.\d+(?:\.\d+)?|nightly|hhvm)/i', $line, $m)) {
            add_signal($signals, "$name php", $m[1]);
            continue;
        }
        if ($inPhpList && trim($line) !== '' && !preg_match('/^\s*#/', $line)) {
            $inPhpList = false;
        }
    }
}

foreach (['.php-version' => '/(\d+\.\d+(?:\.\d+)?)/', '.tool-versions' => '/^php\s+(\d+\.\d+(?:\.\d+)?)/m'] as $file => $pattern) {
    if (is_file("$root/$file") && preg_match($pattern, (string) file_get_contents("$root/$file"), $m)) {
        add_signal($signals, $file, $m[1]);
    }
}

$lowest = [];
foreach ($signals as $signal) {
    if (($version = lowest_version($signal['value'])) !== null) {
        [$major, $minor] = array_map('intval', explode('.', $version));
        $lowest[$version] = $major * 100 + $minor;
    }
}
asort($lowest);

echo json_encode([
    'project' => $project,
    'signals' => $signals,
    'versions_seen' => array_keys($lowest),
    'suggested_current' => array_key_first($lowest),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
