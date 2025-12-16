<?php
/**
 * Sistema de logs usando posts do WordPress
 */
class Manus_WP_Reposter_Logger {
    
    private static $instance = null;
    private $log_post_type = 'manus_log';
    
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
     * Construtor
     */
    private function __construct() {
        // Registra o post type na inicialização
        add_action( 'init', array( $this, 'register_log_post_type' ) );
    }
    
    /**
     * Registra o tipo de post para logs
     */
    public function register_log_post_type() {
        register_post_type( $this->log_post_type, array(
            'labels' => array(
                'name' => __( 'Logs do Reposter', 'manus-wp-reposter' ),
                'singular_name' => __( 'Log', 'manus-wp-reposter' ),
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'do_not_allow', // Ninguém pode criar posts manualmente
            ),
            'map_meta_cap' => true,
            'supports' => array( 'title', 'editor', 'custom-fields' ),
        ) );
    }
    
    /**
     * Adiciona um log
     */
    public function add_log( $level, $message, $context = null ) {
        // Prepara os dados do post
        $post_title = wp_trim_words( sanitize_text_field( $message ), 10, '...' );
        
        $post_data = array(
            'post_title'   => $post_title,
            'post_content' => wp_kses_post( $message ),
            'post_status'  => 'publish',
            'post_type'    => $this->log_post_type,
            'post_date'    => current_time( 'mysql' ),
            'post_date_gmt' => current_time( 'mysql', 1 ),
        );
        
        // Insere o post
        $post_id = wp_insert_post( $post_data );
        
        if ( is_wp_error( $post_id ) || ! $post_id ) {
            // Fallback para error_log se não conseguir criar o post
            error_log( "[Manus $level] $message" );
            if ( $context ) {
                error_log( "Context: " . print_r( $context, true ) );
            }
            return false;
        }
        
        // Salva metadados
        update_post_meta( $post_id, '_manus_log_level', sanitize_text_field( $level ) );
        update_post_meta( $post_id, '_manus_log_timestamp', current_time( 'timestamp' ) );
        
        if ( $context !== null ) {
            update_post_meta( $post_id, '_manus_log_context', 
                is_array( $context ) || is_object( $context ) 
                    ? wp_json_encode( $context, JSON_PRETTY_PRINT )
                    : sanitize_textarea_field( $context )
            );
        }
        
        // Limita o número de logs (mantém apenas os últimos 1000)
        $this->limit_logs();
        
        return $post_id;
    }
    
    /**
     * Limita o número de logs
     */
    private function limit_logs() {
        $args = array(
            'post_type'      => $this->log_post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'ASC', // Mais antigos primeiro
        );
        
        $log_ids = get_posts( $args );
        
        // Se tiver mais de 1000 logs, remove os mais antigos
        if ( count( $log_ids ) > 1000 ) {
            $to_delete = array_slice( $log_ids, 0, count( $log_ids ) - 1000 );
            
            foreach ( $to_delete as $log_id ) {
                wp_delete_post( $log_id, true );
            }
        }
    }
    
    /**
     * Obtém logs
     */
    public function get_logs( $args = array() ) {
        $defaults = array(
            'post_type'      => $this->log_post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'paged'          => 1,
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        // Filtro por nível
        if ( ! empty( $args['level'] ) ) {
            $args['meta_query'] = array(
                array(
                    'key'   => '_manus_log_level',
                    'value' => sanitize_text_field( $args['level'] ),
                ),
            );
            unset( $args['level'] );
        }
        
        // Busca por termo
        if ( ! empty( $args['search'] ) ) {
            $args['s'] = sanitize_text_field( $args['search'] );
            unset( $args['search'] );
        }
        
        $query = new WP_Query( $args );
        
        $logs = array();
        foreach ( $query->posts as $post ) {
            $level = get_post_meta( $post->ID, '_manus_log_level', true );
            $timestamp = get_post_meta( $post->ID, '_manus_log_timestamp', true );
            $context = get_post_meta( $post->ID, '_manus_log_context', true );
            
            // Tenta decodificar JSON se for contexto estruturado
            if ( $context ) {
                $decoded = json_decode( $context, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $context = $decoded;
                }
            }
            
            $logs[] = array(
                'id'        => $post->ID,
                'time'      => $post->post_date,
                'timestamp' => $timestamp ?: strtotime( $post->post_date ),
                'level'     => $level ?: 'INFO',
                'message'   => $post->post_content,
                'context'   => $context,
            );
        }
        
        return array(
            'logs'  => $logs,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        );
    }
    
    /**
     * Conta logs por nível
     */
    public function count_logs_by_level() {
        $levels = array( 'INFO', 'WARNING', 'ERROR', 'SUCCESS' );
        $counts = array();
        
        foreach ( $levels as $level ) {
            $args = array(
                'post_type'      => $this->log_post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_manus_log_level',
                        'value' => $level,
                    ),
                ),
            );
            
            $query = new WP_Query( $args );
            $counts[ $level ] = $query->found_posts;
        }
        
        return $counts;
    }
    
    /**
     * Limpa todos os logs
     */
    public function clear_logs() {
        $args = array(
            'post_type'      => $this->log_post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );
        
        $log_ids = get_posts( $args );
        $deleted_count = 0;
        
        foreach ( $log_ids as $log_id ) {
            if ( wp_delete_post( $log_id, true ) ) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Exporta logs em JSON
     */
    public function export_logs() {
        $logs_data = $this->get_logs( array( 'posts_per_page' => -1 ) );
        
        $export_data = array(
            'export_date' => current_time( 'mysql' ),
            'plugin_version' => defined( 'MANUS_WP_REPOSTER_VERSION' ) ? MANUS_WP_REPOSTER_VERSION : '1.0.0',
            'total_logs'  => count( $logs_data['logs'] ),
            'logs'        => $logs_data['logs'],
        );
        
        return $export_data;
    }
}