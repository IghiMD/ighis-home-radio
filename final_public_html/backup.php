<?php
/**
 * Enhanced Backup System for Ighi's Home Radio
 * SECURE VERSION with automated and manual backups
 */

require_once 'config.php';
require_once 'auth.php';

// Security check for non-API actions
$action = $_GET['action'] ?? 'list';
if (!in_array($action, ['health', 'api_create'])) {
    Auth::require();
}

// Configuration
define('BACKUP_PREFIX', 'ighi_radio_backup_');
define('BACKUP_EXTENSION', '.tar.gz');

/**
 * Backup management class
 */
class BackupManager {
    
    private $backupDir;
    private $maxBackups;
    private $backupEnabled;
    
    public function __construct() {
        $this->backupDir = BACKUP_DIR;
        $this->maxBackups = defined('MAX_BACKUPS') ? (int)MAX_BACKUPS : 168; // 7 days * 24 hours
        $this->backupEnabled = defined('BACKUP_ENABLED') ? BACKUP_ENABLED === 'true' : true;
        
        $this->ensureBackupDirectory();
    }
    
    /**
     * Ensure backup directory exists
     */
    private function ensureBackupDirectory() {
        if (!is_dir($this->backupDir)) {
            if (!mkdir($this->backupDir, 0755, true)) {
                throw new Exception("Failed to create backup directory: {$this->backupDir}");
            }
        }
        
        // Create .htaccess to protect backup directory
        $htaccessFile = $this->backupDir . '.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Order deny,allow\nDeny from all\n");
        }
    }
    
    /**
     * Create a new backup
     */
    public function createBackup($type = 'manual') {
        if (!$this->backupEnabled && $type !== 'manual') {
            return [
                'success' => false,
                'message' => 'Backups are disabled',
                'error_code' => 'BACKUP_DISABLED'
            ];
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = BACKUP_PREFIX . $timestamp . '_' . $type;
        $backupPath = $this->backupDir . $backupName;
        
        try {
            // Create temporary directory for backup preparation
            $tempDir = $backupPath . '_temp';
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception("Failed to create temporary directory");
            }
            
            // Copy files to backup
            $filesCopied = 0;
            $totalSize = 0;
            $backupManifest = [
                'created_at' => time(),
                'created_by' => Auth::getCurrentUser()['username'] ?? 'system',
                'type' => $type,
                'files' => [],
                'version' => '2.0'
            ];
            
            // Define files to backup
            $filesToBackup = $this->getFilesToBackup();
            
            foreach ($filesToBackup as $fileInfo) {
                $source = $fileInfo['path'];
                $relativePath = $fileInfo['relative'];
                $description = $fileInfo['description'];
                
                if (file_exists($source)) {
                    $destDir = dirname($tempDir . '/' . $relativePath);
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    
                    $dest = $tempDir . '/' . $relativePath;
                    
                    if (is_dir($source)) {
                        // Copy directory recursively
                        $this->copyDirectory($source, $dest);
                        $size = $this->getDirectorySize($dest);
                    } else {
                        // Copy single file
                        if (copy($source, $dest)) {
                            $size = filesize($dest);
                        } else {
                            throw new Exception("Failed to copy file: $source");
                        }
                    }
                    
                    $backupManifest['files'][] = [
                        'path' => $relativePath,
                        'description' => $description,
                        'size' => $size,
                        'modified' => filemtime($source)
                    ];
                    
                    $filesCopied++;
                    $totalSize += $size;
                }
            }
            
            // Save backup manifest
            file_put_contents($tempDir . '/backup_manifest.json', json_encode($backupManifest, JSON_PRETTY_PRINT));
            
            // Create compressed archive
            $archivePath = $backupPath . BACKUP_EXTENSION;
            
            if ($this->createTarGz($tempDir, $archivePath)) {
                // Clean up temporary directory
                $this->removeDirectory($tempDir);
                
                // Clean up old backups
                $this->cleanupOldBackups();
                
                $this->logBackupEvent('BACKUP_CREATED', [
                    'backup_name' => $backupName,
                    'type' => $type,
                    'files_count' => $filesCopied,
                    'total_size' => $totalSize
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'backup_name' => $backupName . BACKUP_EXTENSION,
                    'files_count' => $filesCopied,
                    'total_size' => $this->formatFileSize($totalSize),
                    'path' => $archivePath
                ];
            } else {
                throw new Exception("Failed to create compressed archive");
            }
            
        } catch (Exception $e) {
            // Clean up on error
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            $this->logBackupEvent('BACKUP_FAILED', [
                'backup_name' => $backupName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
                'error_code' => 'BACKUP_CREATION_FAILED'
            ];
        }
    }
    
    /**
     * Get list of available backups
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . BACKUP_PREFIX . '*' . BACKUP_EXTENSION);
        
        foreach ($files as $file) {
            $basename = basename($file, BACKUP_EXTENSION);
            $info = $this->parseBackupName($basename);
            
            if ($info) {
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'created_at' => $info['timestamp'],
                    'created_date' => date('Y-m-d H:i:s', $info['timestamp']),
                    'type' => $info['type'],
                    'size' => filesize($file),
                    'size_formatted' => $this->formatFileSize(filesize($file)),
                    'age' => $this->formatAge(time() - $info['timestamp']),
                    'manifest' => $this->getBackupManifest($file)
                ];
            }
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });
        
        return $backups;
    }
    
    /**
     * Restore from backup
     */
    public function restoreBackup($backupName) {
        $backupPath = $this->backupDir . $backupName;
        
        if (!file_exists($backupPath)) {
            return [
                'success' => false,
                'message' => 'Backup file not found',
                'error_code' => 'BACKUP_NOT_FOUND'
            ];
        }
        
        try {
            // Create temporary directory for extraction
            $tempDir = $this->backupDir . 'restore_temp_' . time();
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception("Failed to create temporary directory for restore");
            }
            
            // Extract backup
            if (!$this->extractTarGz($backupPath, $tempDir)) {
                throw new Exception("Failed to extract backup archive");
            }
            
            // Read manifest
            $manifestFile = $tempDir . '/backup_manifest.json';
            if (!file_exists($manifestFile)) {
                throw new Exception("Backup manifest not found - invalid backup");
            }
            
            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!$manifest) {
                throw new Exception("Invalid backup manifest");
            }
            
            // Create backup of current state before restore
            $preRestoreBackup = $this->createBackup('pre_restore');
            
            $filesRestored = 0;
            
            // Restore files
            foreach ($manifest['files'] as $fileInfo) {
                $sourcePath = $tempDir . '/' . $fileInfo['path'];
                $destPath = $this->getAbsolutePath($fileInfo['path']);
                
                if (file_exists($sourcePath)) {
                    $destDir = dirname($destPath);
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    
                    if (is_dir($sourcePath)) {
                        // Restore directory
                        $this->copyDirectory($sourcePath, $destPath);
                    } else {
                        // Restore single file
                        copy($sourcePath, $destPath);
                    }
                    
                    $filesRestored++;
                }
            }
            
            // Clean up temporary directory
            $this->removeDirectory($tempDir);
            
            $this->logBackupEvent('BACKUP_RESTORED', [
                'backup_name' => $backupName,
                'files_restored' => $filesRestored,
                'pre_restore_backup' => $preRestoreBackup['backup_name'] ?? null
            ]);
            
            return [
                'success' => true,
                'message' => 'Backup restored successfully',
                'files_restored' => $filesRestored,
                'pre_restore_backup' => $preRestoreBackup['backup_name'] ?? null
            ];
            
        } catch (Exception $e) {
            // Clean up on error
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            $this->logBackupEvent('RESTORE_FAILED', [
                'backup_name' => $backupName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage(),
                'error_code' => 'RESTORE_FAILED'
            ];
        }
    }
    
    /**
     * Delete backup
     */
    public function deleteBackup($backupName) {
        $backupPath = $this->backupDir . $backupName;
        
        if (!file_exists($backupPath)) {
            return [
                'success' => false,
                'message' => 'Backup file not found',
                'error_code' => 'BACKUP_NOT_FOUND'
            ];
        }
        
        if (unlink($backupPath)) {
            $this->logBackupEvent('BACKUP_DELETED', ['backup_name' => $backupName]);
            
            return [
                'success' => true,
                'message' => 'Backup deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to delete backup',
                'error_code' => 'DELETE_FAILED'
            ];
        }
    }
    
    /**
     * Get backup statistics
     */
    public function getBackupStats() {
        $backups = $this->listBackups();
        
        $stats = [
            'total_backups' => count($backups),
            'total_size' => 0,
            'oldest_backup' => null,
            'newest_backup' => null,
            'backup_types' => [],
            'daily_stats' => []
        ];
        
        foreach ($backups as $backup) {
            $stats['total_size'] += $backup['size'];
            
            // Track backup types
            $type = $backup['type'];
            $stats['backup_types'][$type] = ($stats['backup_types'][$type] ?? 0) + 1;
            
            // Track daily stats
            $date = date('Y-m-d', $backup['created_at']);
            if (!isset($stats['daily_stats'][$date])) {
                $stats['daily_stats'][$date] = ['count' => 0, 'size' => 0];
            }
            $stats['daily_stats'][$date]['count']++;
            $stats['daily_stats'][$date]['size'] += $backup['size'];
        }
        
        if (count($backups) > 0) {
            $stats['oldest_backup'] = end($backups);
            $stats['newest_backup'] = $backups[0];
        }
        
        $stats['total_size_formatted'] = $this->formatFileSize($stats['total_size']);
        $stats['average_size'] = count($backups) > 0 ? $stats['total_size'] / count($backups) : 0;
        $stats['average_size_formatted'] = $this->formatFileSize($stats['average_size']);
        
        return $stats;
    }
    
    /**
     * Check if automatic backup should run
     */
    public function shouldAutoBackup() {
        if (!defined('AUTO_BACKUP') || AUTO_BACKUP !== 'true') {
            return false;
        }
        
        $backups = $this->listBackups();
        
        // Check if we need hourly backup
        $lastAutoBackup = null;
        foreach ($backups as $backup) {
            if ($backup['type'] === 'auto') {
                $lastAutoBackup = $backup['created_at'];
                break;
            }
        }
        
        // If no auto backup exists or last one is older than 1 hour
        return !$lastAutoBackup || (time() - $lastAutoBackup) > 3600;
    }
    
    /**
     * Run automatic backup if needed
     */
    public function runAutoBackup() {
        if ($this->shouldAutoBackup()) {
            return $this->createBackup('auto');
        }
        
        return [
            'success' => false,
            'message' => 'Auto backup not needed',
            'error_code' => 'NOT_NEEDED'
        ];
    }
    
    // Private helper methods
    
    private function getFilesToBackup() {
        $files = [];
        
        // Core data files
        if (defined('CURRENT_SONG_FILE')) {
            $files[] = [
                'path' => CURRENT_SONG_FILE,
                'relative' => 'data/current_song.json',
                'description' => 'Current song data'
            ];
        }
        
        if (defined('HISTORY_FILE')) {
            $files[] = [
                'path' => HISTORY_FILE,
                'relative' => 'data/history.json',
                'description' => 'Song history'
            ];
        }
        
        if (defined('LOG_FILE')) {
            $files[] = [
                'path' => LOG_FILE,
                'relative' => 'logs/system.log',
                'description' => 'System log'
            ];
        }
        
        if (defined('METADATA_CACHE_FILE')) {
            $files[] = [
                'path' => METADATA_CACHE_FILE,
                'relative' => 'cache/metadata.json',
                'description' => 'Metadata cache'
            ];
        }
        
        if (defined('TRIVIA_CACHE_FILE')) {
            $files[] = [
                'path' => TRIVIA_CACHE_FILE,
                'relative' => 'cache/trivia.json',
                'description' => 'AI trivia cache'
            ];
        }
        
        if (defined('ENRICHMENT_STATUS_FILE')) {
            $files[] = [
                'path' => ENRICHMENT_STATUS_FILE,
                'relative' => 'data/enrichment_status.json',
                'description' => 'Enrichment status'
            ];
        }
        
        // AI cache directory
        if (defined('AI_CACHE_DIR') && is_dir(AI_CACHE_DIR)) {
            $files[] = [
                'path' => AI_CACHE_DIR,
                'relative' => 'cache/ai/',
                'description' => 'AI content cache'
            ];
        }
        
        return $files;
    }
    
    private function getAbsolutePath($relativePath) {
        $mapping = [
            'data/current_song.json' => CURRENT_SONG_FILE ?? 'data/current_song.json',
            'data/history.json' => HISTORY_FILE ?? 'data/history.json',
            'logs/system.log' => LOG_FILE ?? 'logs/system.log',
            'cache/metadata.json' => METADATA_CACHE_FILE ?? 'cache/metadata.json',
            'cache/trivia.json' => TRIVIA_CACHE_FILE ?? 'cache/trivia.json',
            'data/enrichment_status.json' => ENRICHMENT_STATUS_FILE ?? 'data/enrichment_status.json',
            'cache/ai/' => AI_CACHE_DIR ?? 'cache/ai/'
        ];
        
        return $mapping[$relativePath] ?? $relativePath;
    }
    
    private function copyDirectory($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }
    
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        return rmdir($dir);
    }
    
    private function getDirectorySize($dir) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    private function createTarGz($sourceDir, $archivePath) {
        if (!class_exists('PharData')) {
            // Fallback to shell command if PharData not available
            $command = "cd " . escapeshellarg(dirname($sourceDir)) . " && tar -czf " . 
                      escapeshellarg($archivePath) . " " . escapeshellarg(basename($sourceDir));
            
            exec($command, $output, $returnCode);
            return $returnCode === 0;
        }
        
        try {
            $phar = new PharData($archivePath);
            $phar->buildFromDirectory($sourceDir);
            return true;
        } catch (Exception $e) {
            error_log("Backup compression error: " . $e->getMessage());
            return false;
        }
    }
    
    private function extractTarGz($archivePath, $destDir) {
        if (!class_exists('PharData')) {
            // Fallback to shell command if PharData not available
            $command = "cd " . escapeshellarg($destDir) . " && tar -xzf " . escapeshellarg($archivePath);
            exec($command, $output, $returnCode);
            return $returnCode === 0;
        }
        
        try {
            $phar = new PharData($archivePath);
            $phar->extractTo($destDir, null, true);
            return true;
        } catch (Exception $e) {
            error_log("Backup extraction error: " . $e->getMessage());
            return false;
        }
    }
    
    private function parseBackupName($basename) {
        $pattern = '/' . preg_quote(BACKUP_PREFIX, '/') . '(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})_(.+)/';
        
        if (preg_match($pattern, $basename, $matches)) {
            $timestamp = DateTime::createFromFormat('Y-m-d_H-i-s', $matches[1]);
            
            return [
                'timestamp' => $timestamp ? $timestamp->getTimestamp() : 0,
                'type' => $matches[2]
            ];
        }
        
        return null;
    }
    
    private function getBackupManifest($archivePath) {
        try {
            if (class_exists('PharData')) {
                $phar = new PharData($archivePath);
                $manifestContent = $phar['backup_manifest.json']->getContent();
                return json_decode($manifestContent, true);
            }
        } catch (Exception $e) {
            // Ignore errors when reading manifest
        }
        
        return null;
    }
    
    private function cleanupOldBackups() {
        $backups = $this->listBackups();
        
        if (count($backups) > $this->maxBackups) {
            $toDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($toDelete as $backup) {
                unlink($backup['path']);
                $this->logBackupEvent('BACKUP_CLEANUP', ['backup_name' => $backup['name']]);
            }
        }
    }
    
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    private function formatAge($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'm';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . 'h';
        } else {
            return floor($seconds / 86400) . 'd';
        }
    }
    
    private function logBackupEvent($event, $data = []) {
        $logData = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user' => Auth::getCurrentUser()['username'] ?? 'system'
        ], $data);
        
        $logMessage = "[{$logData['timestamp']}] [BACKUP] {$event}";
        if (!empty($data)) {
            $logMessage .= " - " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        if (defined('LOG_FILE')) {
            file_put_contents(LOG_FILE, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

// Main execution
try {
    $backupManager = new BackupManager();
    
    switch ($action) {
        case 'list':
            // List all backups
            $backups = $backupManager->listBackups();
            $stats = $backupManager->getBackupStats();
            
            ?>
            <!DOCTYPE html>
            <html lang="sk">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Backup Management - Ighi's Home Radio</title>
                <style>
                    /* Include the same styles as monitor.php */
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: #f8fafc; padding: 20px; line-height: 1.6; min-height: 100vh; }
                    .container { max-width: 1200px; margin: 0 auto; }
                    .header { text-align: center; margin-bottom: 30px; padding: 20px; background: rgba(255, 255, 255, 0.05); border-radius: 16px; backdrop-filter: blur(10px); }
                    .header h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 10px; background: linear-gradient(135deg, #16a34a, #7c3aed); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
                    .card { background: rgba(255, 255, 255, 0.05); border-radius: 16px; padding: 24px; border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); margin-bottom: 20px; }
                    .btn { background: linear-gradient(135deg, #60a5fa, #3b82f6); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease; font-weight: 600; border: none; cursor: pointer; }
                    .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(96, 165, 250, 0.3); }
                    .btn.danger { background: linear-gradient(135deg, #f87171, #dc2626); }
                    .btn.success { background: linear-gradient(135deg, #16a34a, #059669); }
                    .backup-list { display: grid; gap: 15px; }
                    .backup-item { background: rgba(255, 255, 255, 0.03); border-radius: 8px; padding: 15px; border: 1px solid rgba(255, 255, 255, 0.1); }
                    .backup-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
                    .backup-name { font-weight: 600; color: #60a5fa; }
                    .backup-type { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; text-transform: uppercase; font-weight: 600; }
                    .backup-type.manual { background: #16a34a; color: white; }
                    .backup-type.auto { background: #3b82f6; color: white; }
                    .backup-type.pre_restore { background: #d97706; color: white; }
                    .backup-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; color: #cbd5e1; font-size: 0.9rem; }
                    .backup-actions { margin-top: 10px; display: flex; gap: 10px; }
                    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
                    .stat-card { text-align: center; padding: 20px; background: rgba(255, 255, 255, 0.03); border-radius: 8px; }
                    .stat-value { font-size: 2rem; font-weight: 700; color: #16a34a; }
                    .stat-label { color: #cbd5e1; margin-top: 5px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>💾 Backup Management</h1>
                        <p>Ighi's Home Radio - Backup System</p>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stats['total_backups']; ?></div>
                            <div class="stat-label">Total Backups</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stats['total_size_formatted']; ?></div>
                            <div class="stat-label">Total Size</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $stats['average_size_formatted']; ?></div>
                            <div class="stat-label">Average Size</div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card">
                        <h2>Actions</h2>
                        <div style="display: flex; gap: 15px; margin-top: 15px;">
                            <a href="backup.php?action=create" class="btn success" onclick="return confirm('Create new backup?')">
                                📦 Create Manual Backup
                            </a>
                            <a href="monitor.php" class="btn">
                                🔙 Back to Monitor
                            </a>
                        </div>
                    </div>
                    
                    <!-- Backup List -->
                    <div class="card">
                        <h2>Available Backups (<?php echo count($backups); ?>)</h2>
                        
                        <?php if (empty($backups)): ?>
                            <p style="color: #64748b; font-style: italic; margin-top: 15px;">No backups found</p>
                        <?php else: ?>
                            <div class="backup-list" style="margin-top: 20px;">
                                <?php foreach ($backups as $backup): ?>
                                    <div class="backup-item">
                                        <div class="backup-header">
                                            <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                                            <div class="backup-type <?php echo $backup['type']; ?>"><?php echo $backup['type']; ?></div>
                                        </div>
                                        
                                        <div class="backup-details">
                                            <div><strong>Created:</strong> <?php echo $backup['created_date']; ?></div>
                                            <div><strong>Size:</strong> <?php echo $backup['size_formatted']; ?></div>
                                            <div><strong>Age:</strong> <?php echo $backup['age']; ?></div>
                                            <?php if ($backup['manifest']): ?>
                                                <div><strong>Files:</strong> <?php echo count($backup['manifest']['files']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="backup-actions">
                                            <a href="backup.php?action=restore&backup=<?php echo urlencode($backup['name']); ?>" 
                                               class="btn" 
                                               onclick="return confirm('Restore from this backup? Current data will be backed up first.')">
                                                🔄 Restore
                                            </a>
                                            <a href="backup.php?action=delete&backup=<?php echo urlencode($backup['name']); ?>" 
                                               class="btn danger" 
                                               onclick="return confirm('Delete this backup permanently?')">
                                                🗑️ Delete
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </body>
            </html>
            <?php
            break;
            
        case 'create':
            // Create manual backup
            $result = $backupManager->createBackup('manual');
            
            if ($result['success']) {
                header('Location: backup.php?action=list&success=' . urlencode($result['message']));
            } else {
                header('Location: backup.php?action=list&error=' . urlencode($result['message']));
            }
            exit();
            
        case 'restore':
            // Restore from backup
            $backupName = $_GET['backup'] ?? '';
            
            if (empty($backupName)) {
                header('Location: backup.php?action=list&error=No backup specified');
                exit();
            }
            
            $result = $backupManager->restoreBackup($backupName);
            
            if ($result['success']) {
                header('Location: backup.php?action=list&success=' . urlencode($result['message']));
            } else {
                header('Location: backup.php?action=list&error=' . urlencode($result['message']));
            }
            exit();
            
        case 'delete':
            // Delete backup
            $backupName = $_GET['backup'] ?? '';
            
            if (empty($backupName)) {
                header('Location: backup.php?action=list&error=No backup specified');
                exit();
            }
            
            $result = $backupManager->deleteBackup($backupName);
            
            if ($result['success']) {
                header('Location: backup.php?action=list&success=' . urlencode($result['message']));
            } else {
                header('Location: backup.php?action=list&error=' . urlencode($result['message']));
            }
            exit();
            
        case 'stats':
            // Return backup statistics as JSON
            header('Content-Type: application/json');
            echo json_encode($backupManager->getBackupStats());
            break;
            
        case 'health':
            // Health check for backup system
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'online',
                'backup_enabled' => $backupManager->backupEnabled,
                'should_auto_backup' => $backupManager->shouldAutoBackup(),
                'backup_count' => count($backupManager->listBackups()),
                'max_backups' => $backupManager->maxBackups
            ]);
            break;
            
        case 'api_create':
            // API endpoint for creating backups (used by cron jobs)
            $token = $_GET['token'] ?? $_POST['token'] ?? '';
            
            if ($token !== ADMIN_TOKEN) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }
            
            $type = $_GET['type'] ?? 'auto';
            $result = $backupManager->createBackup($type);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            break;
            
        default:
            header('Location: backup.php?action=list');
            exit();
    }
    
} catch (Exception $e) {
    if ($action === 'list') {
        ?>
        <!DOCTYPE html>
        <html lang="sk">
        <head>
            <meta charset="UTF-8">
            <title>Backup System Error</title>
        </head>
        <body>
            <h1>Backup System Error</h1>
            <p>Error: <?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href="monitor.php">Back to Monitor</a>
        </body>
        </html>
        <?php
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 'SYSTEM_ERROR'
        ]);
    }
}
?>