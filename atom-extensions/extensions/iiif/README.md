# IIIF Extension

Framework-independent IIIF (International Image Interoperability Framework) integration for AtoM with deep zoom viewing and manifest generation.

## Overview

This extension provides complete IIIF support for archival images:
- **Deep Zoom Viewer**: OpenSeadragon-powered image viewing with pan, zoom, rotate
- **IIIF Manifests**: Presentation API 2.1 compliant manifest generation
- **Cantaloupe Integration**: Seamless connection to Cantaloupe image server
- **Multi-Image Support**: Image carousels for information objects with multiple digital objects
- **Framework Independent**: Zero Symfony dependencies

## Features

### ✅ Deep Zoom Image Viewer
- Pan and zoom with smooth animations
- Rotate and flip controls
- Navigator thumbnail
- Full-screen mode
- Touch/mobile support
- Keyboard shortcuts

### ✅ IIIF Standards Compliance
- Image API 2.1 support
- Presentation API 2.1 manifests
- Compatible with Universal Viewer, Mirador
- Standard IIIF URLs

### ✅ Performance Optimized
- Tile-based progressive loading
- Efficient bandwidth usage
- Browser caching support
- Lazy loading for multi-image displays

### ✅ Enterprise Logging
- Viewer initialization tracking
- Manifest generation logging
- Error handling with full context
- Performance metrics

## Installation

### Prerequisites

- AtoM 2.9+ with extension framework
- PHP 8.1+
- Cantaloupe 5.0.6+ image server
- OpenSeadragon library (included)

### Cantaloupe Setup

1. **Install Cantaloupe**:

```bash
cd /opt
wget https://github.com/cantaloupe-project/cantaloupe/releases/download/v5.0.6/cantaloupe-5.0.6.zip
unzip cantaloupe-5.0.6.zip
cd cantaloupe-5.0.6
```

2. **Configure cantaloupe.properties**:

```properties
# Image source
FilesystemSource.BasicLookupStrategy.path_prefix = /usr/share/nginx/atom/uploads/

# Slash handling
slash_substitute = _SL_

# Performance
max_pixels = 50000000
cache.server.enabled = true
cache.server.ttl_seconds = 2592000

# HTTPS
https.enabled = true
https.host = 0.0.0.0
https.port = 8183
```

3. **Configure delegates.rb**:

```ruby
module Cantaloupe
  class Delegate
    def filesystemsource_pathname(options = {})
      identifier = context['identifier']
      base = '/usr/share/nginx/atom/uploads'
      File.join(base, identifier)
    end
  end
end
```

4. **Start Cantaloupe**:

```bash
java -Dcantaloupe.config=/opt/cantaloupe-5.0.6/cantaloupe.properties \
     -Xmx2g -jar cantaloupe-5.0.6.jar &
```

5. **Setup systemd service**:

```bash
sudo nano /etc/systemd/system/cantaloupe.service
```

```ini
[Unit]
Description=Cantaloupe IIIF Image Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/cantaloupe-5.0.6
ExecStart=/usr/bin/java -Dcantaloupe.config=/opt/cantaloupe-5.0.6/cantaloupe.properties -Xmx2g -jar /opt/cantaloupe-5.0.6/cantaloupe-5.0.6.jar
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable cantaloupe
sudo systemctl start cantaloupe
```

### nginx Reverse Proxy

```nginx
location /iiif/2 {
    proxy_pass http://localhost:8182/iiif/2;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    
    # CORS headers for IIIF
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, OPTIONS";
    add_header Access-Control-Allow-Headers "Content-Type";
}
```

### Extension Configuration

Settings in database:

