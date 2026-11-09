<?php
/*
 * QA tools – shared helpers (command line only, never run from the browser).
 * Used by backup.php, restore.php, compare.php and stress_test.php.
 *
 * Paths (XAMPP layout):  <XAMPP>\htdocs\LostFoundTracker\tools\   <- these scripts
 *                        <XAMPP>\mysql\bin\mysqldump.exe, mysql.exe
 *                        <XAMPP>\backups\                         <- backups (OUTSIDE htdocs,
 *                                                                    so never downloadable – Phase 4 Table 26)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Africa/Johannesburg');
require_once __DIR__ . '/../config/db_settings.php';   // DB_HOST, DB_NAME, DB_USER, DB_PASS, BASE_URL

const SITE_DIR  = __DIR__ . '/..';
define('XAMPP_DIR', realpath(__DIR__ . '/../../..') ?: __DIR__ . '/../../..');
define('BACKUP_DIR', XAMPP_DIR . DIRECTORY_SEPARATOR . 'backups');
define('RESULTS_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'results');
const RESTORE_DB = 'lost_found_restore';   // separate database for restore tests (Table 26)

function out(string $s = ''): void
{
    echo $s, PHP_EOL;
    $GLOBALS['__log'][] = $s;
}

function head(string $s): void
{
    out('');
    out(str_repeat('=', 72));
    out($s);
    out(str_repeat('=', 72));
}

function ask(string $q, string $default = ''): string
{
    echo $q . ($default !== '' ? " [$default]" : '') . ': ';
    $a = trim((string) fgets(STDIN));
    return $a === '' ? $default : $a;
}

function ask_hidden(string $q): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        // NOTE-10: hide the password on Windows (PowerShell reads it as a secure string, no echo)
        echo $q . ' (hidden while typing): ';
        $ps = '$s = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))';
        $a = shell_exec('powershell -NoProfile -Command "' . $ps . '"');
        if (is_string($a) && trim($a) !== '') return rtrim($a, "\r\n");
        echo PHP_EOL . '(Could not hide the password – it will be visible.) ' . $q . ': ';
        return trim((string) fgets(STDIN));
    }
    echo $q . ': ';
    system('stty -echo 2>/dev/null');
    $a = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo PHP_EOL;
    return $a;
}

function fail(string $s): never
{
    out('ERROR: ' . $s);
    save_log('error');
    exit(1);
}

/** Saves everything printed so far to tools/results/<name>_<date>.txt and returns the path. */
function save_log(string $name): string
{
    if (!is_dir(RESULTS_DIR)) mkdir(RESULTS_DIR, 0775, true);
    $file = RESULTS_DIR . DIRECTORY_SEPARATOR . $name . '_' . date('Y-m-d_His') . '.txt';
    file_put_contents($file, implode(PHP_EOL, $GLOBALS['__log'] ?? []) . PHP_EOL);
    return $file;
}

function pdo_for(?string $dbName): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ($dbName ? ';dbname=' . $dbName : '') . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function mysql_bin(string $tool): string
{
    $exe = XAMPP_DIR . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $tool . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    if (is_file($exe)) return $exe;
    return $tool;   // fall back to PATH
}

/** Credentials for mysqldump/mysql (password via environment, never on the command line). */
function mysql_args(): array
{
    if (DB_PASS !== '') putenv('MYSQL_PWD=' . DB_PASS);
    return ['--host=' . DB_HOST, '--port=' . (int) DB_PORT, '--user=' . DB_USER];
}

/**
 * Runs a program WITHOUT a shell (no quoting problems with spaces in Windows paths).
 * $stdin / $stdout may be file paths. Returns [exit code, stderr text].
 */
function run(array $cmd, ?string $stdin = null, ?string $stdout = null): array
{
    $spec = [
        0 => $stdin !== null ? ['file', $stdin, 'r'] : ['pipe', 'r'],
        1 => $stdout !== null ? ['file', $stdout, 'w'] : ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $p = proc_open($cmd, $spec, $pipes);
    if (!is_resource($p)) return [127, 'could not start ' . $cmd[0]];
    if ($stdin === null) fclose($pipes[0]);
    $outText = $stdout === null ? stream_get_contents($pipes[1]) : '';
    if ($stdout === null) fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($p);
    return [$code, trim($err . $outText)];
}

function copy_dir(string $from, string $to): int
{
    if (!is_dir($to)) mkdir($to, 0775, true);
    $n = 0;
    foreach (is_dir($from) ? scandir($from) : [] as $f) {
        if ($f === '.' || $f === '..' || !is_file("$from/$f")) continue;
        copy("$from/$f", "$to/$f");
        $n++;
    }
    return $n;
}

/** Row count and content fingerprint of every table, so two databases can be compared exactly. */
function db_fingerprint(PDO $pdo): array
{
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
    $result = [];
    foreach ($tables as [$t]) {
        $pk = $pdo->query("SHOW KEYS FROM `$t` WHERE Key_name = 'PRIMARY'")->fetchAll();
        $order = $pk ? implode(', ', array_map(fn ($k) => '`' . $k['Column_name'] . '`', $pk)) : '1';
        $hash = hash_init('sha256');
        $n = 0;
        foreach ($pdo->query("SELECT * FROM `$t` ORDER BY $order", PDO::FETCH_NUM) as $row) {
            hash_update($hash, json_encode($row) . "\n");
            $n++;
        }
        $result[$t] = ['rows' => $n, 'hash' => substr(hash_final($hash), 0, 16)];
    }
    ksort($result);
    return $result;
}

function photo_fingerprint(): array
{
    $dir = SITE_DIR . '/uploads/item_photos';
    $files = is_dir($dir) ? array_values(array_filter(scandir($dir), fn ($f) => is_file("$dir/$f") && $f[0] !== '.')) : [];
    sort($files);
    $h = hash_init('sha256');
    foreach ($files as $f) hash_update($h, $f . ':' . hash_file('sha256', "$dir/$f"));
    return ['files' => count($files), 'hash' => substr(hash_final($h), 0, 16)];
}
