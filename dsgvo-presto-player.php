<?php
/**
 * Plugin Name: DSGVO-konforme PrestoPlayer Integration
 * Description: Implementiert eine 2-Klick-Lösung für PrestoPlayer zur DSGVO-konformen Einbindung von YouTube-Videos
 * Version: 1.0.0
 * Author: Macbay Digital
 * Author URI: https://macbay.de
 * Text Domain: dsgvo-presto-player
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Sicherheitsabfrage
if (!defined('ABSPATH')) {
    exit;
}

class DSGVO_PrestoPlayer {
    
    // Plugin-Version
    private $version = '1.0.0';
    
    // GitHub Repository
    private $github_repo = 'macbaydigital/dsgvo-presto-player';
    private $github_api_url = 'https://api.github.com/repos/macbaydigital/dsgvo-presto-player/releases/latest';
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Hooks registrieren
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'admin_notice'));
        
        // Update-Funktionalität
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
        
        // Plugin aktivieren/deaktivieren
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Plugin aktivieren
     */
    public function activate() {
        // Hier können Aktivierungsaufgaben ausgeführt werden
    }
    
    /**
     * Plugin deaktivieren
     */
    public function deactivate() {
        // Hier können Deaktivierungsaufgaben ausgeführt werden
    }
    
    /**
     * Scripts und Styles laden
     */
    public function enqueue_scripts() {
        // Nur laden, wenn PrestoPlayer aktiv ist
        if (class_exists('\\PrestoPlayer\\Plugin')) {
            wp_enqueue_script(
                'dsgvo-presto-player',
                plugin_dir_url(__FILE__) . 'js/dsgvo-presto-player.js',
                array('jquery'),
                $this->version,
                true
            );
            
            wp_enqueue_style(
                'dsgvo-presto-player',
                plugin_dir_url(__FILE__) . 'css/dsgvo-presto-player.css',
                array(),
                $this->version
            );
            
            // Übersetzbare Texte für JavaScript bereitstellen
            wp_localize_script('dsgvo-presto-player', 'dsgvoPrestoPlayer', array(
                'consentTitle' => __('Externe Inhalte', 'dsgvo-presto-player'),
                'consentMessage' => __('Beim Abspielen wird eine Verbindung zu YouTube hergestellt und Ihre IP-Adresse übertragen. Mit Klick auf "Akzeptieren" stimmen Sie der Datenübertragung zu.', 'dsgvo-presto-player'),
                'consentButton' => __('Video abspielen', 'dsgvo-presto-player'),
            ));
        }
    }
    
    /**
     * Admin-Hinweis anzeigen, wenn PrestoPlayer nicht installiert ist
     */
    public function admin_notice() {
        if (!class_exists('\\PrestoPlayer\\Plugin')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('Das Plugin "DSGVO-konforme PrestoPlayer Integration" benötigt PrestoPlayer, um zu funktionieren.', 'dsgvo-presto-player'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Prüft auf Updates
     * 
     * @param object $transient Update-Transient
     * @return object Modifiziertes Update-Transient
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Plugin-Pfad und Slug
        $plugin_slug = plugin_basename(__FILE__);
        
        // Überprüfen, ob unser Plugin im Transient ist
        if (!isset($transient->checked[$plugin_slug])) {
            return $transient;
        }
        
        // GitHub API abfragen
        $response = $this->get_github_api_info();
        
        // Wenn die API-Anfrage fehlgeschlagen ist oder keine neuen Versionen verfügbar sind
        if (is_wp_error($response) || !isset($response['tag_name'])) {
            return $transient;
        }
        
        // Version aus Tag-Name extrahieren (entfernt v-Präfix, falls vorhanden)
        $latest_version = ltrim($response['tag_name'], 'v');
        
        // Prüfen, ob eine neue Version verfügbar ist
        if (version_compare($this->version, $latest_version, '<')) {
            $item = array(
                'id'            => $plugin_slug,
                'slug'          => dirname($plugin_slug),
                'plugin'        => $plugin_slug,
                'new_version'   => $latest_version,
                'url'           => 'https://github.com/' . $this->github_repo,
                'package'       => isset($response['assets'][0]['browser_download_url']) 
                                  ? $response['assets'][0]['browser_download_url']
                                  : 'https://github.com/' . $this->github_repo . '/archive/refs/tags/' . $response['tag_name'] . '.zip',
                'icons'         => array(),
                'banners'       => array(),
                'banners_rtl'   => array(),
                'tested'        => '',
                'requires_php'  => '',
                'compatibility' => new stdClass(),
            );
            
            $transient->response[$plugin_slug] = (object) $item;
        }
        
        return $transient;
    }
    
    /**
     * Stellt Plugin-Informationen für das Detailfenster bereit
     * 
     * @param false|object|array $result Plugin-Informationen
     * @param string $action Aktion
     * @param object $args Argumente
     * @return object Plugin-Informationen
     */
    public function plugin_info($result, $action, $args) {
        // Nur für unser Plugin
        if ('plugin_information' !== $action || !isset($args->slug) || dirname(plugin_basename(__FILE__)) !== $args->slug) {
            return $result;
        }
        
        // GitHub API abfragen
        $response = $this->get_github_api_info();
        
        // Wenn die API-Anfrage fehlgeschlagen ist
        if (is_wp_error($response) || !isset($response['tag_name'])) {
            return $result;
        }
        
        // Version aus Tag-Name extrahieren
        $latest_version = ltrim($response['tag_name'], 'v');
        
        // Changelog aus Release-Body oder aus Datei extrahieren
        $changelog = isset($response['body']) ? $response['body'] : 'Änderungsprotokoll nicht verfügbar';
        
        // Plugin-Informationen erstellen
        $plugin_info = array(
            'name'              => 'DSGVO-konforme PrestoPlayer Integration',
            'slug'              => dirname(plugin_basename(__FILE__)),
            'version'           => $latest_version,
            'author'            => '<a href="https://macbay.de">Macbay Digital</a>',
            'author_profile'    => 'https://github.com/' . explode('/', $this->github_repo)[0],
            'last_updated'      => isset($response['published_at']) ? date('Y-m-d', strtotime($response['published_at'])) : '',
            'homepage'          => 'https://github.com/' . $this->github_repo,
            'short_description' => 'Implementiert eine 2-Klick-Lösung für PrestoPlayer zur DSGVO-konformen Einbindung von YouTube-Videos',
            'sections'          => array(
                'description'   => $this->get_plugin_description(),
                'installation'  => $this->get_plugin_installation(),
                'changelog'     => $changelog,
                'faq'           => $this->get_plugin_faq(),
            ),
            'download_link'     => isset($response['assets'][0]['browser_download_url']) 
                                  ? $response['assets'][0]['browser_download_url']
                                  : 'https://github.com/' . $this->github_repo . '/archive/refs/tags/' . $response['tag_name'] . '.zip',
        );
        
        return (object) $plugin_info;
    }
    
    /**
     * Nach dem Update aufräumen
     * 
     * @param bool $response Installations-Response
     * @param array $hook_extra Extra-Hooks
     * @param array $result Installations-Ergebnis
     * @return array Modifiziertes Installations-Ergebnis
     */
    public function after_update($response, $hook_extra, $result) {
        // Nur für unser Plugin
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === plugin_basename(__FILE__)) {
            // Stellen Sie sicher, dass das Plugin-Verzeichnis korrekt ist
            global $wp_filesystem;
            $plugin_folder = WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__));
            $wp_filesystem->move($result['destination'], $plugin_folder);
            $result['destination'] = $plugin_folder;
            
            // Plugin aktivieren
            activate_plugin(plugin_basename(__FILE__));
        }
        
        return $result;
    }
    
    /**
     * GitHub API abfragen
     * 
     * @return array|WP_Error API-Antwort oder Fehler
     */
    private function get_github_api_info() {
        // Transient für API-Cache
        $cache_key = 'dsgvo_presto_player_github_api';
        $response_body = get_transient($cache_key);
        
        // Cache verwenden, wenn verfügbar
        if (false === $response_body) {
            // GitHub API abfragen
            $response = wp_remote_get($this->github_api_url, array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                ),
            ));
            
            // Fehler bei der API-Anfrage
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                return new WP_Error('github_api_error', 'Fehler beim Abrufen der GitHub-API');
            }
            
            // API-Antwort decodieren
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            
            // Cache für 6 Stunden setzen
            set_transient($cache_key, $response_body, 6 * HOUR_IN_SECONDS);
        }
        
        return $response_body;
    }
    
    /**
     * Plugin-Beschreibung
     * 
     * @return string HTML-formatierte Beschreibung
     */
    private function get_plugin_description() {
        return '
        <h2>DSGVO-konforme PrestoPlayer Integration</h2>
        <p>Dieses Plugin implementiert eine 2-Klick-Lösung für PrestoPlayer zur DSGVO-konformen Einbindung von YouTube-Videos.</p>
        
        <h3>Funktionen</h3>
        <ul>
            <li>Verhindert die automatische Verbindung zu YouTube beim Seitenaufruf</li>
            <li>Zeigt einen Consent-Layer mit anpassbarem Text an</li>
            <li>Lädt das Video erst nach ausdrücklicher Zustimmung des Besuchers</li>
            <li>Funktioniert mit allen bestehenden PrestoPlayer-Blöcken</li>
            <li>Keine Konfiguration notwendig</li>
        </ul>
        
        <h3>Vorteile</h3>
        <ul>
            <li>Vollständig DSGVO-konform</li>
            <li>Keine externen Verbindungen ohne Einwilligung</li>
            <li>Einfache Installation und Verwendung</li>
            <li>Funktioniert mit bestehenden PrestoPlayer-Inhalten</li>
            <li>Anpassbar an Ihre Design-Anforderungen</li>
            <li>Mehrsprachig dank WordPress-Übersetzungsfunktionen</li>
        </ul>
        ';
    }
    
    /**
     * Plugin-Installationsanleitung
     * 
     * @return string HTML-formatierte Installationsanleitung
     */
    private function get_plugin_installation() {
        return '
        <h2>Installation</h2>
        <ol>
            <li>Laden Sie das Plugin herunter</li>
            <li>Gehen Sie zu Plugins > Installieren > Hochladen</li>
            <li>Wählen Sie die ZIP-Datei aus und klicken Sie auf "Jetzt installieren"</li>
            <li>Aktivieren Sie das Plugin</li>
        </ol>
        
        <h3>Anforderungen</h3>
        <ul>
            <li>WordPress 5.0 oder höher</li>
            <li>PrestoPlayer Plugin (installiert und aktiviert)</li>
        </ul>
        ';
    }
    
    /**
     * Plugin-FAQ
     * 
     * @return string HTML-formatierte FAQ
     */
    private function get_plugin_faq() {
        return '
        <h2>Häufig gestellte Fragen</h2>
        
        <h3>Funktioniert das Plugin mit allen PrestoPlayer-Versionen?</h3>
        <p>Das Plugin wurde mit den aktuellen Versionen von PrestoPlayer getestet und sollte mit allen neueren Versionen kompatibel sein.</p>
        
        <h3>Kann ich die Texte des Consent-Layers anpassen?</h3>
        <p>Ja, die Texte können über die WordPress-Übersetzungsfunktionen angepasst werden. Alternativ können Sie auch den Code des Plugins anpassen.</p>
        
        <h3>Werden auch andere Video-Quellen als YouTube unterstützt?</h3>
        <p>Aktuell unterstützt das Plugin nur YouTube-Videos. Weitere Video-Quellen können in zukünftigen Versionen hinzugefügt werden.</p>
        
        <h3>Ist eine Konfiguration notwendig?</h3>
        <p>Nein, das Plugin funktioniert sofort nach der Aktivierung ohne weitere Konfiguration.</p>
        ';
    }
}

// Plugin initialisieren
new DSGVO_PrestoPlayer();