```sql
-- Enable IIIF
INSERT INTO setting (name, value) VALUES ('iiif_enabled', '1');

-- Cantaloupe URL
INSERT INTO setting (name, value) VALUES 
  ('iiif_base_url', 'https://archives.theahg.co.za/iiif/2');

-- API version (2 or 3)
INSERT INTO setting (name, value) VALUES ('iiif_api_version', '2');

-- Slash substitute (must match Cantaloupe)
INSERT INTO setting (name, value) VALUES ('iiif_slash_substitute', '_SL_');

-- Enable manifests
INSERT INTO setting (name, value) VALUES ('iiif_enable_manifests', '1');

-- Enable download button
INSERT INTO setting (name, value) VALUES ('iiif_enable_download', '1');

-- Viewer height (pixels)
INSERT INTO setting (name, value) VALUES ('iiif_viewer_height', '600');
```

## Usage

### Automatic Viewer Injection

The extension automatically detects IIIF-compatible images and injects the viewer. No template changes needed!

**Compatible formats:**
- JPEG/JPG
- TIFF/TIF
- PNG
- GIF
- WebP

### Manual Viewer Integration

If you need to manually add viewer to templates:

```php
// Get IIIF service
$iiifService = $context->getService('iiif.service');

// Render viewer for digital object
$viewerHtml = $iiifService->renderViewer($digitalObject, [
    'height' => 800,
    'show_download' => true
]);

echo $viewerHtml;
```

### IIIF Manifest URLs

Manifests are automatically generated at:

```
# Information object manifest
https://your-atom.org/iiif/{slug}/manifest

# Digital object manifest  
https://your-atom.org/iiif/object/{id}/manifest
```

### Using with External Viewers

**Universal Viewer:**
```html
<iframe src="https://universalviewer.io/#?manifest=https://your-atom.org/iiif/{slug}/manifest"></iframe>
```

**Mirador:**
```javascript
Mirador.viewer({
  id: 'mirador',
  windows: [{
    manifestId: 'https://your-atom.org/iiif/{slug}/manifest'
  }]
});
```

## Testing

### Test Cantaloupe

```bash
# Test info.json endpoint
curl https://archives.theahg.co.za/iiif/2/r_SL_path_SL_to_SL_image.jpg/info.json

# Should return JSON with image properties
```

### Test Viewer

1. Upload JPEG/TIFF image to AtoM
2. View the information object
3. IIIF viewer should appear automatically
4. Check logs:

```bash
tail -f /var/log/atom/atom-ext-iiif.log
```

Should show:
```
[...] ext-iiif.INFO: IIIF extension booted 
{"cantaloupe_url":"https://archives.theahg.co.za/iiif/2","api_version":2}

[...] ext-iiif.DEBUG: IIIF viewer injected 
{"digital_object_id":3962,"mime_type":"image/tiff"}
```

### Test Manifest

```bash
# Generate manifest
curl https://archives.theahg.co.za/iiif/some-slug/manifest

# Should return JSON manifest
```

## Troubleshooting

### Viewer Not Appearing

**Problem:** Images don't show IIIF viewer

**Solution:**
```bash
# 1. Check Cantaloupe is running
sudo systemctl status cantaloupe

# 2. Check extension is loaded
grep "IIIF extension booted" /var/log/atom/atom-system.log

# 3. Verify MIME type is compatible
mysql -u root -p atom -e "SELECT id, name, mime_type FROM digital_object WHERE id = 3962;"

# 4. Test Cantaloupe directly
curl "https://archives.theahg.co.za/iiif/2/test.jpg/info.json"
```

### Black Screen / No Image

**Problem:** Viewer loads but shows black screen

**Solution:**
```bash
# 1. Check browser console for errors
# Look for: "Failed to load tile" or CORS errors

# 2. Verify file path in Cantaloupe
# Check Cantaloupe logs: /opt/cantaloupe-5.0.6/logs/application.log

# 3. Test identifier building
# The identifier should match file path with slashes replaced

# 4. Check file permissions
sudo ls -la /usr/share/nginx/atom/uploads/r/path/to/image.tif
# Should be readable by www-data
```

### 403 Forbidden Errors

**Problem:** Cantaloupe returns 403

**Solution:**
```bash
# Increase max_pixels in cantaloupe.properties
max_pixels = 100000000

sudo systemctl restart cantaloupe
```

