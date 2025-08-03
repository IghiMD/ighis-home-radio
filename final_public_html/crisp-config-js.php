<?php
// Crisp configuration loader
header('Content-Type: application/javascript');

// Load config
if (file_exists('config.php')) {
    require_once 'config.php';
}

// Get Crisp ID from config or environment
$crispId = defined('CRISP_WEBSITE_ID') ? CRISP_WEBSITE_ID : '';

// Generate JavaScript
?>
// Crisp Chat Configuration
(function() {
    <?php if (!empty($crispId)): ?>
    window.$crisp = [];
    window.CRISP_WEBSITE_ID = "<?php echo htmlspecialchars($crispId); ?>";
    
    var d = document;
    var s = d.createElement("script");
    s.src = "https://client.crisp.chat/l.js";
    s.async = 1;
    d.getElementsByTagName("head")[0].appendChild(s);
    
    window.CRISP_READY_TRIGGER = function() {
        // Set language
        if (typeof currentLanguage !== 'undefined') {
            $crisp.push(["set", "user:locale", currentLanguage]);
        }
        
        // Set current track info
        if (typeof currentTrack !== 'undefined' && currentTrack && currentTrack.title) {
            $crisp.push(["set", "session:data", [[
                ["currently_playing", currentTrack.artist + " - " + currentTrack.title]
            ]]]);
        }
    };
    
    console.log('Crisp chat initialized');
    <?php else: ?>
    console.log('Crisp chat not configured');
    <?php endif; ?>
})();