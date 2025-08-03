// =========================================
// DYNAMICKÉ NAČÍTANIE UDALOSTÍ
// Pridajte tento kód do <script> sekcie v index.html
// =========================================

let currentEventData = null;
let eventUpdateInterval = null;

// Inicializácia dynamických udalostí
async function initializeDynamicEvents() {
    try {
        await loadEventData();
        updateEventDisplay();
        
        // Aktualizácia každých 30 sekúnd
        eventUpdateInterval = setInterval(loadEventData, 30000);
        
        debugLog('Dynamic events initialized');
    } catch (error) {
        debugLog('Error initializing dynamic events', error);
    }
}

// Načítanie údajov o udalosti z API
async function loadEventData() {
    try {
        const response = await fetch('event_api.php');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        if (data.success && data.data) {
            currentEventData = data.data;
            updateEventDisplay();
            debugLog('Event data loaded:', currentEventData);
        } else {
            throw new Error('Invalid event data received');
        }
    } catch (error) {
        debugLog('Error loading event data:', error);
        // Použij predvolené údaje ak API nefunguje
        useFallbackEventData();
    }
}

// Predvolené údaje ak API nefunguje
function useFallbackEventData() {
    currentEventData = {
        title: '"Hop alebo Trop" - Battle of Generations',
        description: 'Join us for our special 2-hour live show where Ighi and Kika battle it out with their music choices. Two generations, countless hits, one amazing radio experience!',
        formatted_date: '',
        formatted_time: '',
        image: 'https://i.imgur.com/6jMnbOD.jpeg',
        status: 'planning',
        is_upcoming: false
    };
    updateEventDisplay();
    debugLog('Using fallback event data');
}

// Aktualizácia zobrazenia udalosti na stránke
function updateEventDisplay() {
    if (!currentEventData) return;
    
    // Aktualizuj názov udalosti
    const titleElement = document.querySelector('[data-translate="show-title"]');
    if (titleElement) {
        titleElement.textContent = currentEventData.title;
    }
    
    // Aktualizuj popis
    const descElement = document.querySelector('[data-translate="show-description"]');
    if (descElement) {
        descElement.textContent = currentEventData.description;
    }
    
    // Aktualizuj obrázok
    const imageElement = document.querySelector('.family-photo img');
    if (imageElement && currentEventData.image) {
        imageElement.src = currentEventData.image;
        imageElement.alt = currentEventData.title;
    }
    
    // Aktualizuj dátum a čas
    updateEventSchedule();
    
    // Aktualizuj status indikátor
    updateEventStatus();
    
    debugLog('Event display updated');
}

// Aktualizácia dátumu a času
function updateEventSchedule() {
    if (!currentEventData) return;
    
    const scheduleElement = document.querySelector('.schedule-notice p');
    if (scheduleElement) {
        let scheduleText = '';
        
        if (currentEventData.formatted_date && currentEventData.formatted_time) {
            const statusTexts = {
                'planning': '🗓️ Plánované na',
                'announced': '📅 Naplánované na',
                'live': '🔴 LIVE TERAZ!',
                'completed': '✅ Ukončené'
            };
            
            const statusText = statusTexts[currentEventData.status] || '📅';
            
            if (currentEventData.status === 'live') {
                scheduleText = '🔴 LIVE SHOW PRÁVE PREBÍHA!';
                scheduleElement.style.background = 'linear-gradient(135deg, rgba(255, 45, 85, 0.3), rgba(255, 140, 0, 0.3))';
                scheduleElement.style.borderColor = '#ff2d55';
                scheduleElement.style.animation = 'pulse 2s infinite';
            } else if (currentEventData.status === 'completed') {
                scheduleText = '✅ Show ukončená - ďakujeme za sledovanie!';
                scheduleElement.style.background = 'linear-gradient(135deg, rgba(0, 255, 136, 0.2), rgba(0, 180, 216, 0.2))';
                scheduleElement.style.borderColor = '#00ff88';
                scheduleElement.style.animation = 'none';
            } else {
                scheduleText = `${statusText} ${currentEventData.formatted_date} o ${currentEventData.formatted_time}`;
                scheduleElement.style.background = 'linear-gradient(135deg, rgba(255, 45, 85, 0.1), rgba(255, 140, 0, 0.1))';
                scheduleElement.style.borderColor = '#ff8c00';
                scheduleElement.style.animation = 'none';
            }
        } else {
            scheduleText = '📅 Termín bude oznámený čoskoro!';
            scheduleElement.style.background = 'linear-gradient(135deg, rgba(255, 45, 85, 0.1), rgba(255, 140, 0, 0.1))';
            scheduleElement.style.borderColor = '#ff8c00';
            scheduleElement.style.animation = 'none';
        }
        
        scheduleElement.textContent = scheduleText;
    }
}

// Aktualizácia status indikátora
function updateEventStatus() {
    if (!currentEventData) return;
    
    // Pridaj status badge ak neexistuje
    let statusBadge = document.querySelector('.event-status-badge');
    if (!statusBadge) {
        statusBadge = document.createElement('div');
        statusBadge.className = 'event-status-badge';
        statusBadge.style.cssText = `
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
        `;
        
        const hopTropInfo = document.querySelector('.hop-trop-info');
        if (hopTropInfo) {
            hopTropInfo.style.position = 'relative';
            hopTropInfo.appendChild(statusBadge);
        }
    }
    
    // Nastavenie vzhľadu podľa statusu
    const statusStyles = {
        'planning': { bg: '#ffc107', color: '#000', text: '🎯 Plánovanie' },
        'announced': { bg: '#17a2b8', color: '#fff', text: '📢 Ohlásené' },
        'live': { bg: '#dc3545', color: '#fff', text: '🔴 NAŽIVO' },
        'completed': { bg: '#28a745', color: '#fff', text: '✅ Ukončené' }
    };
    
    const style = statusStyles[currentEventData.status] || statusStyles.planning;
    statusBadge.style.background = style.bg;
    statusBadge.style.color = style.color;
    statusBadge.textContent = style.text;
    
    // Animácia pre live status
    if (currentEventData.status === 'live') {
        statusBadge.style.animation = 'pulse 1.5s ease-in-out infinite';
    } else {
        statusBadge.style.animation = 'none';
    }
}

// Funkcia na manuálne obnovenie údajov
function refreshEventData() {
    loadEventData();
    showNotification('Údaje o udalosti obnovené', 'success');
}

// CSS animácie (pridať do <style> sekcie)
const eventAnimationCSS = `
@keyframes eventPulse {
    0%, 100% { 
        transform: scale(1);
        opacity: 1;
    }
    50% { 
        transform: scale(1.02);
        opacity: 0.9;
    }
}

.event-live-indicator {
    animation: eventPulse 2s ease-in-out infinite;
}

.event-status-badge {
    transition: all 0.3s ease;
}

.event-status-badge:hover {
    transform: scale(1.1);
}
`;

// Pridanie CSS do stránky
function addEventCSS() {
    const style = document.createElement('style');
    style.textContent = eventAnimationCSS;
    document.head.appendChild(style);
}

// Inicializácia pri načítaní stránky
document.addEventListener('DOMContentLoaded', function() {
    addEventCSS();
    
    // Počkaj na načítanie API config, potom inicializuj events
    setTimeout(() => {
        initializeDynamicEvents();
    }, 2000);
});

// Pridanie do window objektu pre debugging
window.refreshEventData = refreshEventData;
window.currentEventData = () => currentEventData;