<?php
/**
 * Plugin Name: DSGVO-konforme PrestoPlayer Integration
 * Description: Implementiert eine 2-Klick-Lösung für PrestoPlayer zur DSGVO-konformen Einbindung von YouTube-Videos
 * Version: 0.1
 * Author: Macbay Digital
 * Text Domain: dsgvo-presto-player
 */

// Sicherheitsabfrage
if (!defined('ABSPATH')) {
    exit;
}

class DSGVO_PrestoPlayer {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Hooks registrieren
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'admin_notice'));
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
                '1.0',
                true
            );
            
            wp_enqueue_style(
                'dsgvo-presto-player',
                plugin_dir_url(__FILE__) . 'css/dsgvo-presto-player.css',
                array(),
                '1.0'
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
}

// Plugin initialisieren
new DSGVO_PrestoPlayer();
