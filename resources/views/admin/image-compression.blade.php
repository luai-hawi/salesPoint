<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Compression & Cleanup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background-color: #4CAF50;
            width: 0%;
            transition: width 0.3s ease;
        }
        .button {
            background-color: #007cba;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
            transition: background-color 0.3s;
        }
        .button:hover:not(:disabled) {
            background-color: #005a87;
        }
        .button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .button.danger {
            background-color: #dc3545;
        }
        .button.danger:hover:not(:disabled) {
            background-color: #c82333;
        }
        .results {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #007cba;
        }
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            border-color: #dc3545;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            border-color: #28a745;
        }
        .status {
            font-weight: bold;
            margin: 10px 0;
        }
        .log {
            max-height: 300px;
            overflow-y: auto;
            background: #f1f1f1;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007cba;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🖼️ Image Compression & Cleanup Tool</h1>
        <p>This tool will compress all product images and remove unused image files.</p>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="compressed-count">0</div>
                <div>Images Compressed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="deleted-count">0</div>
                <div>Files Deleted</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="error-count">0</div>
                <div>Errors</div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill"></div>
        </div>
        <div id="progress-text">Ready to start</div>

        <div style="margin: 20px 0;">
            <button class="button" id="start-compression" onclick="startCompression()">
                🚀 Start Compression
            </button>
            <button class="button" id="quick-compress" onclick="quickCompress()">
                ⚡ Quick Compress (5 products)
            </button>
            <button class="button danger" id="cleanup-only" onclick="cleanupOnly()">
                🗑️ Cleanup Unused Files Only
            </button>
        </div>

        <div id="results" class="results hidden">
            <h3>Processing Log:</h3>
            <div class="log" id="log"></div>
        </div>
    </div>

    <script>
        let totalCompressed = 0;
        let totalDeleted = 0;
        let totalErrors = 0;
        let isProcessing = false;

        function updateStats() {
            document.getElementById('compressed-count').textContent = totalCompressed;
            document.getElementById('deleted-count').textContent = totalDeleted;
            document.getElementById('error-count').textContent = totalErrors;
        }

        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `[${timestamp}] ${message}\n`;
            logDiv.textContent += logEntry;
            logDiv.scrollTop = logDiv.scrollHeight;
            
            document.getElementById('results').classList.remove('hidden');
        }

        function setProgress(percent, text) {
            document.getElementById('progress-fill').style.width = percent + '%';
            document.getElementById('progress-text').textContent = text;
        }

        function setButtonsDisabled(disabled) {
            document.getElementById('start-compression').disabled = disabled;
            document.getElementById('quick-compress').disabled = disabled;
            document.getElementById('cleanup-only').disabled = disabled;
            isProcessing = disabled;
        }

        async function startCompression() {
            if (isProcessing) return;
            
            setButtonsDisabled(true);
            log('🚀 Starting batch compression...', 'info');
            setProgress(0, 'Initializing...');
            
            let offset = 0;
            let hasMore = true;
            
            while (hasMore) {
                try {
                    const response = await fetch(`/compress-and-cleanup-images?step=compress&offset=${offset}&batch=10`);
                    const data = await response.json();
                    
                    totalCompressed += data.compressed;
                    totalErrors += data.errors.length;
                    
                    updateStats();
                    
                    if (data.progress) {
                        setProgress(data.progress, `Processing images... ${data.progress}%`);
                    }
                    
                    if (data.compressed > 0) {
                        log(`✅ Compressed ${data.compressed} images in this batch`);
                    }
                    
                    if (data.errors.length > 0) {
                        data.errors.forEach(error => log(`❌ ${error}`, 'error'));
                    }
                    
                    hasMore = data.hasMore;
                    offset = data.nextOffset;
                    
                    // Small delay to prevent overwhelming the server
                    await new Promise(resolve => setTimeout(resolve, 100));
                    
                } catch (error) {
                    log(`❌ Network error: ${error.message}`, 'error');
                    break;
                }
            }
            
            // Now cleanup unused files
            log('🧹 Starting cleanup of unused files...');
            setProgress(90, 'Cleaning up unused files...');
            
            try {
                const response = await fetch('/compress-and-cleanup-images?step=cleanup');
                const data = await response.json();
                
                totalDeleted += data.deleted;
                totalErrors += data.errors.length;
                
                updateStats();
                
                if (data.deleted > 0) {
                    log(`🗑️ Deleted ${data.deleted} unused files`);
                    data.deleted_files.forEach(file => log(`   - ${file}`));
                }
                
                if (data.errors.length > 0) {
                    data.errors.forEach(error => log(`❌ ${error}`, 'error'));
                }
                
            } catch (error) {
                log(`❌ Cleanup error: ${error.message}`, 'error');
            }
            
            setProgress(100, 'Complete!');
            log('🎉 Process completed successfully!');
            setButtonsDisabled(false);
        }

        async function quickCompress() {
            if (isProcessing) return;
            
            setButtonsDisabled(true);
            log('⚡ Starting quick compression (5 products)...', 'info');
            setProgress(50, 'Quick compressing...');
            
            try {
                const response = await fetch('/quick-compress-images');
                const data = await response.json();
                
                totalCompressed += data.compressed;
                totalErrors += data.errors.length;
                
                updateStats();
                
                log(`✅ Quick compressed ${data.compressed} images`);
                
                if (data.errors.length > 0) {
                    data.errors.forEach(error => log(`❌ ${error}`, 'error'));
                }
                
                setProgress(100, 'Quick compression complete!');
                
            } catch (error) {
                log(`❌ Error: ${error.message}`, 'error');
            }
            
            setButtonsDisabled(false);
        }

        async function cleanupOnly() {
            if (isProcessing) return;
            
            if (!confirm('Are you sure you want to delete unused image files? This cannot be undone.')) {
                return;
            }
            
            setButtonsDisabled(true);
            log('🗑️ Starting cleanup of unused files only...', 'info');
            setProgress(50, 'Cleaning up...');
            
            try {
                const response = await fetch('/compress-and-cleanup-images?step=cleanup');
                const data = await response.json();
                
                totalDeleted += data.deleted;
                totalErrors += data.errors.length;
                
                updateStats();
                
                if (data.deleted > 0) {
                    log(`🗑️ Deleted ${data.deleted} unused files`);
                    data.deleted_files.forEach(file => log(`   - ${file}`));
                } else {
                    log('ℹ️ No unused files found');
                }
                
                if (data.errors.length > 0) {
                    data.errors.forEach(error => log(`❌ ${error}`, 'error'));
                }
                
                setProgress(100, 'Cleanup complete!');
                
            } catch (error) {
                log(`❌ Error: ${error.message}`, 'error');
            }
            
            setButtonsDisabled(false);
        }

        // Initialize
        updateStats();
    </script>
</body>
</html>