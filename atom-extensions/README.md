# AtoM Enhanced Digital Object Viewer with IIIF Support

A comprehensive plugin for AtoM (Access to Memory) that provides advanced viewing capabilities for various digital object formats including IIIF images, multi-page TIFFs, 3D models, PDFs, audio, video, and more.

## Features

- **IIIF Support**: Deep zoom viewing for high-resolution images and multi-page TIFFs
- **3D Model Viewer**: Interactive viewing of GLB, GLTF, OBJ, STL, and other 3D formats
- **Multi-page TIFF**: Page-by-page navigation with thumbnails
- **Enhanced Image Viewer**: Zoom, pan, and rotate capabilities for standard images
- **Media Players**: Built-in audio and video playback
- **PDF Viewer**: Inline PDF display with navigation
- **Document Support**: Handling for Office documents and text files

## System Requirements

- AtoM 2.7+ (tested on 2.9)
- PHP 7.4+ (tested on 8.3)
- Ubuntu 20.04+ or similar Linux distribution
- Nginx or Apache web server
- ImageMagick with PHP Imagick extension
- Java 11+ (for Cantaloupe IIIF server)

## Dependencies Installation

### 1. Install System Packages

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install ImageMagick and PHP Imagick
sudo apt install -y imagemagick php-imagick

# Install Java for Cantaloupe
sudo apt install -y openjdk-11-jdk

# Install additional image libraries
sudo apt install -y libimage-exiftool-perl libtiff-tools

# Verify installations
convert --version
php -m | grep imagick
java -version
```

### 2. Install and Configure Cantaloupe IIIF Server

```bash
# Download Cantaloupe (check for latest version at https://cantaloupe-project.github.io/)
cd /opt
sudo wget https://github.com/cantaloupe-project/cantaloupe/releases/download/v5.0.6/cantaloupe-5.0.6.zip
sudo unzip cantaloupe-5.0.6.zip
sudo rm cantaloupe-5.0.6.zip

# Create directories
sudo mkdir -p /var/cache/cantaloupe
sudo mkdir -p /var/log/cantaloupe

# Set permissions
sudo chown -R www-data:www-data /var/cache/cantaloupe
sudo chown -R www-data:www-data /var/log/cantaloupe

# Copy configuration files
cd /opt/cantaloupe-5.0.6
sudo cp cantaloupe.properties.sample cantaloupe.properties

