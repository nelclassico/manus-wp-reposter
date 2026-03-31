<?php
/**
 * Plugin Name: Manus WP Reposter
 * Plugin URI: https://github.com/manus-ai/manus-wp-reposter
 * Description: Busca feeds RSS, extrai o conteúdo completo das notícias e as publica como posts no WordPress, com atribuição de crédito e tradução opcional.
 * Version: 2.4.1
 * Author: Emanoel de Oliveira
 * Author URI: https://neotecnow.com
 * License: GPL2
 * Text Domain: manus-wp-reposter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MANUS_WP_REPOSTER_VERSION', '2.4.0' );
define( 'MANUS_WP_REPOSTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANUS_WP_REPOSTER_URL', plugin_dir_url( __FILE__ ) );

final class Manus_WP_Reposter {

    private static $instance = null;
    private $admin      = null;
    private $importer   = null;
    private $translator = null;
    private $extractor  = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 20 );

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Hook do cron registrado aqui, garantindo execução independente do contexto
        add_action( 'manus_wp_reposter_daily_import', array( $this, 'run_cron_import' ) );

        // Verifica agendamento a cada carregamento de página
        add_action( 'wp_loaded', array( $this, 'ensure_cron_scheduled' ) );

        // Reagenda quando o usuário salva novo horário global
        add_action( 'update_option_manus_wp_reposter_daily_time',
            array( $this, 'reschedule_cron_on_time_change' ), 10, 2 );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'manus-wp-reposter', false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function init_plugin() {
        $this->load_dependencies();

        $this->extractor  = new Manus_WP_Reposter_Content_Extractor();
        $this->translator = new Manus_WP_Reposter_Translator();
        $this->importer   = new Manus_WP_Reposter_Importer( $this->translator, $this->extractor );

        if ( is_admin() ) {
            $this->admin = new Manus_WP_Reposter_Admin();
            $this->admin->set_importer( $this->importer );
        }
    }

    // -------------------------------------------------------------------------
    // Cron
    // -------------------------------------------------------------------------

    public function run_cron_import() {
        error_log( 'Manus WP Reposter: Cron disparado às ' . date( 'd/m/Y H:i:s' ) );

        // CORREÇÃO 3: se o importer não estiver pronto (init_plugin ainda não rodou),
        // instancia diretamente para garantir execução no contexto do cron
        if ( ! $this->importer ) {
            $this->load_dependencies();
            $this->extractor  = new Manus_WP_Reposter_Content_Extractor();
            $this->translator = new Manus_WP_Reposter_Translator();
            $this->importer   = new Manus_WP_Reposter_Importer( $this->translator, $this->extractor );
        }

        if ( $this->importer ) {
            $this->importer->run_daily_import();
        } else {
            error_log( 'Manus WP Reposter: ERRO CRÍTICO - Importador não pôde ser instanciado' );
        }
    }

    /**
     * Calcula o próximo timestamp respeitando o horário configurado e o timezone do WP.
     */
    private function get_next_scheduled_timestamp() {
        $daily_time = get_option( 'manus_wp_reposter_daily_time', '09:00' );
        if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $daily_time ) ) {
            $daily_time = '09:00';
        }

        list( $hour, $minute ) = explode( ':', $daily_time );
        $tz       = wp_timezone();
        $now      = new DateTimeImmutable( 'now', $tz );
        $next_run = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $now->format( 'Y-m-d' ) . " {$hour}:{$minute}:00",
            $tz
        );

        if ( $next_run <= $now ) {
            $next_run = $next_run->modify( '+1 day' );
        }

        return $next_run->getTimestamp();
    }

    /**
     * Garante que o cron está agendado corretamente.
     *
     * Quando há feeds agendados individualmente (com horários diferentes ao longo do dia),
     * usamos recorrência HORÁRIA — o importer decide quais feeds executar a cada hora.
     *
     * Quando só existe o feed principal (sem feeds agendados), usamos recorrência DIÁRIA
     * no horário configurado pelo usuário.
     */
    public function ensure_cron_scheduled() {
        $has_scheduled_feeds = $this->has_scheduled_feeds();

        if ( $has_scheduled_feeds ) {
            // Modo multi-feed: roda a cada hora para não perder nenhum horário configurado
            $scheduled = wp_next_scheduled( 'manus_wp_reposter_daily_import' );

            if ( ! $scheduled ) {
                // Agenda para daqui a 1 minuto para começar logo
                wp_schedule_event( time() + 60, 'hourly', 'manus_wp_reposter_daily_import' );
                error_log( 'Manus WP Reposter: Cron horário agendado (modo multi-feed)' );
                return;
            }

            // Se estava agendado como 'daily', reagenda como 'hourly'
            $recurrence = wp_get_schedule( 'manus_wp_reposter_daily_import' );
            if ( $recurrence !== 'hourly' ) {
                wp_unschedule_event( $scheduled, 'manus_wp_reposter_daily_import' );
                wp_schedule_event( time() + 60, 'hourly', 'manus_wp_reposter_daily_import' );
                error_log( 'Manus WP Reposter: Cron convertido para horário (modo multi-feed)' );
            }

        } else {
            // Modo feed único: agenda uma vez ao dia no horário configurado
            $scheduled = wp_next_scheduled( 'manus_wp_reposter_daily_import' );
            $expected  = $this->get_next_scheduled_timestamp();

            if ( ! $scheduled ) {
                wp_schedule_event( $expected, 'daily', 'manus_wp_reposter_daily_import' );
                error_log( 'Manus WP Reposter: Cron diário agendado para ' . date( 'd/m/Y H:i', $expected ) );
                return;
            }

            // Se estava agendado como 'hourly', reagenda como 'daily'
            $recurrence = wp_get_schedule( 'manus_wp_reposter_daily_import' );
            if ( $recurrence === 'hourly' ) {
                wp_unschedule_event( $scheduled, 'manus_wp_reposter_daily_import' );
                wp_schedule_event( $expected, 'daily', 'manus_wp_reposter_daily_import' );
                error_log( 'Manus WP Reposter: Cron convertido para diário ' . date( 'd/m/Y H:i', $expected ) );
                return;
            }

            // Corrige horário divergente (mais de 30 min de diferença)
            if ( abs( $scheduled - $expected ) > 1800 ) {
                wp_unschedule_event( $scheduled, 'manus_wp_reposter_daily_import' );
                wp_schedule_event( $expected, 'daily', 'manus_wp_reposter_daily_import' );
                error_log( 'Manus WP Reposter: Cron corrigido para ' . date( 'd/m/Y H:i', $expected ) );
            }
        }
    }

    /**
     * Verifica se há feeds agendados individualmente configurados
     */
    private function has_scheduled_feeds() {
        $feeds_json = get_option( 'manus_wp_reposter_scheduled_feeds', '[]' );
        $feeds      = json_decode( $feeds_json, true );
        return is_array( $feeds ) && ! empty( $feeds );
    }

    public function reschedule_cron_on_time_change( $old_value, $new_value ) {
        if ( $old_value === $new_value ) return;

        $timestamp = wp_next_scheduled( 'manus_wp_reposter_daily_import' );
        if ( $timestamp ) wp_unschedule_event( $timestamp, 'manus_wp_reposter_daily_import' );

        $next_run = $this->get_next_scheduled_timestamp();
        wp_schedule_event( $next_run, 'daily', 'manus_wp_reposter_daily_import' );
        error_log( sprintf(
            'Manus WP Reposter: Horário alterado de "%s" para "%s". Próxima execução: %s',
            $old_value, $new_value, date( 'd/m/Y H:i', $next_run )
        ) );
    }

    // -------------------------------------------------------------------------
    // Ativação / Desativação
    // -------------------------------------------------------------------------

    public function activate() {
        $this->load_dependencies();

        if ( class_exists( 'Manus_WP_Reposter_Logger' ) ) {
            Manus_WP_Reposter_Logger::instance()->create_table();
        }

        // Remove qualquer agendamento antigo e recria no horário correto
        $timestamp = wp_next_scheduled( 'manus_wp_reposter_daily_import' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'manus_wp_reposter_daily_import' );
        }

        $next_run = $this->get_next_scheduled_timestamp();
        wp_schedule_event( $next_run, 'daily', 'manus_wp_reposter_daily_import' );
        error_log( 'Manus WP Reposter: Ativado. Próxima execução: ' . date( 'd/m/Y H:i', $next_run ) );

        flush_rewrite_rules();
    }

    public function deactivate() {
        $timestamp = wp_next_scheduled( 'manus_wp_reposter_daily_import' );
        if ( $timestamp ) wp_unschedule_event( $timestamp, 'manus_wp_reposter_daily_import' );
        flush_rewrite_rules();
    }

    // -------------------------------------------------------------------------
    // Dependências
    // -------------------------------------------------------------------------

    private function load_dependencies() {
        $files = array(
            'debug'           => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-debug.php',
            'logger'          => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-logger.php',
            'admin'           => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-admin.php',
            'extractor'       => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-extractor.php',
            'image-processor' => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-image-processor.php',
            'functions'       => MANUS_WP_REPOSTER_PATH . 'includes/functions.php',
            'importer'        => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-importer.php',
            'translator'      => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-translator.php',
            'multi-feed'      => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-multi-feed.php',
            'automation'      => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-automation-settings.php',
            'ai-agent'        => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-ai-agent.php',
        );
        foreach ( $files as $file ) {
            if ( file_exists( $file ) ) require_once $file;
        }
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function get_translator() { return $this->translator; }
    public function get_extractor()  { return $this->extractor; }
    public function get_admin()      { return $this->admin; }
    public function get_importer()   { return $this->importer; }
}

Manus_WP_Reposter::instance();
