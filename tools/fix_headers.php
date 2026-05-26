<?php
/**
 * Fix "Cannot modify header information" across all pages.
 * For each file: pull init block to top, push header.php include to just
 * before the display/data section.
 */

$base = __DIR__ . '/../pages/';

// [filename, pageTitle, insert_header_before_this_string, auth_fn]
$jobs = [
    ['products.php',    "'สินค้าทั้งหมด'",       '// Filters',                 'requireLogin'],
    ['customers.php',   "'ฐานข้อมูลลูกค้า'",      '/* ── Filters',              'requireLogin'],
    ['orders.php',      "'ออเดอร์ / คำสั่งซื้อ'",  '// Filters',                 'requireLogin'],
    ['platforms.php',   "'แพลตฟอร์มขาย'",         '// Sales per platform',       'requireLogin'],
    ['promotions.php',  "'โปรโมชั่น'",             "?>\n",                       'requireLogin'],
    ['media.php',       "'คลังสื่อ'",              '// Filters',                 'requireLogin'],
    ['product-add.php', "'เพิ่ม / แก้ไขสินค้า'",  '// ─── HELPERS',             'requireLogin'],
    ['settings.php',    "'ตั้งค่าร้าน'",           null,                         'requireAdmin'],
    ['integrations.php','\'เชื่อมต่อระบบ\'',      null,                         'requireAdmin'],
    ['order-print.php', null,                       null,                         'requireLogin'],
];

foreach ($jobs as [$fn, $ptitle, $insertBefore, $auth]) {
    $path = $base . $fn;
    if (!file_exists($path)) { echo "SKIP (missing): $fn\n"; continue; }

    $src = file_get_contents($path);

    // Already fixed?
    $cPos = strpos($src, 'config/database.php');
    $hPos = strpos($src, 'includes/header.php');
    if ($cPos !== false && $hPos !== false && $cPos < $hPos) {
        $after = substr($src, $hPos + strlen('includes/header.php'));
        if (strpos($after, "header('Location:") === false) {
            echo "OK (skip): $fn\n";
            continue;
        }
    }

    // ── 1. Strip old init block ───────────────────────────────────────────────
    // Remove $pageTitle = ...;
    $src = preg_replace('/^\$pageTitle\s*=\s*[^\n]+;\n?/m', '', $src);
    // Remove require_once includes/header.php
    $src = str_replace("require_once __DIR__ . '/../includes/header.php';\n", '', $src);
    $src = str_replace("require_once __DIR__ . '/../includes/header.php';",  '', $src);
    // Remove first bare $pdo = getDB(); (the module-level one)
    $src = preg_replace('/^(\$pdo\s*=\s*getDB\s*\(\)\s*;[ \t]*\n)/m', '__PDO_REMOVED__', $src, 1);
    $src = str_replace('__PDO_REMOVED__', '', $src);
    // Remove existing config/database.php line
    $src = str_replace("require_once __DIR__ . '/../config/database.php';\n", '', $src);
    // Remove existing requireLogin/requireAdmin
    $src = preg_replace('/^require(?:Login|Admin)\s*\(\)\s*;\n/m', '', $src);

    // ── 2. Add new init block at the top ──────────────────────────────────────
    $init  = "require_once __DIR__ . '/../config/database.php';\n";
    $init .= "{$auth}();\n";
    $init .= "\$pdo = getDB();\n";
    $src   = "<?php\n" . $init . ltrim(substr($src, strpos($src, '<?php') + 5));

    // ── 3. Insert $pageTitle + header.php before the display section ──────────
    if ($ptitle === null) {
        // order-print.php — no pageTitle needed, header.php not used
        // (it's a standalone print page)
        file_put_contents($path, $src);
        echo "FIXED (print page): $fn\n";
        continue;
    }

    $headerBlock = "\n\$pageTitle = {$ptitle};\nrequire_once __DIR__ . '/../includes/header.php';\n";

    if ($insertBefore !== null && strpos($src, $insertBefore) !== false) {
        $pos = strpos($src, $insertBefore);
        $src = substr($src, 0, $pos) . $headerBlock . "\n" . substr($src, $pos);
    } else {
        // Fallback for settings.php / integrations.php:
        // find end of last exit; block — insert after the closing }
        $matches = [];
        preg_match_all('/\n\}\s*\n(?!.*?\{)/s', $src, $m, PREG_OFFSET_CAPTURE);
        // Alternative: find first blank line after last header('Location')
        $lastLoc = max(
            strrpos($src, "header('Location:"),
            strrpos($src, 'header("Location:')
        );
        if ($lastLoc !== false) {
            // Find the next "\n}\n" after the last redirect
            $segment = substr($src, $lastLoc);
            $relPos = strpos($segment, "\n}\n");
            if ($relPos !== false) {
                $pos = $lastLoc + $relPos + 3; // after "\n}\n"
                $src = substr($src, 0, $pos) . $headerBlock . substr($src, $pos);
            } else {
                $src .= $headerBlock;
            }
        } else {
            $src .= $headerBlock;
        }
    }

    // ── 4. Tidy up multiple blank lines ──────────────────────────────────────
    $src = preg_replace('/\n{4,}/', "\n\n\n", $src);

    file_put_contents($path, $src);
    echo "FIXED: $fn\n";
}

// ── Syntax check all files ────────────────────────────────────────────────────
echo "\n--- Syntax Check ---\n";
foreach ($jobs as [$fn]) {
    $path = $base . $fn;
    if (!file_exists($path)) continue;
    $out = trim(shell_exec('/c/xampp/php/php.exe -l "' . $path . '" 2>&1'));
    echo $out . "\n";
}
echo "\nDone.\n";
