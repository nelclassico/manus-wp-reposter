<?php
/**
 * Classe de administração do plugin
 */
class Manus_WP_Reposter_Admin {
    
    private $settings_page = 'manus-wp-reposter';
    private $settings_group = 'manus_wp_reposter_settings';
    private $importer = null;
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_post_manus_wp_reposter_import_now', array( $this, 'handle_import_now' ) );
        add_action( 'admin_post_manus_wp_reposter_clear_logs', array( $this, 'handle_clear_logs' ) );
    }

    /**
     * Define o importador (chamado pelo plugin principal)
     */
    public function set_importer( $importer ) {
        $this->importer = $importer;
    }
    
    /**
     * Adiciona menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Manus WP Reposter', 'manus-wp-reposter' ),
            __( 'WP Reposter', 'manus-wp-reposter' ),
            'manage_options',
            $this->settings_page,
            array( $this, 'display_settings_page' ),
            'dashicons-rss',
            60
        );
        
        add_submenu_page(
            $this->settings_page,
            __( 'Importar Agora', 'manus-wp-reposter' ),
            __( 'Importar Agora', 'manus-wp-reposter' ),
            'manage_options',
            'manus-wp-reposter-import',
            array( $this, 'display_import_page' )
        );
        
        add_submenu_page(
            $this->settings_page,
            __( 'Logs', 'manus-wp-reposter' ),
            __( 'Logs', 'manus-wp-reposter' ),
            'manage_options',
            'manus-wp-reposter-logs',
            array( $this, 'display_logs_page' )
        );
    }
    
    /**
     * Exibe página de configurações
     */
    public function display_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissão negada.', 'manus-wp-reposter' ) );
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="manus-reposter-admin-container">
                <div class="manus-reposter-main-content">
                    <h2><?php _e( 'Configurações Principais', 'manus-wp-reposter' ); ?></h2>
                    
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( $this->settings_group );
                        do_settings_sections( $this->settings_page );
                        submit_button();
                        ?>
                    </form>
                    
                    <h2><?php _e( 'Testar Configurações', 'manus-wp-reposter' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="manus_wp_reposter_test_feed">
                        <?php wp_nonce_field( 'manus_wp_reposter_test_feed' ); ?>
                        <p>
                            <label for="test_feed_url"><?php _e( 'URL do Feed para Teste:', 'manus-wp-reposter' ); ?></label><br>
                            <input type="url" id="test_feed_url" name="test_feed_url" 
                                   value="<?php echo esc_attr( get_option( 'manus_wp_reposter_feed_url' ) ); ?>"
                                   class="regular-text">
                        </p>
                        <?php submit_button( __( 'Testar Feed', 'manus-wp-reposter' ), 'secondary' ); ?>
                    </form>
                </div>
                
                <div class="manus-reposter-sidebar">
                    <div class="manus-reposter-status-card">
                        <h3><?php _e( 'Status do Plugin', 'manus-wp-reposter' ); ?></h3>
                        <?php $this->display_plugin_status(); ?>
                    </div>
                    
                    <div class="manus-reposter-quick-actions">
                        <h3><?php _e( 'Ações Rápidas', 'manus-wp-reposter' ); ?></h3>
                        <p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=manus-wp-reposter-import' ) ); ?>" 
                               class="button button-primary">
                                <?php _e( 'Importar Agora', 'manus-wp-reposter' ); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=manus-wp-reposter-logs' ) ); ?>" 
                               class="button">
                                <?php _e( 'Ver Logs', 'manus-wp-reposter' ); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .manus-reposter-admin-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .manus-reposter-main-content {
            flex: 2;
        }
        .manus-reposter-sidebar {
            flex: 1;
        }
        .manus-reposter-status-card,
        .manus-reposter-quick-actions {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .manus-reposter-status-card h3,
        .manus-reposter-quick-actions h3 {
            margin-top: 0;
        }
        .status-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .status-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .status-label {
            font-weight: bold;
            display: block;
        }
        .status-value {
            color: #007cba;
        }
        .status-error {
            color: #d63638;
        }
        .status-success {
            color: #00a32a;
        }
        </style>
        <?php
    }
    
    /**
     * Exibe página de importação
     */
    public function display_import_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissão negada.', 'manus-wp-reposter' ) );
        }
        
        $feed_url = get_option( 'manus_wp_reposter_feed_url' );

         // Mostra resultado da importação anterior
        if ( isset( $_GET['imported'] ) ) {
            $imported = intval( $_GET['imported'] );
            $skipped = intval( $_GET['skipped'] );
            $failed = intval( $_GET['failed'] );
            
            if ( $imported > 0 ) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . sprintf( 
                    __( 'Importação concluída! %d posts importados, %d pulados, %d falhas.', 'manus-wp-reposter' ), 
                    $imported, $skipped, $failed 
                ) . '</p>';
                echo '</div>';
            } elseif ( isset( $_GET['error'] ) ) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . __( 'Erro na importação:', 'manus-wp-reposter' ) . ' ' . esc_html( urldecode( $_GET['error'] ) ) . '</p>';
                echo '</div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Importar Notícias Agora', 'manus-wp-reposter' ); ?></h1>
            
            <?php if ( empty( $feed_url ) ) : ?>
                <div class="notice notice-warning">
                    <p><?php _e( 'Configure a URL do feed RSS nas configurações principais antes de importar.', 'manus-wp-reposter' ); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="manus-reposter-import-form">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="manus_wp_reposter_import_now">
                    <?php wp_nonce_field( 'manus_wp_reposter_import_now' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="import_quantity"><?php _e( 'Quantidade de Itens:', 'manus-wp-reposter' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="import_quantity" name="import_quantity" 
                                       value="5" min="1" max="20" step="1" style="width: 100px;">
                                <p class="description">
                                    <?php _e( 'Número de notícias para importar (1-20). Serão importadas do mais antigo para o mais novo.', 'manus-wp-reposter' ); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="import_mode"><?php _e( 'Modo de Importação:', 'manus-wp-reposter' ); ?></label>
                            </th>
                            <td>
                                <select id="import_mode" name="import_mode">
                                    <option value="normal"><?php _e( 'Normal (Verificar duplicatas)', 'manus-wp-reposter' ); ?></option>
                                    <option value="force"><?php _e( 'Forçar (Ignorar duplicatas)', 'manus-wp-reposter' ); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e( 'Modo normal verifica se a notícia já foi importada. Modo força importa tudo.', 'manus-wp-reposter' ); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e( 'Opções de Conteúdo:', 'manus-wp-reposter' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="translate_content" value="1">
                                    <?php _e( 'Traduzir conteúdo automaticamente', 'manus-wp-reposter' ); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="download_images" value="1" checked>
                                    <?php _e( 'Baixar e hospedar imagens localmente', 'manus-wp-reposter' ); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="set_featured_image" value="1" checked>
                                    <?php _e( 'Definir imagem destacada automaticamente', 'manus-wp-reposter' ); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="post_status"><?php _e( 'Status dos Posts:', 'manus-wp-reposter' ); ?></label>
                            </th>
                            <td>
                                <select id="post_status" name="post_status">
                                    <option value="draft"><?php _e( 'Rascunho', 'manus-wp-reposter' ); ?></option>
                                    <option value="pending"><?php _e( 'Pendente', 'manus-wp-reposter' ); ?></option>
                                    <option value="publish"><?php _e( 'Publicar', 'manus-wp-reposter' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Iniciar Importação', 'manus-wp-reposter' ), 'primary large' ); ?>
                </form>
            </div>
            
            <div class="manus-reposter-import-info">
                <h3><?php _e( 'Informações da Importação', 'manus-wp-reposter' ); ?></h3>
                <p><?php _e( 'Feed configurado:', 'manus-wp-reposter' ); ?> 
                   <code><?php echo esc_url( $feed_url ); ?></code>
                </p>
                <p><?php _e( 'A importação pode levar alguns minutos dependendo do número de itens e do tamanho do conteúdo.', 'manus-wp-reposter' ); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Exibe página de logs
     */
    public function display_logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissão negada.', 'manus-wp-reposter' ) );
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'manus_reposter_logs';
        
        // Paginação
        $per_page = 50;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;
        
        // Filtros
        $where = array();
        if ( ! empty( $_GET['level'] ) ) {
            $where[] = $wpdb->prepare( 'level = %s', sanitize_text_field( $_GET['level'] ) );
        }
        if ( ! empty( $_GET['search'] ) ) {
            $where[] = $wpdb->prepare( 'message LIKE %s', '%' . $wpdb->esc_like( sanitize_text_field( $_GET['search'] ) ) . '%' );
        }
        
        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        
        // Total de registros
        $total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where_clause" );
        $total_pages = ceil( $total_logs / $per_page );
        
        // Busca logs
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name $where_clause ORDER BY time DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Logs de Importação', 'manus-wp-reposter' ); ?></h1>
            
            <div class="manus-reposter-logs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="manus-wp-reposter-logs">
                    
                    <label for="level"><?php _e( 'Filtrar por nível:', 'manus-wp-reposter' ); ?></label>
                    <select id="level" name="level">
                        <option value=""><?php _e( 'Todos', 'manus-wp-reposter' ); ?></option>
                        <option value="INFO" <?php selected( isset( $_GET['level'] ) && $_GET['level'] === 'INFO' ); ?>>
                            <?php _e( 'Informação', 'manus-wp-reposter' ); ?>
                        </option>
                        <option value="WARNING" <?php selected( isset( $_GET['level'] ) && $_GET['level'] === 'WARNING' ); ?>>
                            <?php _e( 'Aviso', 'manus-wp-reposter' ); ?>
                        </option>
                        <option value="ERROR" <?php selected( isset( $_GET['level'] ) && $_GET['level'] === 'ERROR' ); ?>>
                            <?php _e( 'Erro', 'manus-wp-reposter' ); ?>
                        </option>
                    </select>
                    
                    <label for="search"><?php _e( 'Buscar:', 'manus-wp-reposter' ); ?></label>
                    <input type="text" id="search" name="search" value="<?php echo isset( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : ''; ?>">
                    
                    <?php submit_button( __( 'Filtrar', 'manus-wp-reposter' ), 'secondary', '', false ); ?>
                    
                    <?php if ( ! empty( $_GET['level'] ) || ! empty( $_GET['search'] ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=manus-wp-reposter-logs' ) ); ?>" 
                           class="button">
                           <?php _e( 'Limpar Filtros', 'manus-wp-reposter' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="manus-reposter-logs-actions">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="manus_wp_reposter_clear_logs">
                    <?php wp_nonce_field( 'manus_wp_reposter_clear_logs' ); ?>
                    
                    <button type="submit" class="button button-secondary" 
                            onclick="return confirm('<?php _e( 'Tem certeza que deseja limpar todos os logs?', 'manus-wp-reposter' ); ?>');">
                        <?php _e( 'Limpar Todos os Logs', 'manus-wp-reposter' ); ?>
                    </button>
                    
                    <button type="button" class="button" onclick="manusExportLogs()">
                        <?php _e( 'Exportar Logs', 'manus-wp-reposter' ); ?>
                    </button>
                </form>
            </div>
            
            <?php if ( empty( $logs ) ) : ?>
                <div class="notice notice-info">
                    <p><?php _e( 'Nenhum log encontrado.', 'manus-wp-reposter' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="150"><?php _e( 'Data/Hora', 'manus-wp-reposter' ); ?></th>
                            <th width="100"><?php _e( 'Nível', 'manus-wp-reposter' ); ?></th>
                            <th><?php _e( 'Mensagem', 'manus-wp-reposter' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( $log->time ); ?></td>
                                <td>
                                    <span class="log-level log-level-<?php echo strtolower( esc_attr( $log->level ) ); ?>">
                                        <?php echo esc_html( $log->level ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html( $log->message ); ?>
                                    <?php if ( ! empty( $log->context ) ) : ?>
                                        <details style="margin-top: 5px;">
                                            <summary><?php _e( 'Detalhes', 'manus-wp-reposter' ); ?></summary>
                                            <pre style="background: #f6f7f7; padding: 10px; margin: 5px 0; font-size: 12px; white-space: pre-wrap;"><?php 
                                                echo esc_html( $log->context ); 
                                            ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links( array(
                                'base' => add_query_arg( 'paged', '%#%' ),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page,
                            ) );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <script>
        function manusExportLogs() {
            var data = new FormData();
            data.append('action', 'manus_wp_reposter_export_logs');
            data.append('nonce', '<?php echo wp_create_nonce( 'manus_wp_reposter_export_logs' ); ?>');
            
            fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                method: 'POST',
                body: data,
            })
            .then(response => response.blob())
            .then(blob => {
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'manus-reposter-logs-<?php echo date('Y-m-d'); ?>.json';
                document.body.appendChild(a);
                a.click();
                a.remove();
            })
            .catch(error => console.error('Erro ao exportar logs:', error));
        }
        </script>
        
        <style>
        .log-level {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .log-level-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .log-level-warning {
            background: #fff3cd;
            color: #856404;
        }
        .log-level-error {
            background: #f8d7da;
            color: #721c24;
        }
        .manus-reposter-logs-filters {
            background: #fff;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
        }
        .manus-reposter-logs-filters label {
            margin-right: 5px;
        }
        .manus-reposter-logs-filters select,
        .manus-reposter-logs-filters input[type="text"] {
            margin-right: 15px;
        }
        .manus-reposter-logs-actions {
            margin-bottom: 20px;
        }
        .manus-reposter-logs-actions button {
            margin-right: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Registra configurações
     */
    public function register_settings() {
        // Seção principal
        add_settings_section(
            'manus_wp_reposter_main',
            __( 'Configurações do Feed', 'manus-wp-reposter' ),
            array( $this, 'render_main_section' ),
            $this->settings_page
        );
        
        // Campos
        add_settings_field(
            'manus_wp_reposter_feed_url',
            __( 'URL do Feed RSS', 'manus-wp-reposter' ),
            array( $this, 'render_feed_url_field' ),
            $this->settings_page,
            'manus_wp_reposter_main'
        );
        
        add_settings_field(
            'manus_wp_reposter_default_category',
            __( 'Categoria Padrão', 'manus-wp-reposter' ),
            array( $this, 'render_default_category_field' ),
            $this->settings_page,
            'manus_wp_reposter_main'
        );
        
        add_settings_field(
            'manus_wp_reposter_post_author',
            __( 'Autor Padrão', 'manus-wp-reposter' ),
            array( $this, 'render_post_author_field' ),
            $this->settings_page,
            'manus_wp_reposter_main'
        );
        
        add_settings_field(
            'manus_wp_reposter_auto_translate',
            __( 'Tradução Automática', 'manus-wp-reposter' ),
            array( $this, 'render_auto_translate_field' ),
            $this->settings_page,
            'manus_wp_reposter_main'
        );
        
        // Registra as opções
        register_setting(
            $this->settings_group,
            'manus_wp_reposter_feed_url',
            array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => ''
            )
        );
        
        register_setting(
            $this->settings_group,
            'manus_wp_reposter_default_category',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 0
            )
        );
        
        register_setting(
            $this->settings_group,
            'manus_wp_reposter_post_author',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 1
            )
        );
        
        register_setting(
            $this->settings_group,
            'manus_wp_reposter_auto_translate',
            array(
                'type' => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_boolean' ),
                'default' => false
            )
        );
    }
    
    /**
     * Sanitiza valores booleanos
     */
    public function sanitize_boolean( $value ) {
        return $value === '1' || $value === true;
    }
    
    /**
     * Renderiza seção principal
     */
    public function render_main_section() {
        echo '<p>' . __( 'Configure o feed RSS que será importado automaticamente.', 'manus-wp-reposter' ) . '</p>';
    }
    
    /**
     * Renderiza campo de URL do feed
     */
    public function render_feed_url_field() {
        $value = get_option( 'manus_wp_reposter_feed_url', '' );
        ?>
        <input type="url" name="manus_wp_reposter_feed_url" 
               value="<?php echo esc_url( $value ); ?>" 
               class="regular-text" 
               placeholder="https://exemplo.com/feed">
        <p class="description">
            <?php _e( 'URL completa do feed RSS ou Atom.', 'manus-wp-reposter' ); ?>
        </p>
        <?php
    }
    
    /**
     * Renderiza campo de categoria padrão
     */
    public function render_default_category_field() {
        $selected = get_option( 'manus_wp_reposter_default_category', 0 );
        ?>
        <select name="manus_wp_reposter_default_category">
            <option value="0"><?php _e( '— Selecionar —', 'manus-wp-reposter' ); ?></option>
            <?php
            $categories = get_categories( array( 'hide_empty' => false ) );
            foreach ( $categories as $category ) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $category->term_id ),
                    selected( $selected, $category->term_id, false ),
                    esc_html( $category->name )
                );
            }
            ?>
        </select>
        <p class="description">
            <?php _e( 'Categoria atribuída automaticamente aos posts importados.', 'manus-wp-reposter' ); ?>
        </p>
        <?php
    }
    
    /**
     * Renderiza campo de autor padrão
     */
    public function render_post_author_field() {
        $selected = get_option( 'manus_wp_reposter_post_author', 1 );
        ?>
        <select name="manus_wp_reposter_post_author">
            <?php
            $authors = get_users( array(
                'role__in' => array( 'administrator', 'editor', 'author' ),
                'orderby' => 'display_name'
            ) );
            
            foreach ( $authors as $author ) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $author->ID ),
                    selected( $selected, $author->ID, false ),
                    esc_html( $author->display_name )
                );
            }
            ?>
        </select>
        <?php
    }
    
    /**
     * Renderiza campo de tradução automática
     */
    public function render_auto_translate_field() {
        $enabled = get_option( 'manus_wp_reposter_auto_translate', false );
        ?>
        <label>
            <input type="checkbox" name="manus_wp_reposter_auto_translate" 
                   value="1" <?php checked( $enabled ); ?>>
            <?php _e( 'Traduzir automaticamente conteúdo em inglês para português', 'manus-wp-reposter' ); ?>
        </label>
        <p class="description">
            <?php _e( 'Usa o serviço DeepL para tradução automática. Requer chave de API configurada.', 'manus-wp-reposter' ); ?>
        </p>
        <?php
    }
    
    /**
     * Exibe status do plugin
     */
    private function display_plugin_status() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'manus_reposter_logs';
        
        // Verifica se tabela existe
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        // Próxima execução agendada
        $next_scheduled = wp_next_scheduled( 'manus_wp_reposter_daily_import' );
        
        // URL do feed
        $feed_url = get_option( 'manus_wp_reposter_feed_url' );
        
        // Total de posts importados
        $total_imported = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_manus_imported'"
        );
        
        ?>
        <div class="status-item">
            <span class="status-label"><?php _e( 'Tabela de Logs:', 'manus-wp-reposter' ); ?></span>
            <span class="status-value <?php echo $table_exists ? 'status-success' : 'status-error'; ?>">
                <?php echo $table_exists ? __( 'Criada', 'manus-wp-reposter' ) : __( 'Não criada', 'manus-wp-reposter' ); ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="status-label"><?php _e( 'Feed Configurado:', 'manus-wp-reposter' ); ?></span>
            <span class="status-value <?php echo ! empty( $feed_url ) ? 'status-success' : 'status-error'; ?>">
                <?php echo ! empty( $feed_url ) ? __( 'Sim', 'manus-wp-reposter' ) : __( 'Não', 'manus-wp-reposter' ); ?>
            </span>
        </div>
        
        <div class="status-item">
            <span class="status-label"><?php _e( 'Importação Agendada:', 'manus-wp-reposter' ); ?></span>
            <span class="status-value <?php echo $next_scheduled ? 'status-success' : 'status-error'; ?>">
                <?php echo $next_scheduled ? __( 'Ativa', 'manus-wp-reposter' ) : __( 'Inativa', 'manus-wp-reposter' ); ?>
            </span>
            <?php if ( $next_scheduled ) : ?>
                <br><small><?php echo date_i18n( 'd/m/Y H:i', $next_scheduled ); ?></small>
            <?php endif; ?>
        </div>
        
        <div class="status-item">
            <span class="status-label"><?php _e( 'Total Importado:', 'manus-wp-reposter' ); ?></span>
            <span class="status-value"><?php echo intval( $total_imported ); ?></span>
        </div>
        <?php
    }
    
    /**
     * Lida com importação manual
     */
    public function handle_import_now() {
        // Verifica nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'manus_wp_reposter_import_now' ) ) {
            wp_die( __( 'Ação não autorizada.', 'manus-wp-reposter' ) );
        }
        
        // Verifica permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissão negada.', 'manus-wp-reposter' ) );
        }
        
        // Obtém parâmetros
        $quantity = isset( $_POST['import_quantity'] ) ? intval( $_POST['import_quantity'] ) : 5;
        $quantity = max( 1, min( $quantity, 20 ) );
        
        $mode = isset( $_POST['import_mode'] ) ? sanitize_text_field( $_POST['import_mode'] ) : 'normal';
        $translate = isset( $_POST['translate_content'] ) && $_POST['translate_content'] === '1';
        $download_images = isset( $_POST['download_images'] ) && $_POST['download_images'] === '1';
        $set_featured = isset( $_POST['set_featured_image'] ) && $_POST['set_featured_image'] === '1';
        $post_status = isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : 'draft';
        
        // Executa importação
        $result = array();
        if ( $this->importer ) {
            $result = $this->importer->run_manual_import( $quantity, array(
                'mode' => $mode,
                'translate' => $translate,
                'download_images' => $download_images,
                'set_featured' => $set_featured,
                'post_status' => $post_status,
            ) );
        } else {
            $result['error'] = 'Importador não disponível';
        }
        
        // Redireciona com resultado
        $redirect_url = add_query_arg( array(
            'page' => 'manus-wp-reposter-import',
            'imported' => isset( $result['imported'] ) ? $result['imported'] : 0,
            'skipped' => isset( $result['skipped'] ) ? $result['skipped'] : 0,
            'failed' => isset( $result['failed'] ) ? $result['failed'] : 0,
        ), admin_url( 'admin.php' ) );
        
        if ( isset( $result['error'] ) ) {
            $redirect_url = add_query_arg( 'error', urlencode( $result['error'] ), $redirect_url );
        }
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    /**
     * Lida com limpeza de logs
     */
    public function handle_clear_logs() {
        // Verifica nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'manus_wp_reposter_clear_logs' ) ) {
            wp_die( __( 'Ação não autorizada.', 'manus-wp-reposter' ) );
        }
        
        // Verifica permissões
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permissão negada.', 'manus-wp-reposter' ) );
        }
        
        // Limpa logs
        global $wpdb;
        $table_name = $wpdb->prefix . 'manus_reposter_logs';
        $wpdb->query( "TRUNCATE TABLE $table_name" );
        
        // Log da ação
        $this->log( 'INFO', __( 'Logs limpos manualmente pelo administrador.', 'manus-wp-reposter' ) );
        
        // Redireciona
        wp_safe_redirect( add_query_arg( 'page', 'manus-wp-reposter-logs', admin_url( 'admin.php' ) ) );
        exit;
    }
    
    /**
     * Enque scripts e styles
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'manus-wp-reposter' ) !== false ) {
            wp_enqueue_style(
                'manus-wp-reposter-admin',
                MANUS_WP_REPOSTER_URL . 'assets/css/admin.css',
                array(),
                MANUS_WP_REPOSTER_VERSION
            );
            
            wp_enqueue_script(
                'manus-wp-reposter-admin',
                MANUS_WP_REPOSTER_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                MANUS_WP_REPOSTER_VERSION,
                true
            );
            
            wp_localize_script( 'manus-wp-reposter-admin', 'manusReposter', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'manus_wp_reposter_nonce' ),
                'strings' => array(
                    'confirm_clear_logs' => __( 'Tem certeza que deseja limpar todos os logs?', 'manus-wp-reposter' ),
                    'importing' => __( 'Importando...', 'manus-wp-reposter' ),
                    'import_complete' => __( 'Importação completa!', 'manus-wp-reposter' ),
                )
            ) );
        }
    }
    
    /**
     * Registra log
     */
    private function log( $level, $message, $context = null ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'manus_reposter_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'level' => $level,
                'message' => $message,
                'context' => $context ? json_encode( $context, JSON_PRETTY_PRINT ) : null,
            )
        );
    }
}