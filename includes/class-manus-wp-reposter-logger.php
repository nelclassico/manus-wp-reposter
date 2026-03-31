<?php
/**
 * Sistema de logs usando tabela SQL dedicada
 */
class Manus_WP_Reposter_Logger {

    private static $instance = null;
    private $table_name;

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'manus_reposter_logs';
        $this->maybe_create_table();
    }

    // -------------------------------------------------------------------------
    // Criação da tabela
    // -------------------------------------------------------------------------

    /**
     * Cria a tabela se ela ainda não existir.
     * Seguro de chamar múltiplas vezes.
     */
    public function maybe_create_table() {
        global $wpdb;

        // Verifica se a tabela já existe antes de rodar dbDelta
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name ) {
            return;
        }

        $this->create_table();
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id        bigint(20)   NOT NULL AUTO_INCREMENT,
            time      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level     varchar(20)  NOT NULL DEFAULT 'INFO',
            message   text         NOT NULL,
            context   longtext     DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY time  (time)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // -------------------------------------------------------------------------
    // Escrita
    // -------------------------------------------------------------------------

    /**
     * Adiciona um registro de log.
     *
     * @param string     $level   INFO | WARNING | ERROR | SUCCESS
     * @param string     $message Mensagem legível.
     * @param mixed|null $context Dados adicionais (array, objeto ou string).
     * @return int|false ID do registro inserido ou false em caso de falha.
     */
    public function add_log( $level, $message, $context = null ) {
        global $wpdb;

        $context_value = null;
        if ( $context !== null ) {
            $context_value = is_array( $context ) || is_object( $context )
                ? wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
                : sanitize_textarea_field( (string) $context );
        }

        $inserted = $wpdb->insert(
            $this->table_name,
            array(
                'time'    => current_time( 'mysql' ),
                'level'   => strtoupper( sanitize_text_field( $level ) ),
                'message' => sanitize_textarea_field( $message ),
                'context' => $context_value,
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        if ( $inserted === false ) {
            // Fallback para error_log se a inserção falhar
            error_log( "[Manus {$level}] {$message}" );
            if ( $context ) {
                error_log( 'Contexto: ' . print_r( $context, true ) );
            }
            return false;
        }

        // Mantém no máximo 1000 registros
        $this->limit_logs();

        return $wpdb->insert_id;
    }

    // -------------------------------------------------------------------------
    // Leitura
    // -------------------------------------------------------------------------

    /**
     * Retorna logs com suporte a filtros e paginação.
     *
     * @param array $args {
     *   @type int    $per_page Registros por página. Default 50.
     *   @type int    $paged    Página atual. Default 1.
     *   @type string $level    Filtrar por nível (opcional).
     *   @type string $search   Buscar na mensagem (opcional).
     * }
     * @return array { logs: array, total: int, pages: int }
     */
    public function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 50,
            'paged'    => 1,
            'level'    => '',
            'search'   => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['level'] ) ) {
            $where[]  = 'level = %s';
            $params[] = strtoupper( sanitize_text_field( $args['level'] ) );
        }

        if ( ! empty( $args['search'] ) ) {
            $where[]  = 'message LIKE %s';
            $params[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
        }

        $where_sql = implode( ' AND ', $where );

        // Total
        $total_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";
        $total     = (int) ( empty( $params )
            ? $wpdb->get_var( $total_sql )
            : $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ) );

        // Registros paginados
        $per_page = max( 1, (int) $args['per_page'] );
        $offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;
        $pages    = ceil( $total / $per_page );

        $rows_sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY time DESC LIMIT %d OFFSET %d";
        $all_params = array_merge( $params, array( $per_page, $offset ) );
        $rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $all_params ) );

        return array(
            'logs'  => $rows ?: array(),
            'total' => $total,
            'pages' => $pages,
        );
    }

    /**
     * Conta registros agrupados por nível.
     */
    public function count_logs_by_level() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT level, COUNT(*) as total FROM {$this->table_name} GROUP BY level",
            ARRAY_A
        );

        $counts = array( 'INFO' => 0, 'WARNING' => 0, 'ERROR' => 0, 'SUCCESS' => 0 );
        foreach ( $results as $row ) {
            $counts[ $row['level'] ] = (int) $row['total'];
        }

        return $counts;
    }

    // -------------------------------------------------------------------------
    // Manutenção
    // -------------------------------------------------------------------------

    /**
     * Mantém no máximo 1000 registros, removendo os mais antigos.
     */
    private function limit_logs() {
        global $wpdb;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        if ( $total <= 1000 ) {
            return;
        }

        $to_delete = $total - 1000;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} ORDER BY time ASC LIMIT %d",
                $to_delete
            )
        );
    }

    /**
     * Remove todos os logs.
     *
     * @return int Número de registros deletados.
     */
    public function clear_logs() {
        global $wpdb;
        return (int) $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
    }

    /**
     * Exporta todos os logs em formato array (para JSON).
     */
    public function export_logs() {
        $logs_data = $this->get_logs( array( 'per_page' => 9999 ) );

        return array(
            'export_date'    => current_time( 'mysql' ),
            'plugin_version' => defined( 'MANUS_WP_REPOSTER_VERSION' ) ? MANUS_WP_REPOSTER_VERSION : '1.0.0',
            'total_logs'     => $logs_data['total'],
            'logs'           => $logs_data['logs'],
        );
    }
}