### Manifest Generation Fails

**Problem:** Manifest URL returns error

**Solution:**
```bash
# Check manifest generation logs
grep "manifest generated" /var/log/atom/atom-ext-iiif.log

# Verify digital objects are linked
mysql -u root -p atom -e "
SELECT io.id, io.title, COUNT(do.id) as image_count 
FROM information_object io 
LEFT JOIN digital_object do ON do.information_object_id = io.id 
WHERE io.slug = 'your-slug' 
GROUP BY io.id;"
```

## Configuration Options

Complete list of database settings:

| Setting | Default | Description |
|---------|---------|-------------|
| `iiif_enabled` | `true` | Enable/disable IIIF |
| `iiif_base_url` | - | Cantaloupe base URL |
| `iiif_api_version` | `2` | IIIF API version (2 or 3) |
| `iiif_server_type` | `cantaloupe` | Image server type |
| `iiif_slash_substitute` | `_SL_` | Slash replacement in identifiers |
| `iiif_enable_manifests` | `true` | Enable manifest generation |
| `iiif_enable_download` | `true` | Show download button |
| `iiif_viewer_height` | `600` | Viewer height in pixels |
| `iiif_carousel_autorotate` | `true` | Auto-rotate carousel |
| `iiif_carousel_interval` | `5000` | Carousel rotation interval (ms) |

## Architecture

### Components

```
IIIF Extension
├── Extension.php          # Entry point, event handling
└── IiifService.php        # Core IIIF logic

Uses:
├── DatabaseInterface      # Settings, digital object queries
├── FileSystemInterface    # File paths, uploads directory
├── ConfigurationInterface # App configuration
└── LoggerInterface        # Structured logging
```

### No Symfony Dependencies

```php
// ❌ OLD (Plugin - Symfony-coupled)
$baseUrl = sfConfig::get('app_iiif_base_url');
$digitalObject = QubitDigitalObject::getById($id);

// ✅ NEW (Extension - Interface-based)
$baseUrl = $this->configuration->get('iiif_base_url');
$digitalObject = $this->database->findById('digital_object', $id);
```

### Event Flow

```
Digital Object Viewed
    ↓
Event: 'view.render.digital_object'
    ↓
IiifService::isIiifCompatible() checks MIME type
    ↓
IiifService::renderViewer() generates HTML
    ↓
Viewer injected into page
    ↓
OpenSeadragon loads from Cantaloupe
    ↓
Logged to atom-ext-iiif.log
```

## Performance

- **Initial load**: ~500ms (first tile request)
- **Subsequent tiles**: ~50-100ms each
- **Memory overhead**: ~100KB per viewer instance
- **Bandwidth**: Efficient - only loads visible tiles

## Security

### File Access Control

Cantaloupe respects AtoM's file permissions:
- Files in `uploads/` directory only
- No directory traversal
- Read-only access

### CORS Headers

IIIF requires CORS for external viewers:
```nginx
add_header Access-Control-Allow-Origin *;
```

**Note:** Restrict in production if needed:
```nginx
add_header Access-Control-Allow-Origin "https://trusted-viewer.com";
```

## Integration with Other Extensions

### Security Clearance

IIIF respects security classifications:
```
User with "Restricted" clearance:
    ↓
Views information object with "Secret" image
    ↓
Security extension blocks access
    ↓
IIIF viewer never renders
```

### Metadata Extraction

Works seamlessly with metadata extraction:
```
Upload TIFF with EXIF
    ↓
Metadata extraction runs first
    ↓
IIIF viewer renders with extracted metadata
    ↓
Both logged independently
```

## License

GNU Affero General Public License v3.0 (same as AtoM)

## Credits

- **OpenSeadragon**: https://openseadragon.github.io/
- **Cantaloupe**: https://cantaloupe-project.github.io/
- **IIIF**: https://iiif.io/

## Author

Johan Pieterse <pieterse.johan3@gmail.com>  
Part of The AHG archives project (theahg.co.za)
