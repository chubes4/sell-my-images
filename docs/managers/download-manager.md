# Download Manager

The Download Manager handles secure file delivery with token-based authentication and automatic cleanup.

## Secure Download System

**Token-Based Access**
- 64-character random tokens via `wp_generate_password()`
- Database storage with expiration timestamps
- Single-use download enforcement
- Secure file path obfuscation

**File Serving**
```php
// Direct file delivery through REST API
DownloadManager::serve_download($token);
// Handles headers, streaming, and cleanup
```

## File Management

**Storage Structure**
Downloads stored in WordPress uploads directory:
```
/wp-content/uploads/smi-downloads/
├── 2023/10/
│   ├── job-uuid-4x.jpg
│   └── job-uuid-8x.png
```

**Security Features**
- Files stored outside web root when possible
- Direct access prevention via .htaccess
- Token validation before file access
- Automatic cleanup after expiration

## Download Process

**File Delivery**
1. Token validation and expiration check
2. File existence verification
3. Security header configuration
4. Chunked file streaming for large files
5. Download completion logging

**HTTP Headers**
```php
// Secure download headers
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="image-4x.jpg"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
```

## Expiration System

**Automatic Cleanup**
```php
// Remove expired downloads
$cleaned = DownloadManager::cleanup_expired_files();
// Returns: number of files removed
```

**Expiration Logic**
- Default 24-hour download window
- Configurable expiration period
- Grace period for completion
- Email notification before expiration

## Performance Optimization

**Chunked Streaming**
Large file handling with memory efficiency:
```php
// Stream file in 8KB chunks
$chunk_size = 8192;
while (!feof($handle)) {
    echo fread($handle, $chunk_size);
    flush();
}
```

**File Size Limits**
- Server memory limit awareness
- Progressive download support
- Resume capability for large files
- Bandwidth throttling options

## Token Management

**Generation Process**
```php
// Create secure download token
$token = DownloadManager::create_download_token($job_id, $file_path);
// Links token to job and file for validation
```

**Validation Process**
- Token existence verification
- Expiration timestamp checking
- File path validation
- Job completion status confirmation

## Integration Points

**Email Notifications**
- Download link generation for emails
- Expiration warning notifications
- Completion confirmations
- Customer support integration

**Admin Management**
- Token regeneration capability
- Manual expiration extension
- Download statistics tracking
- File cleanup administration

## Security Measures

**Access Control**
- No directory browsing allowed
- Token-only file access
- IP address logging (optional)
- Rate limiting per token

**File Protection**
```
# .htaccess in download directory
<Files "*">
    Order Deny,Allow
    Deny from all
</Files>
```

**Privacy Compliance**
- Automatic file deletion
- No personal data in filenames
- Secure token generation
- GDPR-compliant handling