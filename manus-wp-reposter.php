<?php
/**
 * Plugin Name: Manus WP Reposter
 * Plugin URI: https://github.com/manus-ai/manus-wp-reposter
 * Description: Busca feeds RSS, extrai o conteúdo completo das notícias e as publica como posts no WordPress, com atribuição de crédito e tradução opcional.
 * Version: 2.0.0
 * Author: Emanoel de Oliveira  
 * Author URI: https://neotecnow.com
 * License: GPL2
 * Text Domain: manus-wp-reposter
 */

// Previne acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constantes do plugin
define( 'MANUS_WP_REPOSTER_VERSION', '2.0.0' );
define( 'MANUS_WP_REPOSTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'MANUS_WP_REPOSTER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Classe principal do plugin
 */
final class Manus_WP_Reposter {
    
    private static $instance = null;
    private $admin = null;
    private $importer = null;
    private $translator = null;
    private $extractor = null;
    private $logger = null;
    
    /**
     * Singleton instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor privado
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializa hooks
     */
    private function init_hooks() {
        // Inicializa quando plugins são carregados
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 20 ); // Aumenta prioridade
        
        // Hooks de ativação/desativação
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        
        // Carrega traduções no init para evitar warning
        add_action( 'init', array( $this, 'load_textdomain' ) );
    }
    
    /**
     * Carrega traduções
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'manus-wp-reposter',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );
    }
    
    /**
     * Inicializa o plugin
     */
    public function init_plugin() {
        // Carrega dependências com verificação
        $this->load_dependencies();
        
        // Inicializa componentes
        $this->extractor = new Manus_WP_Reposter_Content_Extractor();
        $this->translator = new Manus_WP_Reposter_Translator();
        
        // O importador precisa das outras classes, então passa por parâmetro
        $this->importer = new Manus_WP_Reposter_Importer( $this->translator, $this->extractor );
        
        if ( is_admin() ) {
            $this->admin = new Manus_WP_Reposter_Admin();
            
            // PASSA a instância do importador para o admin
            $this->admin->set_importer( $this->importer );
        }
        
        // Hooks de ação
        add_action( 'manus_wp_reposter_daily_import', array( $this->importer, 'run_daily_import' ) );
    }
    
    /**
     * Obtém instância do tradutor
     */
    public function get_translator() {
        return $this->translator;
    }
    
    /**
     * Obtém instância do extrator
     */
    public function get_extractor() {
        return $this->extractor;
    }
    
    /**
     * Obtém instância do administrador
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Obtém instância do importador
     */
    public function get_importer() {
        return $this->importer;
    }
    
    /**
     * Obtém instância do logger
     */
    public function get_logger() {
        // Carrega logger se ainda não carregado
        if ( ! $this->logger && class_exists( 'Manus_WP_Reposter_Logger' ) ) {
            $this->logger = Manus_WP_Reposter_Logger::instance();
        }
        return $this->logger;
    }
    
    /**
     * Carrega dependências com verificação
     */
    private function load_dependencies() {
        // Lista de arquivos na ORDEM CORRETA (alfabética para clareza)
        $files = array(
            'debug'      => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-debug.php',
            'logger'     => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-logger.php',
            'admin'      => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-admin.php',
            'extractor'  => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-extractor.php',
            'functions'  => MANUS_WP_REPOSTER_PATH . 'includes/functions.php',
            'importer'   => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-importer.php',
            'translator' => MANUS_WP_REPOSTER_PATH . 'includes/class-manus-wp-reposter-translator.php',
        );
        
        foreach ( $files as $key => $file ) {
            if ( file_exists( $file ) ) {
                require_once $file;
                error_log("Manus: Carregado $key");
            } else {
                error_log("Manus: ERRO - Arquivo não encontrado: $file");
                // Cria arquivo básico se não existir
                $this->create_basic_file( $key, $file );
                if ( file_exists( $file ) ) {
                    require_once $file;
                }
            }
        }
    }
    
    /**
     * Cria arquivo básico se não existir
     */
    private function create_basic_file( $type, $filepath ) {
        $content = '';
        
        switch ( $type ) {
            case 'extractor':
                $content = '<?php
class Manus_WP_Reposter_Content_Extractor {
    public function extract_content( $url ) {
        return array(
            "success" => false,
            "title" => "",
            "content" => "",
            "featured_image" => "",
            "images" => array(),
            "metadata" => array(),
        );
    }
}';
                break;
                
            case 'translator':
                $content = '<?php
class Manus_WP_Reposter_Translator {
    public function detect_language( $text ) {
        return "en";
    }
    
    public function translate_text( $text, $source, $target ) {
        return $text;
    }
    
    public function translate_html( $html, $source, $target ) {
        return $html;
    }
}';
                break;
                
            case 'importer':
                $content = '<?php
class Manus_WP_Reposter_Importer {
    private $translator;
    private $extractor;
    
    public function __construct( $translator = null, $extractor = null ) {
        $this->translator = $translator;
        $this->extractor = $extractor;
    }
    
    public function run_daily_import() {
        error_log( "Manus WP Reposter: Importador diário executado" );
    }
}';
                break;
                
            case 'functions':
                $content = '<?php
function manus_log( $level, $message, $context = null ) {
    // Implementação básica
    error_log( "[$level] $message" );
}';
                break;
        }
        
        if ( ! empty( $content ) ) {
            // Cria diretório se não existir
            $dir = dirname( $filepath );
            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0755, true );
            }
            
            file_put_contents( $filepath, $content );
        }
    }
    
    /**
     * Ativação do plugin
     */
    public function activate() {
        // Agenda importação diária
        if ( ! wp_next_scheduled( 'manus_wp_reposter_daily_import' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'manus_wp_reposter_daily_import' );
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Desativação do plugin
     */
    public function deactivate() {
        // Remove agendamento
        $timestamp = wp_next_scheduled( 'manus_wp_reposter_daily_import' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'manus_wp_reposter_daily_import' );
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Agenda trabalhos cron
     */
    public function schedule_cron_jobs() {
        // Garante que o cron job está agendado
        if ( ! wp_next_scheduled( 'manus_wp_reposter_daily_import' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'manus_wp_reposter_daily_import' );
        }
    }
}

// Inicializa o plugin com prioridade
add_action( 'plugins_loaded', function() {
    Manus_WP_Reposter::instance();
}, 15 );