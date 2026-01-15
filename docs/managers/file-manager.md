# File Manager

The File Manager is responsible for **securely downloading** the upscaled image from the Upsampler temporary URL and **storing it locally**.

It does not serve downloads to customers (that is handled by the Download Manager). Instead, it handles the “internal” file persistence step after an Upsampler job completes.

## Responsibilities

- Ensures there is a dedicated storage directory under WordPress uploads.
- Downloads files from Upsampler using streaming requests.
- Validates downloaded files are legitimate images and are an allowed MIME type.
- Stores the final file with a predictable, job-linked filename.

## Storage Location

Files are stored under the WordPress uploads directory:

- `{uploads}/sell-my-images/`

On first creation, the directory is protected by:

- a `.htaccess` denying direct access
- an `index.php` “silence is golden” file

## Download + Validation Flow

Primary method:

- `SellMyImages\Managers\FileManager::download_from_upsampler( $upscaled_url, $job_id )`

Behavior:

1. Streams the remote file to a temporary file on disk.
2. Verifies the request returned HTTP 200.
3. Validates content exists (file exists and size > 0).
4. Confirms the file is a real image via `getimagesize()`.
5. Verifies the MIME type is in the allowed list:
   - `image/jpeg`, `image/jpg`, `image/png`, `image/webp`
6. Renames the temporary file to a final file with the correct extension.

## Timeouts

Large image downloads use a longer HTTP timeout:

- `DOWNLOAD_TIMEOUT = 300` seconds

## Storage Stats

For admin/debug contexts, storage statistics can be retrieved via:

- `SellMyImages\Managers\FileManager::get_storage_stats()`

Returns:

- `file_count`
- `total_size`
- `total_size_formatted`

## Related Code

- `sell-my-images/src/Managers/FileManager.php`
- `sell-my-images/src/Managers/DownloadManager.php`
