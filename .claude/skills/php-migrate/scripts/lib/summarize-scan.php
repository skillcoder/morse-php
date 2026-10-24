<?php
// Merge PHPCompatibility (phpcs JSON) and PHPStan (JSON) reports, apply waivers, group findings.
// CLI: php summarize-scan.php <phpcompat.json> <phpstan.json> <waivers.json>
//      -> JSON; exit 0 when nothing remains after waivers, 1 otherwise, 2 when a report is unreadable
//
// waivers.json: {"waivers": [{"tool": "phpcompat"|"phpstan", "id": "<sniff code or identifier>",
//                             "file": "<optional relative path>", "line": <optional>, "reason": "..."}]}

[$_, $compatFile, $stanFile, $waiverFile] = $argv + [null, '', '', ''];

function load(string $file): ?array
{
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    return is_array($data) ? $data : null;
}

$waivers = load($waiverFile)['waivers'] ?? [];

function waived(array $waivers, string $tool, string $id, string $file, int $line): ?string
{
    foreach ($waivers as $waiver) {
        if (($waiver['tool'] ?? '') === $tool && ($waiver['id'] ?? '') === $id
            && (!isset($waiver['file']) || $waiver['file'] === $file)
            && (!isset($waiver['line']) || (int) $waiver['line'] === $line)) {
            return $waiver['reason'] ?? 'waived';
        }
    }
    return null;
}

function group(array $findings): array|stdClass
{
    $grouped = [];
    foreach ($findings as [$id, $location, $message]) {
        $grouped[$id]['count'] = ($grouped[$id]['count'] ?? 0) + 1;
        if (count($grouped[$id]['examples'] ?? []) < 5) {
            $grouped[$id]['examples'][] = "$location $message";
        }
    }
    uasort($grouped, fn ($a, $b) => $b['count'] <=> $a['count']);
    return $grouped ?: new stdClass(); // always a JSON object
}

$result = ['phpcompat' => null, 'phpstan' => null, 'waived' => [], 'remaining' => 0];
$unreadable = false;

$compat = load($compatFile);
if ($compat === null) {
    $unreadable = true;
} else {
    $findings = [];
    foreach ($compat['files'] ?? [] as $path => $file) {
        $path = preg_replace('#^/app/#', '', $path);
        foreach ($file['messages'] ?? [] as $msg) {
            $id = $msg['source'] ?? 'unknown';
            $line = (int) ($msg['line'] ?? 0);
            if (($reason = waived($waivers, 'phpcompat', $id, $path, $line)) !== null) {
                $result['waived'][] = "phpcompat $id $path:$line ($reason)";
                continue;
            }
            $findings[] = [$id, "$path:$line", strtolower($msg['type'] ?? '') . ': ' . ($msg['message'] ?? '')];
        }
    }
    $result['phpcompat'] = ['total' => count($findings), 'by_source' => group($findings)];
    $result['remaining'] += count($findings);
}

$stan = load($stanFile);
if ($stan === null) {
    $unreadable = true;
} else {
    $findings = [];
    foreach ($stan['files'] ?? [] as $path => $file) {
        $path = preg_replace('#^/app/#', '', $path);
        foreach ($file['messages'] ?? [] as $msg) {
            $id = $msg['identifier'] ?? 'unknown';
            $line = (int) ($msg['line'] ?? 0);
            if (($reason = waived($waivers, 'phpstan', $id, $path, $line)) !== null) {
                $result['waived'][] = "phpstan $id $path:$line ($reason)";
                continue;
            }
            $findings[] = [$id, "$path:$line", $msg['message'] ?? ''];
        }
    }
    foreach ($stan['errors'] ?? [] as $general) {
        $findings[] = ['general', '-', is_string($general) ? $general : json_encode($general)];
    }
    $result['phpstan'] = ['total' => count($findings), 'by_identifier' => group($findings)];
    $result['remaining'] += count($findings);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
exit($unreadable ? 2 : ($result['remaining'] > 0 ? 1 : 0));
