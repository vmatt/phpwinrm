# PHP WinRM Client

## Purpose

This PHP script provides a lightweight client for executing commands on Windows machines using the Windows Remote Management (WinRM) protocol. It allows you to run commands remotely on Windows systems from a PHP application, which can be useful for various system administration and automation tasks.

## Requirements
- PHP 8.0+ (might work with older versions, haven't tested)
- php-curl
   - _no need for SoapClient or any other fancy HTTP libs_

## Features

- Execute commands on remote Windows machines
- Lightweight and easy to integrate into PHP applications
- Uses Basic authentication for simplicity

## Disclaimer
This script is provided as-is, without any warranties or guarantees. Use at your own risk and ensure you comply with all relevant security policies and regulations in your environment.
> **IMPORTANT:** This script uses Basic authentication, which transmits credentials in base64-encoded format. This method is not secure over unencrypted connections and can be easily intercepted on the network.

- **Do not use this script over untrusted networks without proper encryption (e.g., HTTPS).**
- **Use this script only in secure, isolated environments where network traffic cannot be sniffed.**
- Consider using more secure authentication methods for production environments.

## Setup Instructions

### Enabling WinRM on the Windows Machine

1. Open PowerShell as Administrator on the target Windows machine.
2. Run the following command to enable WinRM:
   ```powershell
   winrm quickconfig
   ```
3. Answer 'Y' to any prompts.

### Configuring Basic Authentication

#### Through the UI (Windows 10/Server 2016+)

1. Open the "Local Group Policy Editor" (run `gpedit.msc`).
2. Navigate to: Computer Configuration > Administrative Templates > Windows Components > Windows Remote Management (WinRM) > WinRM Service.
3. Double-click on "Allow Basic authentication".
4. Select "Enabled" and click "OK".
5. Restart the WinRM service by running in PowerShell:
   ```powershell
   Restart-Service WinRM
   ```

#### Through Group Policy (for Active Directory environments)

1. Open the Group Policy Management Console.
2. Create a new GPO or edit an existing one.
3. Navigate to: Computer Configuration > Policies > Administrative Templates > Windows Components > Windows Remote Management (WinRM) > WinRM Service.
4. Enable the "Allow Basic authentication" setting.
5. Link the GPO to the appropriate Organizational Unit (OU) containing the target machines.
6. Force a Group Policy update on the target machines or wait for the next automatic update.

### Firewall Configuration

Ensure that WinRM traffic is allowed through the Windows Firewall:

1. Open Windows Firewall with Advanced Security.
2. Create a new Inbound Rule.
3. Allow TCP port 5985 (for HTTP)
4. Specify Remote IP Address for better security

## Usage Guide

1. Include the `WinRMClient` class in your PHP script:

   ```php
   require_once 'WinRMClient.php';
   ```

2. Create an instance of the `WinRMClient` class:

   ```php
   $client = new WinRMClient('192.168.1.100', 'username', 'password');
   ```

   Replace `'192.168.1.100'`, `'username'`, and `'password'` with the appropriate values for your target Windows machine.

3. Execute a command:

   ```php
   $output = $client->execute_command('dir C:\\');
   echo $output;
   ```

   This will execute the `dir C:\` command on the remote Windows machine and display the output.

## Example

```php
<?php
require_once 'WinRMClient.php';

$client = new WinRMClient('192.168.1.100', 'administrator', 'password123');

// List contents of C:\
$output = $client->execute_command('dir C:\\');
echo "Contents of C:\\\n$output\n\n";

// Get system information
$output = $client->execute_command('systeminfo');
echo "System Information:\n$output\n";
```

## Limitations

- This script uses Basic authentication, which is not secure over unencrypted connections.
- Only works with local users (no domain users can be used for Basic auth)
- It does not support more advanced WinRM features like file transfers.
- Error handling is basic and may need improvement for production use.

## Contributing

Feel free to fork this project and submit pull requests for any improvements or bug fixes.