# Create the delegate script
sudo nano delegates.rb
```

Add the delegate script content:

```ruby
module Cantaloupe
  class Delegate
    attr_accessor :context
    
    UPLOADS_ROOT = '/usr/share/nginx/atom_psis/uploads'  # Adjust path for your AtoM installation
    
    def filesystemsource_pathname(options = {})
      identifier = @context['identifier']
      
      puts "[Cantaloupe] Raw identifier: #{identifier}"
      
      # Handle meta-identifier (identifier;page format)
      if identifier.include?(';')
        parts = identifier.split(';')
        base_identifier = parts[0]
        page_num = parts[1].to_i if parts[1]
        puts "[Cantaloupe] Detected page request: page #{page_num} of #{base_identifier}"
      else
        base_identifier = identifier
        page_num = nil
      end
      
      # The identifier already has slashes (Cantaloupe already converted _SL_ to /)
      id = base_identifier
      
      # Remove "uploads/" prefix if present since our UPLOADS_ROOT already includes it
      id = id.sub(/^uploads\//, '') if id.start_with?('uploads/')
      
      # Build full absolute path
      full_path = File.join(UPLOADS_ROOT, id)
      
      puts "[Cantaloupe] Looking for file at: #{full_path}"
      
      if File.exist?(full_path)
        puts "[Cantaloupe] ✓ File found"
        
        if page_num && page_num > 0
          page_index = page_num - 1
          page_path = "#{full_path}[#{page_index}]"
          puts "[Cantaloupe] → Returning page #{page_num} (index #{page_index}): #{page_path}"
          return page_path
        else
          return full_path
        end
      else
        puts "[Cantaloupe] ✗ File NOT found at #{full_path}"
        return nil
      end
    end
    
    def pre_authorize(options = {})
      true
    end
    
    def authorize(options = {})
      true
    end
    
    def source(options = {})
      'FilesystemSource'
    end
  end
end

class CustomDelegate < Cantaloupe::Delegate
end
```

### 3. Configure Cantaloupe

Edit `/opt/cantaloupe-5.0.6/cantaloupe.properties`:

```properties
# Basic Configuration
http.enabled = true
http.host = 0.0.0.0
http.port = 8182

# Slash substitute for URL encoding
slash_substitute = _SL_

# Meta-identifier configuration
meta_identifier.transformer = StandardMetaIdentifierTransformer
meta_identifier.transformer.StandardMetaIdentifierTransformer.delimiter = ;

# Enable IIIF endpoints
endpoint.iiif.1.enabled = false
endpoint.iiif.2.enabled = true
endpoint.iiif.3.enabled = true

# Delegate script
delegate_script.enabled = true
delegate_script.pathname = /opt/cantaloupe-5.0.6/delegates.rb

# Source configuration
source.delegate = true
FilesystemSource.lookup_strategy = ScriptLookupStrategy

# Processor
processor.selection_strategy = AutomaticSelectionStrategy

# Cache
cache.server.derivative.enabled = true
cache.server.derivative = FilesystemCache
FilesystemCache.pathname = /var/cache/cantaloupe

# CORS for AtoM
cors.enabled = true
cors.allow_origin = *

# Logging
log.application.level = info
```

### 4. Create Cantaloupe Service

```bash
sudo nano /etc/systemd/system/cantaloupe.service
```

Add:

```ini
[Unit]
Description=Cantaloupe IIIF Image Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
ExecStart=/usr/bin/java -Xmx2g -jar /opt/cantaloupe-5.0.6/cantaloupe-5.0.6.jar
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Start the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable cantaloupe
sudo systemctl start cantaloupe
sudo systemctl status cantaloupe
```

### 5. Configure Nginx Proxy

Add to your AtoM nginx configuration:

```nginx
# IIIF proxy configuration
location /iiif/ {
    proxy_pass http://localhost:8182/iiif/;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Host $host;
    proxy_connect_timeout 600;
    proxy_send_timeout 600;
    proxy_read_timeout 600;
    send_timeout 600;
}

# Serve 3D viewer assets
location /atom_psis/3d/ {
    alias /usr/share/nginx/atom_psis/plugins/arPsisPlugin/web/3d/;
}

# Serve zoom-pan assets
location /atom-extensions/extensions/zoom-pan/ {
    alias /usr/share/nginx/atom_psis/plugins/arPsisPlugin/web/zoom-pan/;
}
```

Reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## AtoM Implementation

### 1. Create Plugin Structure

```bash
cd /usr/share/nginx/atom_psis/plugins
sudo mkdir -p arPsisPlugin/{config,lib,modules,web}
```

### 2. Install the Enhanced Viewer

Create `/usr/share/nginx/atom_psis/plugins/arPsisPlugin/lib/informationObjectHelper.php`:

```php
<?php

function render_digital_object_viewer($resource, $obj)
{
    // [Insert the complete viewer code from the provided implementation]
    // This includes all the format detection, viewer initialization,
    // and rendering logic for different file types
}
```

### 3. Download JavaScript Libraries

```bash
# Create directories
cd /usr/share/nginx/atom_psis/plugins/arPsisPlugin/web
sudo mkdir -p 3d/js zoom-pan/public

# Download Three.js for 3D viewing
cd 3d/js
sudo wget https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js
sudo wget https://raw.githubusercontent.com/mrdoob/three.js/r128/examples/js/loaders/GLTFLoader.js
sudo wget https://raw.githubusercontent.com/mrdoob/three.js/r128/examples/js/controls/OrbitControls.js

# Create zoom-pan viewer files
cd ../../zoom-pan/public
```

Create `zoom-pan.css`:

```css
.zoom-pan-container {
    position: relative;
    overflow: hidden;
    cursor: move;
}

.zoom-pan-stage {
    transform-origin: center center;
    transition: transform 0.3s ease;
}

.zoom-pan-toolbar {
    background: rgba(0,0,0,0.7);
    border-radius: 4px;
    padding: 5px;
}

.zp-btn {
    width: 30px;
    height: 30px;
    margin: 2px;
    background: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}

.zp-btn:hover {
    background: #ddd;
}
```

Create `zoom-pan.js`:

```javascript
class ZoomPanViewer {
    constructor(selector, options) {
        this.container = document.querySelector(selector);
        this.stage = this.container.querySelector('.zoom-pan-stage');
        this.img = this.stage.querySelector('img');
        this.scale = 1;
        this.rotation = 0;
        
        this.initControls();
        this.initEvents();
    }
    
    initControls() {
        const toolbar = this.container.querySelector('.zoom-pan-toolbar');
        toolbar.addEventListener('click', (e) => {
            const action = e.target.dataset.action;
            switch(action) {
                case 'zoom-in': this.zoom(1.2); break;
                case 'zoom-out': this.zoom(0.8); break;
                case 'rotate-left': this.rotate(-90); break;
                case 'rotate-right': this.rotate(90); break;
                case 'reset': this.reset(); break;
                case 'fullscreen': this.fullscreen(); break;
            }
        });
    }
    
    initEvents() {
        this.container.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? 0.9 : 1.1;
            this.zoom(delta);
        });
    }
    
    zoom(factor) {
        this.scale *= factor;
        this.scale = Math.max(0.1, Math.min(10, this.scale));
        this.updateTransform();
    }
    
    rotate(degrees) {
        this.rotation += degrees;
        this.updateTransform();
    }
    
    reset() {
        this.scale = 1;
        this.rotation = 0;
        this.updateTransform();
    }
    
    fullscreen() {
        if (this.container.requestFullscreen) {
            this.container.requestFullscreen();
        }
    }
    
    updateTransform() {
        this.img.style.transform = `scale(${this.scale}) rotate(${this.rotation}deg)`;
    }
}
```

### 4. Integrate with AtoM Templates

Modify your AtoM template file (e.g., `/usr/share/nginx/atom_psis/apps/qubit/modules/informationobject/templates/showSuccess.php`):

```php
<?php 
// Replace the standard digital object display with:
if ($resource->getDigitalObject()) {
    $digitalObjects = $resource->getDigitalObjectsByUsageId(QubitTerm::MASTER_ID);
    foreach ($digitalObjects as $digitalObject) {
        echo render_digital_object_viewer($resource, $digitalObject);
    }
}
?>
```

### 5. Set Permissions

```bash
sudo chown -R www-data:www-data /usr/share/nginx/atom_psis/plugins/arPsisPlugin
sudo chmod -R 755 /usr/share/nginx/atom_psis/plugins/arPsisPlugin
```

### 6. Clear AtoM Cache

```bash
cd /usr/share/nginx/atom_psis
sudo -u www-data php symfony cc
```

## Testing

1. **Test Cantaloupe directly:**
   ```
   curl http://localhost:8182/iiif/2/test.jpg/info.json
   ```

2. **Test through Nginx:**
   ```
   curl https://your-domain.com/iiif/2/test.jpg/info.json
   ```

3. **Upload test files in AtoM:**
   - Multi-page TIFF
   - High-resolution JP2 or TIFF
   - 3D model (GLB format)
   - Regular images (JPG, PNG)
   - PDF documents

## Troubleshooting

### Check Cantaloupe logs:
```bash
sudo journalctl -u cantaloupe -f
tail -f /var/log/cantaloupe/application.log
```

### Check file permissions:
```bash
ls -la /usr/share/nginx/atom_psis/uploads/
```

### Test ImageMagick:
```bash
identify /path/to/multi-page.tif
```

### Clear caches:
```bash
# AtoM cache
cd /usr/share/nginx/atom_psis
sudo -u www-data php symfony cc

# Cantaloupe cache
sudo rm -rf /var/cache/cantaloupe/*
```

## Performance Tuning

1. **Increase Java heap for Cantaloupe:**
   Edit `/etc/systemd/system/cantaloupe.service`:
   ```
   ExecStart=/usr/bin/java -Xmx4g -jar /opt/cantaloupe-5.0.6/cantaloupe-5.0.6.jar
   ```

2. **Enable Cantaloupe caching:**
   In `cantaloupe.properties`:
   ```
   cache.server.derivative.enabled = true
   cache.server.derivative.ttl_seconds = 2592000
   ```

3. **Optimize Nginx:**
   ```nginx
   proxy_cache_path /var/cache/nginx/iiif levels=1:2 keys_zone=iiif:100m inactive=7d max_size=10g;
   
   location /iiif/ {
       proxy_cache iiif;
       proxy_cache_valid 200 7d;
       # ... other proxy settings
   }
   ```

## License

This implementation is provided as-is for use with AtoM installations.

## Support

For issues or questions:
- Check AtoM documentation: https://www.accesstomemory.org/
- Cantaloupe documentation: https://cantaloupe-project.github.io/
- IIIF specifications: https://iiif.io/