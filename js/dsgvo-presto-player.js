/**
 * DSGVO-konforme PrestoPlayer Integration
 * Implementiert eine 2-Klick-Lösung für YouTube-Videos
 */
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        // Warten, bis PrestoPlayer vollständig geladen ist
        waitForPrestoPlayer(function() {
            initConsentLayer();
        });
    });
    
    /**
     * Warten bis PrestoPlayer geladen ist
     */
    function waitForPrestoPlayer(callback) {
        if (document.querySelectorAll('.presto-player-wrapper').length > 0) {
            callback();
        } else {
            setTimeout(function() {
                waitForPrestoPlayer(callback);
            }, 50);
        }
    }
    
    /**
     * Consent-Layer für alle PrestoPlayer-Elemente initialisieren
     */
    function initConsentLayer() {
        // Alle PrestoPlayer-Elemente finden
        const prestoPlayers = document.querySelectorAll('.presto-player-wrapper');
        
        prestoPlayers.forEach(function(player) {
            // Prüfen, ob es sich um ein YouTube-Video handelt
            const isYouTube = player.querySelector('[data-provider="youtube"]') !== null;
            
            // Nur für YouTube-Videos den Consent-Layer anzeigen
            if (isYouTube) {
                // Original-Player speichern und ausblenden
                const originalContent = player.innerHTML;
                
                // Versuchen, die Video-ID zu extrahieren
                let videoId = '';
                const dataAttr = player.querySelector('[data-video-id]');
                const srcAttr = player.querySelector('iframe[src*="youtube"]');
                
                if (dataAttr) {
                    videoId = dataAttr.getAttribute('data-video-id');
                } else if (srcAttr) {
                    const src = srcAttr.getAttribute('src');
                    const match = src.match(/(?:youtube\.com\/embed\/|youtu\.be\/)([^?&]+)/);
                    if (match && match[1]) {
                        videoId = match[1];
                    }
                }
                
                // Thumbnail URL (bevorzugt aus PrestoPlayer, sonst von YouTube)
                let thumbnailUrl = player.querySelector('.presto-player-image')?.getAttribute('src');
                
                if (!thumbnailUrl && videoId) {
                    thumbnailUrl = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
                }
                
                // Wenn keine Thumbnail-URL gefunden wurde, Standard-Hintergrund verwenden
                if (!thumbnailUrl) {
                    thumbnailUrl = '#';
                }
                
                // Consent-Layer erstellen
                const consentLayer = document.createElement('div');
                consentLayer.className = 'presto-consent-layer';
                consentLayer.innerHTML = `
                    <div class="presto-consent-container">
                        <div class="presto-consent-thumbnail" style="background-image: url('${thumbnailUrl}')"></div>
                        <div class="presto-consent-message">
                            <h3>${dsgvoPrestoPlayer.consentTitle}</h3>
                            <p>${dsgvoPrestoPlayer.consentMessage}</p>
                            <button class="presto-consent-button">${dsgvoPrestoPlayer.consentButton}</button>
                        </div>
                    </div>
                `;
                
                // Original-Player durch Consent-Layer ersetzen
                player.innerHTML = '';
                player.appendChild(consentLayer);
                
                // Event-Listener für Consent-Button
                consentLayer.querySelector('.presto-consent-button').addEventListener('click', function() {
                    // Original-Player wiederherstellen
                    player.innerHTML = originalContent;
                    
                    // PrestoPlayer-Initialisierung auslösen
                    if (window.prestoPlayer && typeof window.prestoPlayer.initialize === 'function') {
                        window.prestoPlayer.initialize();
                    } else if (window.prestoPlayer && typeof window.prestoPlayer.init === 'function') {
                        window.prestoPlayer.init();
                    } else {
                        // Fallback: Versuchen, das Video manuell zu starten
                        setTimeout(function() {
                            const iframe = player.querySelector('iframe');
                            if (iframe) {
                                const src = iframe.getAttribute('src');
                                if (src && !src.includes('autoplay=1')) {
                                    iframe.setAttribute('src', src + (src.includes('?') ? '&' : '?') + 'autoplay=1');
                                }
                            }
                        }, 500);
                    }
                    
                    // Consent in SessionStorage speichern (optional)
                    try {
                        sessionStorage.setItem('presto-player-youtube-consent', 'true');
                    } catch (e) {
                        console.warn('SessionStorage nicht verfügbar');
                    }
                });
            }
        });
    }
})();
