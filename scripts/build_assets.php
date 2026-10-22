<?php
/**
 * Build assets: bundle CSS and JS for production
 * Usage: php scripts/build_assets.php [--minify]
 */

$minify = in_array('--minify', $argv);

$root = __DIR__ . '/..';
$cssSrc = $root . '/public/assets/css';
$cssDist = $root . '/public/assets/dist/css';
$jsSrc = $root . '/public/assets/js';
$jsDist = $root . '/public/assets/dist/js';

@mkdir($cssDist, 0755, true);
@mkdir($jsDist, 0755, true);

/* ---------- CSS bundling ---------- */

function readCssFiles(string $dir, string $manifest): array {
    $content = file_get_contents($dir . '/' . $manifest);
    if ($content === false) {
        return [];
    }

    $files = [];
    preg_match_all('/@import\s+"([^"]+)"\s+layer\((\w+)\);/', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $file = $dir . '/' . $m[1];
        $layer = $m[2];
        if (is_file($file)) {
            $files[] = [
                'path' => $file,
                'layer' => $layer,
                'content' => file_get_contents($file),
            ];
        }
    }

    return $files;
}

function minifyCss(string $css): string {
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
    $css = preg_replace('/;}/', '}', $css);
    return trim($css);
}

$cssFiles = readCssFiles($cssSrc, 'app.css');

$bundles = [
    'tokens' => [],
    'base' => [],
    'layout' => [],
    'atoms' => [],
    'molecules' => [],
    'organisms' => [],
    'pages' => [],
    'utilities' => [],
];

foreach ($cssFiles as $f) {
    if (isset($bundles[$f['layer']])) {
        $bundles[$f['layer']][] = $f['content'];
    }
}

$layerOrder = ['tokens', 'base', 'layout', 'atoms', 'molecules', 'organisms', 'pages', 'utilities'];
$output = [];

foreach ($layerOrder as $layer) {
    if (!empty($bundles[$layer])) {
        $output[] = "@layer $layer {";
        $output[] = implode("\n", $bundles[$layer]);
        $output[] = "}";
    }
}

$bundled = implode("\n", $output);

if ($minify) {
    $bundled = minifyCss($bundled);
}

$distFile = $cssDist . '/app.min.css';
file_put_contents($distFile, $bundled);

echo "CSS: $distFile (" . number_format(strlen($bundled)) . " bytes)\n";

/* ---------- JS bundling (placeholder for future) ---------- */

$jsFiles = glob($jsSrc . '/*.js');
if ($jsFiles) {
    $jsOutput = [];
    foreach ($jsFiles as $f) {
        $jsOutput[] = file_get_contents($f);
    }
    $jsBundled = implode("\n", $jsOutput);
    if ($minify) {
        $jsBundled = preg_replace('!/\*.*?\*/!s', '', $jsBundled);
        $jsBundled = preg_replace('/\s+/', ' ', $jsBundled);
    }
    $jsDistFile = $jsDist . '/app.min.js';
    file_put_contents($jsDistFile, $jsBundled);
    echo "JS: $jsDistFile (" . number_format(strlen($jsBundled)) . " bytes)\n";
} else {
    echo "JS: no source files found (skipped)\n";
}

echo "Build complete.\n";