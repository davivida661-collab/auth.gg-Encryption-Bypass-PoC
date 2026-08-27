# Auth.GG Encryption Bypass PoC

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Language](https://img.shields.io/badge/language-C%23%20%7C%20PHP-purple.svg)
![.NET](https://img.shields.io/badge/.NET-Framework-4.7.2-green.svg)

A proof-of-concept demonstrating the bypass of AES encryption within auth.gg authentication system. Due to poor implementation of AES encryption, all programs utilizing auth.gg—including obfuscated ones—are vulnerable to exploitation.

## ⚠️ Disclaimer

This repository is for **educational and security research purposes only**. No copyright has been infringed; the PoC was developed using clean-room design principles. No property of auth.gg was modified during this process.

## 📚 Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [C# Implementation](#c-implementation)
  - [C++ Implementation](#c-implementation-1)
- [Server Configuration](#server-configuration)
- [Security Analysis](#security-analysis)
- [Alternatives](#alternatives)
- [Credits](#credits)
- [License](#license)

## Overview

The auth.gg authentication system implements AES encryption with hardcoded keys transmitted in requests, making it possible to intercept and decrypt communications. This PoC demonstrates how to bypass these protections using:

- **HTTP Debugging**: Intercept and modify encrypted requests
- **SSL Key Replacement**: Hook cryptographic functions to substitute known keys
- **Server-side Decryption**: PHP-based servers that decrypt intercepted data

## How It Works

### Encryption Weakness

The core vulnerability lies in the implementation:

1. **Hardcoded Keys**: Encryption keys are sent with requests, not kept server-side
2. **Predictable IVs**: Initialization vectors follow patterns that can be exploited
3. **Weak Key Derivation**: Keys are derived from predictable sources

### Bypass Mechanism

The bypass works by:

1. **Intercepting Traffic**: Using HTTP debugger to capture encrypted requests
2. **Extracting Keys**: Retrieving the AES key from the request data
3. **Decrypting/Re-encrypting**: Modifying data with valid encryption
4. **Forwarding Requests**: Sending manipulated requests to auth.gg servers

## Project Structure

```
auth.gg-Encryption-Bypass-PoC/
├── cpp/
│   └── index.php                    # PHP server for C++ encryption
├── csharp/
│   ├── client/
│   │   ├── harmony-ssl-hook/        # C# client application
│   │   │   ├── Program.cs           # Main program with Harmony patching
│   │   │   ├── App.config           # .NET Framework configuration
│   │   │   ├── Properties/
│   │   │   │   └── AssemblyInfo.cs  # Assembly metadata
│   │   │   ├── harmony-ssl-hook.csproj  # Project file
│   │   │   └── packages.config      # NuGet packages
│   │   └── harmony-ssl-hook.sln     # Visual Studio solution
│   └── server/
│       └── index.php                # PHP server for C# encryption
├── LICENSE                          # MIT License
└── README.md                        # This file
```

## Requirements

### C# Client
- Windows OS
- .NET Framework 4.7.2 or later
- Visual Studio 2017 or later (for building)
- NuGet packages (automatically restored):
  - HarmonyX 2.10.1
  - Mono.Cecil 0.11.4
  - MonoMod.RuntimeDetour 22.3.23.4
  - MonoMod.Utils 22.3.23.4

### Server (PHP)
- PHP 7.0 or later
- OpenSSL extension enabled
- Web server (Apache/Nginx) or PHP built-in server

### HTTP Debugger
- [HTTPDebugger Pro](https://httpdebugger.com/download_pro.html) (trial or licensed)

## Installation

### C# Client

1. **Clone the Repository**
   ```bash
   git clone https://github.com/wnelson03/auth.gg-Encryption-Bypass-PoC.git
   cd auth.gg-Encryption-Bypass-PoC
   ```

2. **Restore NuGet Packages**
   - Open `csharp/client/harmony-ssl-hook.sln` in Visual Studio
   - Right-click solution → Restore NuGet Packages
   - Or use Package Manager Console:
     ```powershell
     Update-Package -reinstall
     ```

3. **Build the Solution**
   - Select **Release** configuration
   - Build → Build Solution (Ctrl+Shift+B)
   - Output: `csharp/client/bin/Release/harmony-ssl-hook.exe`

### Server

1. **Deploy PHP Files**
   - Copy `cpp/index.php` or `csharp/server/index.php` to your web server
   - Ensure OpenSSL extension is enabled in `php.ini`

2. **Configure Web Server**
   ```apache
   # Apache example
   <VirtualHost *:80>
       ServerName auth-proxy.example.com
       DocumentRoot /var/www/auth-proxy
       
       <Directory /var/www/auth-proxy>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. **Test Server**
   ```bash
   curl -X POST -d "test=1" http://your-server/index.php
   # Should return error message (no POST data)
   ```

## Usage

### C# Implementation

#### Step 1: Configure HTTP Debugger

1. Download and install [HTTPDebugger](https://httpdebugger.com/download_pro.html)
2. Import the settings file from the Releases section
3. Keep HTTPDebugger running in the background

#### Step 2: Run the Hook

**Option A: Drag-and-Drop**
- Drag your target `.exe` file onto `harmony-ssl-hook.exe`

**Option B: Command Line**
```bash
harmony-ssl-hook.exe "C:\path\to\target.exe"
```

**Option C: Interactive Mode**
- Run `harmony-ssl-hook.exe` without arguments
- Enter the path to the target executable when prompted

#### Step 3: Authenticate

- The hooked application will launch with SSL certificate verification disabled
- Enter any username, password, or license key
- The server will validate and return success

#### Important Notes

- **Version Mismatch**: If you see "Update available", the target expects a different version
  - Close HTTPDebugger temporarily while launching the target
  - Or modify the version string in `csharp/server/index.php`
- **DLL Dependencies**: If the target requires DLL files, place them in the same directory as `harmony-ssl-hook.exe`

### C++ Implementation

The C++ implementation uses a PHP-based decryption server:

1. **Deploy Server**: Upload `cpp/index.php` to your web server
2. **Configure Target**: Point the target application to your server URL
3. **Intercept Traffic**: Use HTTPDebugger to capture requests
4. **Decrypt Responses**: The server automatically decrypts and responds

## Server Configuration

### PHP Server Setup

Both server implementations (`cpp/index.php` and `csharp/server/index.php`) can be configured:

```php
// Configuration options
$CONFIG = [
    'expiry_days' => 5,           // Days until subscription expires
    'log_requests' => true,       // Log incoming requests
    'allowed_origins' => ['*'],   // CORS origins
];
```

### Environment Variables (Optional)

For production deployments, consider using environment variables:

```bash
export AUTH_PROXY_KEY="your-secret-key"
export AUTH_PROXY_EXPIRY="5"
```

### Free Hosting

You can host the PHP server for free at:
- [000webhost](https://www.000webhost.com/)
- [InfinityFree](https://infinityfree.net/)
- [AwardSpace](https://www.awardspace.com/)

## Security Analysis

### Vulnerabilities Demonstrated

1. **Client-Side Key Storage**: AES keys are transmitted in requests
2. **Weak Key Derivation**: Keys derived from predictable values
3. **No Certificate Pinning**: SSL verification can be bypassed
4. **Predictable IVs**: Initialization vectors follow patterns

### Affected Versions

- All versions using the vulnerable encryption implementation
- Programs with obfuscation are still affected (keys remain in memory)

### Mitigation Recommendations

For auth.gg developers:
- Implement server-side key generation and storage
- Use proper key derivation functions (PBKDF2, Argon2)
- Implement certificate pinning
- Use random, unpredictable IVs for each request

## Alternatives

### KeyAuth

[KeyAuth](https://keyauth.cc) is a privacy-focused alternative that:
- Hashes emails (in addition to passwords)
- Never transmits encryption keys in requests
- Uses open-source code for transparency
- Implements proper encryption practices

### c_auth

An open-source alternative available at [github.com/fingu/c_auth](https://github.com/fingu/c_auth)

## Credits

- **Author**: [William Nelson](https://github.com/wnelson03) - Founder of [KeyAuth](https://keyauth.cc)
- **Research**: [@CabboShiba](https://github.com/CabboShiba)
- **Tutorial Video**: [YouTube](https://youtu.be/LtiPOj6DuAg?t=36)
- **Community**: [KeyAuth Discord](https://discord.gg/keyauth)

### Acknowledgments

- Stack Overflow communities for cryptographic implementation examples
- Harmony library for .NET method patching
- HTTPDebugger team for debugging tools

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/improvement`)
5. Create a Pull Request

### Development Guidelines

- Maintain clean, well-commented code
- Follow existing style conventions
- Test changes thoroughly before submitting
- Update documentation as needed

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## ⚖️ Legal Notice

This software is provided for educational and security research purposes only. Users are responsible for ensuring they have proper authorization before testing on any system. The authors are not responsible for any misuse of this software.

---

**⚠️ Security Notice**: If you are a developer using auth.gg, we strongly recommend migrating to a more secure authentication solution that implements proper cryptographic practices.
