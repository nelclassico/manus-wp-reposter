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
       add_action( 'admin_init', array( $this, 'register_ai_settings' ) );
       add_action( 'admin_init', array( $this, 'register_automation_settings' ) );
       add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
       add_action( 'admin_post_manus_wp_reposter_import_now', array( $this, 'handle_import_now' ) );
       add_action( 'admin_post_manus_wp_reposter_clear_logs', array( $this, 'handle_clear_logs' ) );

       // AJAX handlers
       add_action( 'wp_ajax_manus_analyze_feed', array( 'Manus_WP_Reposter_Automation_Settings', 'handle_ajax_analyze_feed' ) );
       add_action( 'wp_ajax_manus_ai_test_connection', array( $this, 'handle_ajax_ai_test' ) );
       add_action( 'wp_ajax_manus_ai_get_models', array( $this, 'handle_ajax_ai_get_models' ) );
       add_action( 'wp_ajax_manus_run_scheduled_feeds_now', array( 'Manus_WP_Reposter_Automation_Settings', 'handle_ajax_run_scheduled_feeds_now' ) );
   }

   /**
    * @deprecated Mantido por compatibilidade, não faz mais nada.
    */
   public function force_register_ajax() {}

   /**
    * @deprecated Mantido por compatibilidade, não faz mais nada.
    */
   public function register_ajax_hooks() {}

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

        add_submenu_page(

            $this->settings_page,

            __( 'Agente de IA', 'manus-wp-reposter' ),

            __( '🤖 Agente de IA', 'manus-wp-reposter' ),

            'manage_options',

            'manus-wp-reposter-ai',

            array( $this, 'display_ai_page' )

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

                        <p>
                            <button type="button" id="manus-run-scheduled-now" class="button button-secondary" style="width:100%;">
                                <?php _e( '▶ Executar Feeds Agendados Agora', 'manus-wp-reposter' ); ?>
                            </button>
                            <span id="manus-run-scheduled-result" style="display:none; margin-top:6px; font-size:12px;"></span>
                        </p>

                    </div>

                </div>

            </div>

        </div>

        <script>
        (function($){
            $('#manus-run-scheduled-now').on('click', function(){
                var $btn = $(this);
                var $result = $('#manus-run-scheduled-result');
                $btn.prop('disabled', true).text(manusReposterData.strings.running_scheduled || 'Executando...');
                $result.hide();
                $.post(manusReposterData.ajax_url, {
                    action: 'manus_run_scheduled_feeds_now',
                    nonce: manusReposterData.nonce_run_scheduled
                }, function(resp){
                    $btn.prop('disabled', false).text('▶ Executar Feeds Agendados Agora');
                    if(resp.success){
                        $result.css('color','green').text(resp.data.message || 'Concluído!').show();
                    } else {
                        $result.css('color','red').text((resp.data && resp.data.message) || 'Erro').show();
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text('▶ Executar Feeds Agendados Agora');
                    $result.css('color','red').text('Erro de comunicação.').show();
                });
            });
        })(jQuery);
        </script>



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

        $temp_feed_url = isset( $_POST['temp_feed_url'] ) ? esc_url_raw( $_POST['temp_feed_url'] ) : '';



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



            <?php if ( empty( $feed_url ) && empty( $temp_feed_url ) ) : ?>

                <div class="notice notice-warning">

                    <p><?php _e( 'Configure a URL do feed RSS nas configurações principais ou insira uma URL temporária abaixo.', 'manus-wp-reposter' ); ?></p>

                </div>

            <?php endif; ?>



            <div class="manus-reposter-import-form">

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

                    <input type="hidden" name="action" value="manus_wp_reposter_import_now">

                    <?php wp_nonce_field( 'manus_wp_reposter_import_now' ); ?>



                    <table class="form-table">

                        <tr>

                            <th scope="row">

                                <label for="temp_feed_url"><?php _e( 'URL do Feed (Temporária):', 'manus-wp-reposter' ); ?></label>

                            </th>

                            <td>

                                <input type="url" 

                                       id="temp_feed_url" 

                                       name="temp_feed_url"

                                       value="<?php echo esc_attr( $temp_feed_url ); ?>"

                                       placeholder="<?php echo esc_attr( $feed_url ); ?>"

                                       class="regular-text">

                                <p class="description">

                                    <?php _e( 'URL temporária para esta importação específica. Se deixar em branco, será usada a URL principal configurada. Esta URL não substitui a configuração principal.', 'manus-wp-reposter' ); ?>

                                </p>

                                <?php if ( ! empty( $feed_url ) ) : ?>

                                    <p class="description">

                                        <?php _e( 'URL principal configurada:', 'manus-wp-reposter' ); ?>

                                        <code><?php echo esc_url( $feed_url ); ?></code>

                                    </p>

                                <?php endif; ?>

                            </td>

                        </tr>



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

                <?php if ( ! empty( $feed_url ) ) : ?>

                    <p><?php _e( 'Feed principal configurado:', 'manus-wp-reposter' ); ?>

                       <code><?php echo esc_url( $feed_url ); ?></code>

                    </p>

                <?php endif; ?>

                <p><?php _e( 'A importação pode levar alguns minutos dependendo do número de itens e do tamanho do conteúdo.', 'manus-wp-reposter' ); ?></p>

                <p class="description">

                    <strong><?php _e( 'Nota:', 'manus-wp-reposter' ); ?></strong>

                    <?php _e( 'A URL temporária é usada apenas para esta importação. A importação automática continuará usando a URL principal.', 'manus-wp-reposter' ); ?>

                </p>

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

    /**
     * Registra configurações de automação
     */
    public function register_automation_settings() {
        if ( class_exists( 'Manus_WP_Reposter_Automation_Settings' ) ) {
            Manus_WP_Reposter_Automation_Settings::register_automation_settings();
            Manus_WP_Reposter_Automation_Settings::add_automation_section();
            
            // *** CAMPO PRINCIPAL: Feeds agendados individuais ***
            add_settings_field(
                'manus_wp_reposter_scheduled_feeds',
                __( 'Feeds Agendados (Automação)', 'manus-wp-reposter' ),
                array( 'Manus_WP_Reposter_Automation_Settings', 'render_scheduled_feeds_field' ),
                $this->settings_page,
                'manus_wp_reposter_automation'
            );

            // Horário global (fallback quando não há feeds agendados)
            add_settings_field(
                'manus_wp_reposter_daily_time',
                __( 'Horário Global (Feed Principal)', 'manus-wp-reposter' ),
                array( 'Manus_WP_Reposter_Automation_Settings', 'render_daily_time_field' ),
                $this->settings_page,
                'manus_wp_reposter_automation'
            );

            add_settings_field(
                'manus_wp_reposter_feed_url_analysis',
                __( 'Análise de Feed', 'manus-wp-reposter' ),
                array( 'Manus_WP_Reposter_Automation_Settings', 'render_feed_analysis_field' ),
                $this->settings_page,
                'manus_wp_reposter_automation'
            );

            add_settings_field(
                'manus_wp_reposter_enable_veracity_check',
                __( 'Verificação de Veracidade', 'manus-wp-reposter' ),
                array( 'Manus_WP_Reposter_Automation_Settings', 'render_veracity_check_field' ),
                $this->settings_page,
                'manus_wp_reposter_automation'
            );

            add_settings_field(
                'manus_wp_reposter_trusted_sources',
                __( 'Fontes Confiáveis', 'manus-wp-reposter' ),
                array( 'Manus_WP_Reposter_Automation_Settings', 'render_trusted_sources_field' ),
                $this->settings_page,
                'manus_wp_reposter_automation'
            );
        }
    }

    public function register_settings() {

        // Registra as opções

        register_setting(

            $this->settings_group,

            'manus_wp_reposter_feed_url',

            array(

                'type' => 'string',

                'sanitize_callback' => array( $this, 'sanitize_and_sync_feed_url' ),

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



        register_setting(

            $this->settings_group,

            'manus_wp_reposter_daily_quantity',

            array(

                'type' => 'integer',

                'sanitize_callback' => 'absint',

                'default' => 1,

            )

        );



        // Seção principal - CORRIGIDO: Mantenha apenas UMA seção

        add_settings_section(

            'manus_wp_reposter_main',

            __( 'Configurações do Feed', 'manus-wp-reposter' ),

            array( $this, 'render_main_section' ),

            $this->settings_page

        );



        // Campos - todos na mesma seção 'manus_wp_reposter_main'

        add_settings_field(

            'manus_wp_reposter_feed_url',

            __( 'URL do Feed RSS', 'manus-wp-reposter' ),

            array( $this, 'render_feed_url_field' ),

            $this->settings_page,

            'manus_wp_reposter_main'  // Mesma seção

        );



        add_settings_field(

            'manus_wp_reposter_default_category',

            __( 'Categoria Padrão', 'manus-wp-reposter' ),

            array( $this, 'render_default_category_field' ),

            $this->settings_page,

            'manus_wp_reposter_main'  // Mesma seção

        );



        add_settings_field(

            'manus_wp_reposter_post_author',

            __( 'Autor Padrão', 'manus-wp-reposter' ),

            array( $this, 'render_post_author_field' ),

            $this->settings_page,

            'manus_wp_reposter_main'  // Mesma seção

        );



        add_settings_field(

            'manus_wp_reposter_auto_translate',

            __( 'Tradução Automática', 'manus-wp-reposter' ),

            array( $this, 'render_auto_translate_field' ),

            $this->settings_page,

            'manus_wp_reposter_main'  // Mesma seção

        );



        add_settings_field(

            'manus_wp_reposter_daily_quantity',

            __( 'Quantidade de Posts (Automático)', 'manus-wp-reposter' ),

            array( $this, 'render_daily_quantity_field' ),  // Renomeei o callback para consistência

            $this->settings_page,

            'manus_wp_reposter_main'  // Mesma seção

        );

    }





   /**

    * Renderiza campo de quantidade diária

    */

   public function render_daily_quantity_field() {

       $quantity = get_option( 'manus_wp_reposter_daily_quantity', 1 );

       ?>

       <input type="number" 

              name="manus_wp_reposter_daily_quantity" 

              value="<?php echo esc_attr( $quantity ); ?>" 

              min="1" 

              max="20" 

              step="1" 

              class="small-text" />

       <p class="description">

           <?php _e( 'Número de posts a serem importados na rotina automática diária (1-20).', 'manus-wp-reposter' ); ?>

       </p>

       <?php

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
        // Fallback: se ainda vazio, usa o campo de análise (os dois são sincronizados pelo AJAX de análise)
        if ( empty( $value ) ) {
            $value = get_option( 'manus_wp_reposter_feed_url_analysis', '' );
        }

        ?>

        <input type="url" name="manus_wp_reposter_feed_url"

               value="<?php echo esc_url( $value ); ?>"

               class="regular-text"

               placeholder="https://exemplo.com/feed">

        <p class="description">

            <?php _e( 'URL completa do feed RSS ou Atom usada na importação. Dica: você também pode usar o botão &quot;Analisar Feed&quot; na aba Automação — ele valida e salva a URL automaticamente neste campo.', 'manus-wp-reposter' ); ?>

        </p>

        <?php

    }



    /**
     * Sanitiza a URL do feed e sincroniza com o campo de análise (os dois devem sempre ter o mesmo valor).
     */
    public function sanitize_and_sync_feed_url( $value ) {
        $clean = esc_url_raw( trim( $value ) );
        // Mantém o campo de análise em sincronia para que a aba Automação reflita o valor atual.
        if ( ! empty( $clean ) ) {
            update_option( 'manus_wp_reposter_feed_url_analysis', $clean );
        }
        return $clean;
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

        $temp_feed_url = isset( $_POST['temp_feed_url'] ) ? esc_url_raw( $_POST['temp_feed_url'] ) : '';

        $quantity = isset( $_POST['import_quantity'] ) ? intval( $_POST['import_quantity'] ) : 5;

        $quantity = max( 1, min( $quantity, 20 ) );



        $mode = isset( $_POST['import_mode'] ) ? sanitize_text_field( $_POST['import_mode'] ) : 'normal';

        $translate = isset( $_POST['translate_content'] ) && $_POST['translate_content'] === '1';

        $download_images = isset( $_POST['download_images'] ) && $_POST['download_images'] === '1';

        $set_featured = isset( $_POST['set_featured_image'] ) && $_POST['set_featured_image'] === '1';

        $post_status = isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : 'draft';



        // Determina qual URL usar

        if ( ! empty( $temp_feed_url ) ) {

            $feed_url = $temp_feed_url;

            $using_temp_url = true;

        } else {

            $feed_url = get_option( 'manus_wp_reposter_feed_url' );

            $using_temp_url = false;

        }



        // Verifica se há URL para usar

        if ( empty( $feed_url ) ) {

            wp_die( __( 'Configure uma URL do feed RSS nas configurações principais ou insira uma URL temporária.', 'manus-wp-reposter' ) );

        }



        // Executa importação

        $result = array();

        if ( $this->importer ) {

            $result = $this->importer->run_manual_import( 

                $quantity, 

                array(

                    'mode' => $mode,

                    'translate' => $translate,

                    'download_images' => $download_images,

                    'set_featured' => $set_featured,

                    'post_status' => $post_status,

                    'feed_url' => $feed_url, // Passa a URL específica

                )

            );

        } else {

            $result['error'] = 'Importador não disponível';

        }



        // Log da URL usada

        if ( isset( $result['error'] ) ) {

            $log_message = sprintf(

                'Importação manual falhou usando %s: %s',

                $using_temp_url ? 'URL temporária' : 'URL principal',

                $result['error']

            );

        } else {

            $log_message = sprintf(

                'Importação manual concluída usando %s: %d importados, %d pulados, %d falhas',

                $using_temp_url ? 'URL temporária' : 'URL principal',

                $result['imported'] ?? 0,

                $result['skipped'] ?? 0,

                $result['failed'] ?? 0

            );

        }

        

        $this->log( 'INFO', $log_message, array(

            'url_type' => $using_temp_url ? 'temporary' : 'main',

            'feed_url' => $feed_url,

            'quantity' => $quantity,

            'result' => $result,

        ) );



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
                MANUS_WP_REPOSTER_VERSION . '.' . time(), // 🔴 FORÇA VERSÃO ÚNICA PARA EVITAR CACHE
                true
            );

            // 🔴 CRIAR UM NONCE COM TEMPO DE VIDA MAIOR E NOME ESPECÍFICO
            $nonce = wp_create_nonce( 'manus_analyze_feed_action' ); // Nome diferente do padrão
            
            error_log('🔵 MANUS: Nonce criado para análise: ' . $nonce);
            
            wp_localize_script( 'manus-wp-reposter-admin', 'manusReposterData', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => $nonce,
                'nonce_run_scheduled' => wp_create_nonce( 'manus_run_scheduled_now' ),
                'nonce_name' => 'manus_analyze_nonce',
                'strings' => array(
                    'confirm_clear_logs'      => __( 'Tem certeza que deseja limpar todos os logs?', 'manus-wp-reposter' ),
                    'importing'               => __( 'Importando...', 'manus-wp-reposter' ),
                    'import_complete'         => __( 'Importação completa!', 'manus-wp-reposter' ),
                    'enter_feed_url'          => __( 'Por favor, insira uma URL de feed.', 'manus-wp-reposter' ),
                    'analyzing'               => __( 'Analisando...', 'manus-wp-reposter' ),
                    'analyze_feed'            => __( 'Analisar Feed', 'manus-wp-reposter' ),
                    'analysis_error'          => __( 'Erro ao analisar o feed.', 'manus-wp-reposter' ),
                    'ajax_error'              => __( 'Erro de comunicação.', 'manus-wp-reposter' ),
                    'running_scheduled'       => __( 'Executando feeds agendados...', 'manus-wp-reposter' ),
                    'scheduled_feeds_success' => __( 'Feeds agendados executados com sucesso!', 'manus-wp-reposter' ),
                )
            ) );
        }
    }

    /**

     * Registra log

     */

    private function log( $level, $message, $context = null ) {
        if ( class_exists( 'Manus_WP_Reposter_Logger' ) ) {
            Manus_WP_Reposter_Logger::instance()->add_log( $level, $message, $context );
        } else {
            error_log( "[Manus {$level}] {$message}" );
        }
    }

    // =========================================================================
    // Agente de IA — Página, configurações e AJAX
    // =========================================================================

    /**
     * Registra as opções do agente de IA no WordPress Settings API
     */
    public function register_ai_settings() {
        $fields = array(
            'manus_ai_rewrite_enabled' => array( 'type' => 'boolean',  'default' => false,              'sanitize' => array( $this, 'sanitize_boolean' ) ),
            'manus_ai_provider'        => array( 'type' => 'string',   'default' => 'none',             'sanitize' => 'sanitize_text_field' ),
            'manus_ai_api_key'         => array( 'type' => 'string',   'default' => '',                 'sanitize' => 'sanitize_text_field' ),
            'manus_ai_model'           => array( 'type' => 'string',   'default' => '',                 'sanitize' => 'sanitize_text_field' ),
            'manus_ai_tone'            => array( 'type' => 'string',   'default' => 'jornalistico',     'sanitize' => 'sanitize_text_field' ),
            'manus_ai_target_language' => array( 'type' => 'string',   'default' => 'pt-BR',            'sanitize' => 'sanitize_text_field' ),
            'manus_ai_min_words'       => array( 'type' => 'integer',  'default' => 200,                'sanitize' => 'absint' ),
            'manus_ai_max_words'       => array( 'type' => 'integer',  'default' => 800,                'sanitize' => 'absint' ),
        );
        foreach ( $fields as $option => $cfg ) {
            register_setting( 'manus_ai_settings', $option, array(
                'type'              => $cfg['type'],
                'default'           => $cfg['default'],
                'sanitize_callback' => $cfg['sanitize'],
            ) );
        }
    }

    // -------------------------------------------------------------------------
    // Página do agente
    // -------------------------------------------------------------------------

    public function display_ai_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Permissão negada.', 'manus-wp-reposter' ) );

        $saved = isset( $_GET['ai-saved'] );
        $agent = class_exists( 'Manus_WP_Reposter_AI_Agent' ) ? new Manus_WP_Reposter_AI_Agent() : null;

        // Processar formulário
        if ( isset( $_POST['manus_ai_save'] ) && check_admin_referer( 'manus_ai_save_action' ) ) {
            update_option( 'manus_ai_rewrite_enabled', isset( $_POST['manus_ai_rewrite_enabled'] ) );
            update_option( 'manus_ai_provider',        sanitize_text_field( $_POST['manus_ai_provider'] ?? 'none' ) );
            update_option( 'manus_ai_api_key',         sanitize_text_field( $_POST['manus_ai_api_key'] ?? '' ) );
            update_option( 'manus_ai_model',           sanitize_text_field( $_POST['manus_ai_model'] ?? '' ) );
            update_option( 'manus_ai_tone',            sanitize_text_field( $_POST['manus_ai_tone'] ?? 'jornalistico' ) );
            update_option( 'manus_ai_target_language', sanitize_text_field( $_POST['manus_ai_target_language'] ?? 'pt-BR' ) );
            update_option( 'manus_ai_min_words',       absint( $_POST['manus_ai_min_words'] ?? 200 ) );
            update_option( 'manus_ai_max_words',       absint( $_POST['manus_ai_max_words'] ?? 800 ) );
            wp_safe_redirect( add_query_arg( array( 'page' => 'manus-wp-reposter-ai', 'ai-saved' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $enabled   = (bool) get_option( 'manus_ai_rewrite_enabled', false );
        $provider  = get_option( 'manus_ai_provider', 'none' );
        $api_key   = get_option( 'manus_ai_api_key', '' );
        $model     = get_option( 'manus_ai_model', '' );
        $tone      = get_option( 'manus_ai_tone', 'jornalistico' );
        $lang      = get_option( 'manus_ai_target_language', 'pt-BR' );
        $min_words = (int) get_option( 'manus_ai_min_words', 200 );
        $max_words = (int) get_option( 'manus_ai_max_words', 800 );

        $providers = array(
            'none'   => '— Selecione um provedor —',
            'claude' => 'Anthropic Claude',
            'openai' => 'OpenAI (GPT)',
            'gemini' => 'Google Gemini',
            'groq'   => 'Groq (Llama 3 - Grátis)',
        );
        $tones = array(
            'jornalistico'  => 'Jornalístico (objetivo, terceira pessoa)',
            'informativo'   => 'Informativo (claro e didático)',
            'descontraido'  => 'Descontraído (acessível, próximo)',
            'institucional' => 'Institucional (formal, órgãos públicos)',
        );
        $languages = array(
            'pt-BR' => 'Português Brasileiro',
            'pt-PT' => 'Português Europeu',
            'es'    => 'Espanhol',
            'en'    => 'Inglês',
            'fr'    => 'Francês',
        );
        // Modelos pré-carregados para o provider salvo
        $current_models = class_exists( 'Manus_WP_Reposter_AI_Agent' )
            ? Manus_WP_Reposter_AI_Agent::get_models_for_provider( $provider )
            : array();
        ?>
        <div class="wrap">
            <h1>🤖 <?php _e( 'Agente de IA — Reescrita Inteligente', 'manus-wp-reposter' ); ?></h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php _e( 'Configurações salvas com sucesso!', 'manus-wp-reposter' ); ?></p></div>
            <?php endif; ?>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;max-width:780px;margin-top:16px;">
                <p style="font-size:14px;color:#555;margin-top:0;">
                    <?php _e( 'O Agente de IA reescreve cada notícia importada usando um modelo de linguagem de sua escolha. Em vez de uma tradução mecânica, o agente <strong>reformula o texto integralmente</strong>: melhora concordância, fluidez, elimina repetições e adapta o tom — tudo em uma única chamada à API.', 'manus-wp-reposter' ); ?>
                </p>
                <p style="font-size:13px;color:#777;">
                    <?php _e( '<strong>Fallback automático:</strong> se a chamada à IA falhar (timeout, cota, etc.), o plugin usa automaticamente o tradutor simples (DeepL/Google) como backup, garantindo que a importação não pare.', 'manus-wp-reposter' ); ?>
                </p>
            </div>

            <form method="post" action="" style="max-width:780px;margin-top:20px;">
                <?php wp_nonce_field( 'manus_ai_save_action' ); ?>
                <input type="hidden" name="manus_ai_save" value="1">

                <table class="form-table">

                    <tr>
                        <th scope="row"><?php _e( 'Ativar Agente de IA', 'manus-wp-reposter' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="manus_ai_rewrite_enabled" value="1" <?php checked( $enabled ); ?>>
                                <?php _e( 'Ativar reescrita inteligente via IA em todas as importações', 'manus-wp-reposter' ); ?>
                            </label>
                            <p class="description"><?php _e( 'Quando ativado, substitui a tradução mecânica. Configure o provedor e a chave abaixo.', 'manus-wp-reposter' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="manus_ai_provider"><?php _e( 'Provedor de IA', 'manus-wp-reposter' ); ?></label></th>
                        <td>
                            <select name="manus_ai_provider" id="manus_ai_provider" class="regular-text">
                                <?php foreach ( $providers as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $provider, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e( 'Claude (Anthropic) — rápido e com ótima qualidade para pt-BR. Groq oferece modelos Llama 3 gratuitos com alta velocidade.', 'manus-wp-reposter' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="manus_ai_api_key"><?php _e( 'Chave de API', 'manus-wp-reposter' ); ?></label></th>
                        <td>
                            <input type="password" id="manus_ai_api_key" name="manus_ai_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>"
                                   class="regular-text" autocomplete="new-password">
                            <button type="button" id="manus-ai-toggle-key" class="button button-small" style="margin-left:6px;">
                                <?php _e( 'Mostrar', 'manus-wp-reposter' ); ?>
                            </button>
                            <button type="button" id="manus-ai-test-btn" class="button button-secondary" style="margin-left:6px;">
                                <?php _e( 'Testar Conexão', 'manus-wp-reposter' ); ?>
                            </button>
                            <span id="manus-ai-test-result" style="margin-left:10px;font-weight:600;"></span>
                            <p class="description">
                                <?php _e( 'Claude: ', 'manus-wp-reposter' ); ?><a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a> &nbsp;|&nbsp;
                                <?php _e( 'OpenAI: ', 'manus-wp-reposter' ); ?><a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a> &nbsp;|&nbsp;
                                <?php _e( 'Gemini: ', 'manus-wp-reposter' ); ?><a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a> &nbsp;|&nbsp;
                                <?php _e( 'Groq (Grátis): ', 'manus-wp-reposter' ); ?><a href="https://console.groq.com/keys" target="_blank">console.groq.com</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="manus_ai_model"><?php _e( 'Modelo', 'manus-wp-reposter' ); ?></label></th>
                        <td>
                            <select name="manus_ai_model" id="manus_ai_model" class="regular-text">
                                <?php if ( empty( $current_models ) ) : ?>
                                    <option value=""><?php _e( '— Selecione um provedor primeiro —', 'manus-wp-reposter' ); ?></option>
                                <?php else : ?>
                                    <?php foreach ( $current_models as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $model, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php _e( 'Para pt-BR, recomendamos Claude Haiku (custo-benefício) ou Claude Sonnet (maior qualidade).', 'manus-wp-reposter' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="manus_ai_tone"><?php _e( 'Tom do Texto', 'manus-wp-reposter' ); ?></label></th>
                        <td>
                            <select name="manus_ai_tone" id="manus_ai_tone" class="regular-text">
                                <?php foreach ( $tones as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $tone, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Define como a IA deve redigir o texto. "Institucional" é ideal para órgãos públicos como o MPCE.', 'manus-wp-reposter' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="manus_ai_target_language"><?php _e( 'Idioma de Saída', 'manus-wp-reposter' ); ?></label></th>
                        <td>
                            <select name="manus_ai_target_language" id="manus_ai_target_language" class="regular-text">
                                <?php foreach ( $languages as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $lang, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'O agente sempre entrega o texto no idioma selecionado, independentemente do idioma de origem do feed.', 'manus-wp-reposter' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e( 'Tamanho do Texto', 'manus-wp-reposter' ); ?></th>
                        <td>
                            <label><?php _e( 'Mínimo:', 'manus-wp-reposter' ); ?>
                                <input type="number" name="manus_ai_min_words" value="<?php echo esc_attr( $min_words ); ?>" min="50" max="2000" step="50" style="width:90px;margin:0 8px;">
                                <?php _e( 'palavras', 'manus-wp-reposter' ); ?>
                            </label>
                            &nbsp;&nbsp;
                            <label><?php _e( 'Máximo:', 'manus-wp-reposter' ); ?>
                                <input type="number" name="manus_ai_max_words" value="<?php echo esc_attr( $max_words ); ?>" min="100" max="5000" step="50" style="width:90px;margin:0 8px;">
                                <?php _e( 'palavras', 'manus-wp-reposter' ); ?>
                            </label>
                            <p class="description"><?php _e( 'O agente tentará respeitar esses limites. Textos originais muito curtos podem não atingir o mínimo.', 'manus-wp-reposter' ); ?></p>
                        </td>
                    </tr>

                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e( 'Salvar Configurações', 'manus-wp-reposter' ); ?>">
                </p>
            </form>

            <div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:14px 18px;max-width:780px;margin-top:8px;">
                <strong><?php _e( '💡 Como usar:', 'manus-wp-reposter' ); ?></strong>
                <ol style="margin:8px 0 0 18px;font-size:13px;">
                    <li><?php _e( 'Escolha o provedor e insira sua chave de API.', 'manus-wp-reposter' ); ?></li>
                    <li><?php _e( 'Clique em <strong>Testar Conexão</strong> para confirmar que a chave funciona.', 'manus-wp-reposter' ); ?></li>
                    <li><?php _e( 'Escolha o modelo (Claude Haiku é o mais econômico), o tom e o idioma de saída.', 'manus-wp-reposter' ); ?></li>
                    <li><?php _e( 'Marque <strong>Ativar Agente de IA</strong> e salve.', 'manus-wp-reposter' ); ?></li>
                    <li><?php _e( 'A partir da próxima importação (manual ou automática), cada notícia será reescrita automaticamente.', 'manus-wp-reposter' ); ?></li>
                </ol>
            </div>

        </div>

        <script>
        (function($){
            // Toggle visibilidade da chave
            $('#manus-ai-toggle-key').on('click', function(){
                var $f = $('#manus_ai_api_key');
                var show = $f.attr('type') === 'password';
                $f.attr('type', show ? 'text' : 'password');
                $(this).text(show ? '<?php echo esc_js( __( 'Ocultar', 'manus-wp-reposter' ) ); ?>' : '<?php echo esc_js( __( 'Mostrar', 'manus-wp-reposter' ) ); ?>');
            });

            // Troca os modelos quando muda o provedor
            $('#manus_ai_provider').on('change', function(){
                var provider = $(this).val();
                var $modelSelect = $('#manus_ai_model');
                $modelSelect.html('<option><?php echo esc_js( __( 'Carregando...', 'manus-wp-reposter' ) ); ?></option>');
                $.post(ajaxurl, {
                    action: 'manus_ai_get_models',
                    provider: provider,
                    nonce: '<?php echo wp_create_nonce( 'manus_ai_nonce' ); ?>'
                }, function(resp){
                    $modelSelect.empty();
                    if(resp.success && resp.data.models){
                        $.each(resp.data.models, function(val, label){
                            $modelSelect.append('<option value="'+val+'">'+label+'</option>');
                        });
                    } else {
                        $modelSelect.append('<option value=""><?php echo esc_js( __( '— Selecione um provedor primeiro —', 'manus-wp-reposter' ) ); ?></option>');
                    }
                });
            });

            // Teste de conexão
            $('#manus-ai-test-btn').on('click', function(){
                var $btn = $(this);
                var $res = $('#manus-ai-test-result');
                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testando...', 'manus-wp-reposter' ) ); ?>');
                $res.css('color','#888').text('<?php echo esc_js( __( 'Aguarde...', 'manus-wp-reposter' ) ); ?>');
                $.post(ajaxurl, {
                    action:   'manus_ai_test_connection',
                    provider: $('#manus_ai_provider').val(),
                    api_key:  $('#manus_ai_api_key').val(),
                    model:    $('#manus_ai_model').val(),
                    nonce:    '<?php echo wp_create_nonce( 'manus_ai_nonce' ); ?>'
                }, function(resp){
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Testar Conexão', 'manus-wp-reposter' ) ); ?>');
                    if(resp.success){
                        $res.css('color','#1a7e3a').text('✓ ' + resp.data.message);
                    } else {
                        $res.css('color','#cc1818').text('✗ ' + (resp.data ? resp.data.message : '<?php echo esc_js( __( 'Erro desconhecido', 'manus-wp-reposter' ) ); ?>'));
                    }
                }).fail(function(){
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Testar Conexão', 'manus-wp-reposter' ) ); ?>');
                    $res.css('color','#cc1818').text('✗ <?php echo esc_js( __( 'Falha na comunicação AJAX', 'manus-wp-reposter' ) ); ?>');
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX: lista de modelos por provedor
    // -------------------------------------------------------------------------

    public function handle_ajax_ai_get_models() {
        check_ajax_referer( 'manus_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Permissão negada.' ) );

        $provider = sanitize_text_field( $_POST['provider'] ?? 'none' );
        $models   = class_exists( 'Manus_WP_Reposter_AI_Agent' )
            ? Manus_WP_Reposter_AI_Agent::get_models_for_provider( $provider )
            : array();

        wp_send_json_success( array( 'models' => $models ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: teste de conexão com a API de IA
    // -------------------------------------------------------------------------

    public function handle_ajax_ai_test() {
        check_ajax_referer( 'manus_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Permissão negada.' ) );

        // Usa os valores enviados pelo formulário (ainda não salvos), criando um agente temporário
        $provider = sanitize_text_field( $_POST['provider'] ?? 'none' );
        $api_key  = sanitize_text_field( $_POST['api_key']  ?? '' );
        $model    = sanitize_text_field( $_POST['model']    ?? '' );

        if ( empty( $api_key ) || $provider === 'none' ) {
            wp_send_json_error( array( 'message' => 'Informe o provedor e a chave de API antes de testar.' ) );
        }

        // Sobrescreve temporariamente as opções para o teste
        $prev_provider = get_option( 'manus_ai_provider' );
        $prev_key      = get_option( 'manus_ai_api_key' );
        $prev_model    = get_option( 'manus_ai_model' );
        $prev_enabled  = get_option( 'manus_ai_rewrite_enabled' );

        update_option( 'manus_ai_provider', $provider );
        update_option( 'manus_ai_api_key',  $api_key );
        update_option( 'manus_ai_model',    $model );
        update_option( 'manus_ai_rewrite_enabled', true );

        $agent  = new Manus_WP_Reposter_AI_Agent();
        $result = $agent->test_connection();

        // Restaura valores anteriores
        update_option( 'manus_ai_provider',        $prev_provider );
        update_option( 'manus_ai_api_key',         $prev_key );
        update_option( 'manus_ai_model',           $prev_model );
        update_option( 'manus_ai_rewrite_enabled', $prev_enabled );

        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => $result['message'] ) );
        } else {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }
    }


}



