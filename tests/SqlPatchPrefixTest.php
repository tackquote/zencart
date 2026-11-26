<?php
/**
 * Every table in zc_install/*.sql gets the store's table prefix when the file goes
 * through Zen Cart's Tools > Install SQL Patches.
 *
 * That tool (admin/sqlpatch.php, executeSql()) does not parse SQL. It trims each LINE,
 * skips lines starting with '#' or '-', and adds the prefix only when the line STARTS
 * with a keyword it knows. A table named in the middle of any other line (for example
 * `AND NOT EXISTS (SELECT 1 FROM admin_pages ...)`) stays unprefixed, and on a store
 * with DB_PREFIX set MySQL answers error 1146 and the row is never written.
 *
 * sqlPatchPrefix() below ports the rewrite rules of executeSql() that can apply to
 * these files, read from github.com/zencart/zencart admin/sqlpatch.php at v1.5.7
 * (lines 245-262), v1.5.7d, v1.5.8a (251-270) and v2.2.2. The `FROM ` rewrite is
 * case-SENSITIVE `str_replace` in v1.5.7 (and v1.5.6c) and `str_ireplace` from v1.5.7a
 * on; the port uses the stricter v1.5.7 form, the oldest release this module supports,
 * so a lower-case `from` in these files fails here. Statements the tool would refuse or check against the database (DROP, CREATE,
 * ALTER, RENAME ...) are reported as unsupported, so a new kind of statement fails
 * here instead of passing unchecked.
 *
 * Run: php tests/SqlPatchPrefixTest.php   (exit 0 = all passed)
 */

$passed = 0;
$failures = array();

function check($label, $condition)
{
    global $passed, $failures;
    if ($condition) {
        $passed++;
        echo '  ok   ' . $label . PHP_EOL;
    } else {
        $failures[] = $label;
        echo '  FAIL ' . $label . PHP_EOL;
    }
}

/**
 * Port of executeSql()'s per-line prefixing. Returns the statements as the tool would
 * send them to MySQL, or throws on a line kind this port does not cover.
 *
 * @return string[]
 */
function sqlPatchPrefix($sql, $prefix)
{
    $statements = array();
    $newline = '';
    foreach (preg_split('/\r?\n/', $sql) as $line) {
        $line = str_replace('`', '', trim($line));
        if ($line === '' || $line[0] === '#' || $line[0] === '-') {
            continue;
        }
        $upper = strtoupper($line);
        foreach (array('DROP ', 'CREATE ', 'ALTER TABLE ', 'RENAME TABLE ', 'TRUNCATE ', 'REPLACE INTO ', 'INSERT IGNORE INTO ', 'UPDATE ') as $unsupported) {
            if (strpos($upper, $unsupported) === 0) {
                throw new RuntimeException('sqlPatchPrefix() does not port this statement kind: ' . $line);
            }
        }
        if (substr($upper, 0, 12) === 'INSERT INTO ') {
            $line = 'INSERT INTO ' . $prefix . ltrim(substr($line, 12));
        } elseif (substr($upper, 0, 12) === 'DELETE FROM ') {
            $line = 'DELETE FROM ' . $prefix . ltrim(substr($line, 12));
        } elseif (substr($upper, 0, 7) === 'SELECT ' && substr_count($upper, 'FROM ') > 0) {
            $line = str_replace('FROM ', 'FROM ' . $prefix, $line);
        } elseif (substr($upper, 0, 11) === 'INNER JOIN ') {
            $line = 'INNER JOIN ' . $prefix . ltrim(substr($line, 11));
        } elseif (substr($upper, 0, 11) === 'CROSS JOIN ') {
            $line = 'CROSS JOIN ' . $prefix . ltrim(substr($line, 11));
        } elseif (substr($upper, 0, 10) === 'LEFT JOIN ') {
            $line = 'LEFT JOIN ' . $prefix . ltrim(substr($line, 10));
        } elseif ($upper === 'FROM INFORMATION_SCHEMA.COLUMNS') {
            // left alone by the tool
        } elseif (substr($upper, 0, 5) === 'FROM ') {
            if (substr_count($line, ',') > 0) {
                $tables = explode(',', ltrim(substr($line, 5)));
                $line = 'FROM ';
                foreach ($tables as $t) {
                    $line .= $prefix . ltrim($t) . ',';
                }
                $line = rtrim($line, ',');
            } else {
                $line = str_replace('FROM ', 'FROM ' . $prefix, $line);
            }
        }
        $newline .= $line . ' ';
        if (substr($line, -1) === ';') {
            $statements[] = rtrim($newline);
            $newline = '';
        }
    }
    if (trim($newline) !== '') {
        $statements[] = trim($newline);
    }

    return $statements;
}

/**
 * Every table a statement names: the word after FROM, INTO, JOIN, UPDATE or TABLE,
 * outside string literals.
 *
 * @return string[]
 */
function tableReferences($statement)
{
    $code = preg_replace("/'(?:[^']|'')*'/", "''", $statement);
    preg_match_all('/\b(?:FROM|INTO|JOIN|UPDATE|TABLE)\s+([A-Za-z_][A-Za-z0-9_.]*)/i', $code, $m);

    return $m[1];
}

$prefix = 'zen_';
$files = glob(__DIR__ . '/../zc_install/*.sql');
check('found the SQL files', count($files) >= 4);

foreach ($files as $file) {
    $name = 'zc_install/' . basename($file);
    try {
        $statements = sqlPatchPrefix(file_get_contents($file), $prefix);
    } catch (RuntimeException $e) {
        check("$name: " . $e->getMessage(), false);
        continue;
    }
    $refs = 0;
    $unprefixed = array();
    foreach ($statements as $statement) {
        foreach (tableReferences($statement) as $table) {
            $refs++;
            if (strpos($table, $prefix) !== 0) {
                $unprefixed[] = $table;
            }
        }
    }
    check("$name: every table reference carries the prefix after Install SQL Patches"
        . ($unprefixed ? ' (unprefixed: ' . implode(', ', array_unique($unprefixed)) . ')' : ''),
        $refs > 0 && $unprefixed === array());
}

// The port itself: a table in the middle of a line is NOT prefixed, exactly as the
// tool behaves. If this stops failing, the port has drifted from executeSql().
$bad = sqlPatchPrefix("INSERT INTO t (a)\nSELECT 1 FROM g\nWHERE 1\n  AND NOT EXISTS (SELECT 1 FROM admin_pages ap WHERE 1);", $prefix);
$badRefs = tableReferences($bad[0]);
check('port: a mid-line subquery table stays unprefixed, as in sqlpatch.php',
    in_array('admin_pages', $badRefs, true) && in_array('zen_t', $badRefs, true) && in_array('zen_g', $badRefs, true));
$lower = sqlPatchPrefix("INSERT INTO t (a)\nSELECT 1 from g;", $prefix);
check('port: a lower-case "from" is not prefixed, as in v1.5.7 (str_replace)',
    in_array('g', tableReferences($lower[0]), true));
check('port: string literals are not read as tables',
    tableReferences("INSERT INTO zen_x (a) VALUES ('copy FROM here INTO there');") === array('zen_x'));

echo 'passed: ' . $passed . ', failed: ' . count($failures) . PHP_EOL;
exit(empty($failures) ? 0 : 1);
