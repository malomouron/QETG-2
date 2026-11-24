<?php

declare(strict_types=1);
$coverageDirectory = getenv('QETG_COVERAGE_DIR') ?: ($argv[1] ?? '');
$files = glob(rtrim($coverageDirectory, '/') . '/*.ser') ?: array();
$merged = array();
foreach ($files as $file) {
    $data = unserialize((string) file_get_contents($file), array('allowed_classes' => false));
    foreach ((array) $data as $path => $lines) {
        $normalized = str_replace('\\', '/', $path);
        if (!str_contains($normalized, '/var/www/html/src/')) continue;
        foreach ($lines as $line => $status) {
            if ($status === -2) continue;
            $merged[$normalized][$line] = (($merged[$normalized][$line] ?? 0) === 1 || $status === 1) ? 1 : -1;
        }
    }
}
$stats = array();
foreach ($merged as $path => $lines) $stats[$path] = [count(array_filter($lines, static fn (int $value): bool => $value === 1)), count($lines)];
$totals = array_fill_keys(array('all', 'Application', 'Domain', 'Infrastructure', 'Presentation'), array(0, 0));
foreach ($stats as $path => [$hit, $total]) {
    $totals['all'][0] += $hit;
    $totals['all'][1] += $total;
    foreach (array('Application', 'Domain', 'Infrastructure', 'Presentation') as $layer) {
        if (str_contains($path, '/src/' . $layer . '/')) {
            $totals[$layer][0] += $hit;
            $totals[$layer][1] += $total;
            break;
        }
    }
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/var/www/html/src', FilesystemIterator::SKIP_DOTS));
$source = array();
foreach ($iterator as $item) if ($item->isFile() && $item->getExtension() === 'php') $source[] = str_replace('\\', '/', $item->getPathname());
$loaded = array_fill_keys(array_keys($stats), true);
$missing = array_values(array_filter($source, static fn (string $path): bool => !isset($loaded[$path])));
printf("Runs: %d; loaded files: %d/%d\n", count($files), count($stats), count($source));
foreach ($totals as $name => [$hit, $total]) printf("%-16s %5d/%-5d %6.2f%%\n", $name, $hit, $total, $total ? 100 * $hit / $total : 0);
uasort($stats, static fn (array $a, array $b): int => ($a[0] / max(1, $a[1])) <=> ($b[0] / max(1, $b[1])));
echo "\nLowest line coverage files:\n";
$displayed = 0;
foreach ($stats as $path => [$hit, $total]) {
    printf("%5d/%-5d %6.2f%% %s\n", $hit, $total, $total ? 100 * $hit / $total : 0, str_replace('/var/www/html/', '', $path));
    if (++$displayed === 15) break;
}
echo "UNLOADED:\n"; foreach ($missing as $path) echo str_replace('/var/www/html/', '', $path), "\n";
if ($missing !== array()) {
    fwrite(STDERR, "Coverage report incomplete: at least one production source file has no execution data.\n");
    exit(1);
}
$minimum = getenv('QETG_MIN_LINE_COVERAGE');
$minimum = $minimum === false ? '0' : $minimum;
if (!is_numeric($minimum) || (float) $minimum < 0 || (float) $minimum > 100) {
    fwrite(STDERR, "QETG_MIN_LINE_COVERAGE must be a percentage between 0 and 100.\n");
    exit(2);
}
[$coveredLines, $executableLines] = $totals['all'];
if ($executableLines === 0 || 100 * $coveredLines < (float) $minimum * $executableLines) {
    fwrite(STDERR, sprintf("No executable lines measured or coverage below %.2f%%.\n", (float) $minimum));
    exit(1);
}
