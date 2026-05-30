<?php
/**
 * BabyKawaii — Full database backup (.sql download)
 * ดาวน์โหลดสำรองทั้งฐานข้อมูลเป็นไฟล์ .sql (โครงสร้าง + ข้อมูล)
 * ใช้ PHP/PDO สร้าง dump เอง ไม่ต้องพึ่ง mysqldump บนเครื่อง
 */
require_once __DIR__ . '/../config/database.php';
requireAdmin();              // GET → csrf_verify เป็น no-op, ดาวน์โหลดได้ตามปกติ

$pdo = getDB();
@set_time_limit(0);

$stamp    = date('Ymd-His');
$filename = "babykawaii-backup-{$stamp}.sql";

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
while (ob_get_level() > 0) { ob_end_flush(); }

function out(string $s): void { echo $s; @flush(); }

out("-- BabyKawaii Database Backup\n");
out("-- Generated: " . date('Y-m-d H:i:s') . " (" . DB_NAME . ")\n");
out("-- ----------------------------------------------------\n");
out("SET NAMES utf8mb4;\n");
out("SET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $tq = "`" . str_replace("`", "``", $table) . "`";

    // โครงสร้าง
    $create = $pdo->query("SHOW CREATE TABLE {$tq}")->fetch(PDO::FETCH_ASSOC);
    $createSql = $create['Create Table'] ?? ($create['Create View'] ?? '');
    out("-- ----------------------------\n");
    out("-- Table: {$table}\n");
    out("-- ----------------------------\n");
    out("DROP TABLE IF EXISTS {$tq};\n");
    out($createSql . ";\n\n");

    // ข้อมูล (ทีละ batch กันใช้ RAM เกิน)
    $count = (int)$pdo->query("SELECT COUNT(*) FROM {$tq}")->fetchColumn();
    if ($count === 0) { out("\n"); continue; }

    $stmt = $pdo->query("SELECT * FROM {$tq}");
    $batch = [];
    $batchSize = 100;
    $colsLine = null;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($colsLine === null) {
            $colsLine = '(' . implode(',', array_map(fn($c) => "`" . str_replace("`", "``", $c) . "`", array_keys($row))) . ')';
        }
        $vals = array_map(function ($v) use ($pdo) {
            if ($v === null) return 'NULL';
            return $pdo->quote((string)$v);
        }, array_values($row));
        $batch[] = '(' . implode(',', $vals) . ')';

        if (count($batch) >= $batchSize) {
            out("INSERT INTO {$tq} {$colsLine} VALUES\n" . implode(",\n", $batch) . ";\n");
            $batch = [];
        }
    }
    if ($batch) {
        out("INSERT INTO {$tq} {$colsLine} VALUES\n" . implode(",\n", $batch) . ";\n");
    }
    out("\n");
}

out("SET FOREIGN_KEY_CHECKS=1;\n");
out("-- End of backup\n");
exit;
