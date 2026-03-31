<?php

/**
 * Classe para gerenciar configurações de automação avançada
 * Adiciona novas funcionalidades sem afetar o código existente
 */


class Manus_WP_Reposter_Automation_Settings {

    /**
     * Registra as novas configurações de automação
     */
    public static function register_automation_settings() {
        
        // Horário de repostagem automática
        register_setting(
            'manus_wp_reposter_settings',
            'manus_wp_reposter_daily_time',
            array(
                'type' => 'string',
                'sanitize_callback' => array( __CLASS__, 'sanitize_time_format' ),
                'default' => '09:00'
            )
        );

        // URL do feed para análise
        register_setting(
            'manus_wp_reposter_settings',
            'manus_wp_reposter_feed_url_analysis',
            array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => ''
            )
        );

        // Habilitar verificação de veracidade
        register_setting(
            'manus_wp_reposter_settings',
            'manus_wp_reposter_enable_veracity_check',
            array(
                'type' => 'boolean',
                'sanitize_callback' => array( __CLASS__, 'sanitize_boolean' ),
                'default' => false
            )
        );

        // Fontes confiáveis (JSON)
        register_setting(
            'manus_wp_reposter_settings',
            'manus_wp_reposter_trusted_sources',
            array(
                'type' => 'string',
                'sanitize_callback' => array( __CLASS__, 'sanitize_json_sources' ),
                'default' => '[]'
            )
        );

        // Última análise de feed
        register_setting(
            'manus_wp_reposter_settings',
            'manus_wp_reposter_last_feed_analysis',
            array(
                'type' => 'string',
                'sanitize_callback' => array( __CLASS__, 'sanitize_json_sources' ),
                'default' => '{}'
            )
        );

        // *** FEEDS AGENDADOS INDIVIDUAIS (hora + link + categoria + tradução) ***
        register_setting(
            'manus_wp_reposter_settings',
            'manus_wp_reposter_scheduled_feeds',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( __CLASS__, 'sanitize_scheduled_feeds' ),
                'default'           => '[]',
            )
        );
    }

    /**
     * Adiciona seção de configurações de automação
     */
    public static function add_automation_section() {
        add_settings_section(
            'manus_wp_reposter_automation',
            __( 'Configurações de Automação Avançada', 'manus-wp-reposter' ),
            array( __CLASS__, 'render_automation_section_description' ),
            'manus-wp-reposter'
        );
    }

    public static function handle_ajax_analyze_feed() {
        // 🔴 CAPTURAR QUALQUER SAÍDA INESPERADA
        ob_start();
        
        // 🔴 LOG DETALHADO
        error_log('🔵🔵🔵 MANUS AJAX: INICIANDO HANDLER 🔵🔵🔵');
        error_log('🔵 METHOD: ' . $_SERVER['REQUEST_METHOD']);
        error_log('🔵 POST DATA: ' . print_r($_POST, true));
        
        try {
            // Verificar se é uma requisição AJAX
            if ( ! wp_doing_ajax() ) {
                throw new Exception('Requisição não é AJAX');
            }
            
            // Verificar nonce
            if (!isset($_POST['nonce'])) {
                throw new Exception('Nonce não enviado');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'manus_analyze_feed_action')) {
                error_log('🔴 Nonce inválido - recebido: ' . $_POST['nonce']);
                error_log('🔴 Nonce esperado: ' . wp_create_nonce('manus_analyze_feed_action'));
                throw new Exception('Nonce inválido');
            }
            
            // Verificar permissões
            if (!current_user_can('manage_options')) {
                throw new Exception('Permissão negada');
            }
            
            // Obter URL
            $feed_url = isset($_POST['feed_url']) ? esc_url_raw(trim($_POST['feed_url'])) : '';
            if (empty($feed_url)) {
                throw new Exception('URL do feed vazia');
            }
            
            error_log('🔵 Analisando feed: ' . $feed_url);

            if (!function_exists('fetch_feed')) {
                require_once(ABSPATH . WPINC . '/feed.php');
            }

            // Chamar a análise real do feed
            $result = self::analyze_feed($feed_url);

            if ($result === false) {
                throw new Exception('Não foi possível analisar o feed. Verifique se a URL é válida e acessível.');
            }

            // Sincroniza a URL analisada com a option principal usada na importação,
            // evitando que o usuário precise preencher dois campos separados.
            update_option( 'manus_wp_reposter_feed_url', $feed_url );
            update_option( 'manus_wp_reposter_feed_url_analysis', $feed_url );

            // Persiste o resultado da análise para exibição na tela
            update_option( 'manus_wp_reposter_last_feed_analysis', wp_json_encode( array_merge(
                $result,
                array( 'feed_url' => $feed_url, 'analyzed_at' => current_time( 'mysql' ) )
            ) ) );

            // Limpar buffer e enviar resposta
            ob_end_clean();
            
            // 🔴 FORÇAR HEADER JSON
            header('Content-Type: application/json');
            
            echo json_encode(array(
                'success' => true,
                'data' => $result
            ));
            exit;
            
        } catch (Exception $e) {
            error_log('🔴 MANUS AJAX ERRO: ' . $e->getMessage());
            
            // Capturar qualquer saída inesperada
            $output = ob_get_clean();
            if (!empty($output)) {
                error_log('🔴 MANUS AJAX: Saída inesperada: ' . substr($output, 0, 500));
            }
            
            // 🔴 FORÇAR HEADER JSON MESMO EM ERRO
            header('Content-Type: application/json');
            http_response_code(200); // Manter 200 para o AJAX processar
            
            echo json_encode(array(
                'success' => false,
                'data' => array(
                    'message' => 'Erro na análise: ' . $e->getMessage(),
                    'debug' => array(
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'output' => substr($output, 0, 500),
                        'post_data' => $_POST
                    )
                )
            ));
            exit;
        }
    }

    /**
     * Renderiza descrição da seção de automação
     */
    public static function render_automation_section_description() {
        echo '<p>' . __( 'Configure o horário de repostagem automática, análise de veracidade e fontes confiáveis.', 'manus-wp-reposter' ) . '</p>';
    }

    /**
     * Renderiza campo de horário de repostagem
     */
    public static function render_daily_time_field() {
        $time = get_option( 'manus_wp_reposter_daily_time', '09:00' );
        ?>
        <input type="time" 
               name="manus_wp_reposter_daily_time" 
               value="<?php echo esc_attr( $time ); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e( 'Hora em que os posts serão repostados automaticamente (formato 24h).', 'manus-wp-reposter' ); ?>
        </p>
        <?php
    }

    /**
     * Renderiza campo de análise de feed
     */
    public static function render_feed_analysis_field() {
        // Usa a URL principal como fallback para não deixar o campo vazio
        // quando o usuário já configurou o feed nas Configurações Gerais.
        $feed_url = get_option( 'manus_wp_reposter_feed_url_analysis', '' );
        if ( empty( $feed_url ) ) {
            $feed_url = get_option( 'manus_wp_reposter_feed_url', '' );
        }
        $last_analysis = get_option( 'manus_wp_reposter_last_feed_analysis', '' );
        ?>
        <div style="margin-bottom: 15px;">
            <input type="url" 
                   name="manus_wp_reposter_feed_url_analysis" 
                   id="manus-feed-url-analysis" 
                   value="<?php echo esc_url( $feed_url ); ?>"
                   placeholder="https://exemplo.com/feed"
                   class="regular-text" />
            <button type="button" id="manus-analyze-feed-button" class="button button-secondary">
                <?php _e( 'Analisar Feed', 'manus-wp-reposter' ); ?>
            </button>
            <p class="description">
                <?php _e( 'URL do feed RSS. Ao clicar em "Analisar Feed", a URL é validada <strong>e automaticamente salva</strong> como feed principal para importação — não é necessário preencher o campo nas Configurações Gerais separadamente.', 'manus-wp-reposter' ); ?>
            </p>
        </div>

        <div id="manus-feed-analysis-result">
            <!-- O resultado da análise será carregado aqui via AJAX -->
        </div>

        <?php if ( ! empty( $feed_url ) ) : ?>
            <div style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                <p>
                    <strong><?php _e( 'Análise do Feed:', 'manus-wp-reposter' ); ?></strong><br>
                    <small><?php echo esc_html( $feed_url ); ?></small>
                </p>
                <?php
                // A análise do feed será feita via AJAX para evitar problemas de carregamento de dependências
                // e para fornecer feedback assíncrono ao usuário.
                $last_analysis_data = json_decode( get_option( 'manus_wp_reposter_last_feed_analysis', '{}' ), true ) ?? [];
                if ( ! empty( $last_analysis_data ) && isset( $last_analysis_data['feed_url'] ) && $last_analysis_data['feed_url'] === $feed_url ) {
                    echo '<div class="manus-feed-analysis-box">';
                    echo '<p>';
                    echo '<strong>' . __( 'Última Análise:', 'manus-wp-reposter' ) . '</strong><br>';
                    echo '</p>';
                    echo '<div style="margin-top: 10px;">';
                    echo '<p><strong>' . __( 'Status da Análise:', 'manus-wp-reposter' ) . '</strong></p>';
                    echo '<ul style="margin: 5px 0; padding-left: 20px;">';
                    printf( '<li>%s: <strong class="%s">%s</strong></li>', 
                        __( 'Feed Válido', 'manus-wp-reposter' ), 
                        $last_analysis_data['valid'] ? 'manus-feed-analysis-valid' : 'manus-feed-analysis-invalid', 
                        $last_analysis_data['valid'] ? '✓ Sim' : '✗ Não' 
                    );
                    printf( '<li>%s: <strong>%d</strong></li>', 
                        __( 'Itens Encontrados', 'manus-wp-reposter' ), 
                        $last_analysis_data['item_count'] 
                    );
                    printf( '<li>%s: <strong>%s</strong></li>', 
                        __( 'Última Atualização', 'manus-wp-reposter' ), 
                        esc_html( $last_analysis_data['last_update'] ) 
                    );
                    printf( '<li>%s: <strong>%s</strong></li>', 
                        __( 'Domínio', 'manus-wp-reposter' ), 
                        esc_html( $last_analysis_data['domain'] ) 
                    );
                    echo '</ul>';
                    echo '</div>';
                    echo '</div>';
                } elseif ( ! empty( $feed_url ) ) {
                    echo '<p class="manus-automation-warning">' . __( 'Clique em "Analisar Feed" para verificar a URL.', 'manus-wp-reposter' ) . '</p>';
                }
                ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Renderiza campo de verificação de veracidade
     */
    public static function render_veracity_check_field() {
        $enabled = get_option( 'manus_wp_reposter_enable_veracity_check', false );
        ?>
        <label>
            <input type="checkbox" 
                   name="manus_wp_reposter_enable_veracity_check" 
                   value="1" 
                   <?php checked( $enabled ); ?> />
            <?php _e( 'Ativar verificação de veracidade de notícias', 'manus-wp-reposter' ); ?>
        </label>
        <p class="description">
            <?php _e( 'Quando ativado, apenas notícias de fontes confiáveis serão importadas automaticamente.', 'manus-wp-reposter' ); ?>
        </p>
        <?php
    }

    /**
     * Renderiza campo de fontes confiáveis
     */
    public static function render_trusted_sources_field() {
        $sources_json = get_option( 'manus_wp_reposter_trusted_sources', '[]' );
        $sources = json_decode( $sources_json, true );
        if ( ! is_array( $sources ) ) {
            $sources = array();
        }

        $last_analysis_data = json_decode( get_option( 'manus_wp_reposter_last_feed_analysis', '{}' ), true ) ?? [];
        ?>
        <div style="margin-bottom: 15px;">
            <p><strong><?php _e( 'Fontes Confiáveis:', 'manus-wp-reposter' ); ?></strong></p>
            <div id="manus-trusted-sources-list" style="margin-bottom: 10px;">
                <?php foreach ( $sources as $index => $source ) : ?>
                    <div style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">
                        <input type="text" 
                               class="manus-source-domain" 
                               placeholder="exemplo.com" 
                               value="<?php echo esc_attr( $source['domain'] ); ?>"
                               style="flex: 1;" />
                        <input type="text" 
                               class="manus-source-name" 
                               placeholder="Nome da Fonte" 
                               value="<?php echo esc_attr( $source['name'] ); ?>"
                               style="flex: 1;" />
                        <button type="button" 
                                class="button button-secondary manus-remove-source" 
                                onclick="this.parentElement.remove();">
                            <?php _e( 'Remover', 'manus-wp-reposter' ); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" 
                    class="button button-secondary" 
                    onclick="manusAddTrustedSource();">
                <?php _e( '+ Adicionar Fonte', 'manus-wp-reposter' ); ?>
            </button>
            <input type="hidden" 
                   name="manus_wp_reposter_trusted_sources" 
                   id="manus-trusted-sources-input" 
                   value="<?php echo esc_attr( $sources_json ); ?>" />
            <input type="hidden" 
                   name="manus_wp_reposter_last_feed_analysis" 
                   id="manus-last-feed-analysis-input" 
                   value="<?php echo esc_attr( json_encode( $last_analysis_data ) ); ?>" />
            <p class="description">
                <?php _e( 'Adicione os domínios das fontes que você confia. Exemplo: bbc.com, reuters.com, etc.', 'manus-wp-reposter' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Sanitiza formato de hora
     */
    public static function sanitize_time_format( $value ) {
        if ( preg_match( '/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
            return $value;
        }
        return '09:00';
    }

    /**
     * Sanitiza valores booleanos
     */
    public static function sanitize_boolean( $value ) {
        return $value === '1' || $value === true;
    }

    /**
     * Sanitiza e valida JSON de fontes
     */
    public static function sanitize_json_sources( $value ) {
        if ( empty( $value ) ) {
            return '[]';
        }
        
        $decoded = json_decode( $value, true );
        if ( ! is_array( $decoded ) ) {
            return '[]';
        }

        $sanitized = array();
        foreach ( $decoded as $source ) {
            if ( isset( $source['domain'] ) && isset( $source['name'] ) ) {
                $sanitized[] = array(
                    'domain' => sanitize_text_field( $source['domain'] ),
                    'name' => sanitize_text_field( $source['name'] ),
                );
            }
        }

        return json_encode( $sanitized );
    }

    /**
     * Analisa um feed RSS (VERSÃO SEM USAR FUNÇÕES DO WORDPRESS)
     */
    public static function analyze_feed($feed_url) {
        error_log('=== MANUS: Iniciando análise do feed (versão simplificada) ===');
        error_log('URL: ' . $feed_url);
        
        if (empty($feed_url)) {
            error_log('ERRO: URL vazia');
            return false;
        }

        try {
            // Fazer requisição HTTP diretamente
            $response = wp_remote_get($feed_url, array(
                'timeout' => 30,
                'user-agent' => 'Mozilla/5.0 (compatible; Manus WP Reposter; +' . home_url() . ')',
                'sslverify' => false
            ));

            if (is_wp_error($response)) {
                error_log('ERRO wp_remote_get: ' . $response->get_error_message());
                return false;
            }

            $code = wp_remote_retrieve_response_code($response);
            error_log('Status HTTP: ' . $code);

            if ($code !== 200) {
                error_log('ERRO: Status HTTP ' . $code);
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                error_log('ERRO: Corpo da resposta vazio');
                return false;
            }

            // Suprimir erros do parser XML e usar LIBXML_NOERROR para feeds
            // com declarações de namespace (Atom, RSS 1.0/RDF, etc.)
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            if ($xml === false) {
                // Última tentativa: usar fetch_feed do WordPress (SimplePie)
                error_log('SimpleXML falhou, tentando via fetch_feed (SimplePie)...');
                if (!function_exists('fetch_feed')) {
                    require_once(ABSPATH . WPINC . '/feed.php');
                }
                $sp_feed = fetch_feed($feed_url);
                if (is_wp_error($sp_feed)) {
                    error_log('ERRO fetch_feed: ' . $sp_feed->get_error_message());
                    return false;
                }
                $item_count  = $sp_feed->get_item_quantity();
                $feed_title  = $sp_feed->get_title();
                $last_item   = $sp_feed->get_item(0);
                $last_update = $last_item ? $last_item->get_date('d/m/Y H:i') : '';
                $domain      = wp_parse_url($feed_url, PHP_URL_HOST);
                error_log('fetch_feed OK — itens: ' . $item_count);
                return array(
                    'valid'       => true,
                    'item_count'  => $item_count,
                    'last_update' => $last_update ?: __('Desconhecido', 'manus-wp-reposter'),
                    'domain'      => $domain,
                    'feed_title'  => $feed_title ?: __('Feed sem título', 'manus-wp-reposter'),
                    'debug'       => array('Análise feita via SimplePie (fetch_feed)'),
                );
            }

            error_log('XML carregado com sucesso!');

            // Registrar todos os namespaces declarados no feed
            $namespaces = $xml->getNamespaces(true);

            $item_count  = 0;
            $last_update = '';
            $feed_title  = '';

            // --- RSS 2.0 ---
            if (isset($xml->channel)) {
                $feed_title = isset($xml->channel->title) ? (string)$xml->channel->title : '';
                if (isset($xml->channel->item)) {
                    $item_count = count($xml->channel->item);
                    if ($item_count > 0) {
                        $first_item = $xml->channel->item[0];
                        if (isset($first_item->pubDate)) {
                            $last_update = (string)$first_item->pubDate;
                        } elseif (isset($namespaces['dc'])) {
                            $dc = $first_item->children($namespaces['dc']);
                            if (isset($dc->date)) {
                                $last_update = (string)$dc->date;
                            }
                        }
                    }
                }
            }
            // --- Atom (elemento raiz: <feed>) ---
            elseif ($xml->getName() === 'feed' || isset($xml->entry)) {
                $feed_title = isset($xml->title) ? (string)$xml->title : '';
                $entries = $xml->entry;
                $item_count = count($entries);
                if ($item_count > 0) {
                    $first = $entries[0];
                    if (isset($first->updated)) {
                        $last_update = (string)$first->updated;
                    } elseif (isset($first->published)) {
                        $last_update = (string)$first->published;
                    }
                }
            }
            // --- RSS 1.0 / RDF ---
            else {
                // RDF: namespace rss no nivel do documento, itens como <item>
                $rss_ns = isset($namespaces['rss']) ? $namespaces['rss'] : 'http://purl.org/rss/1.0/';
                $dc_ns  = isset($namespaces['dc'])  ? $namespaces['dc']  : 'http://purl.org/dc/elements/1.1/';
                $channel = $xml->children($rss_ns)->channel;
                if ($channel && isset($channel->title)) {
                    $feed_title = (string)$channel->title;
                }
                $items = $xml->children($rss_ns)->item;
                if ($items) {
                    $item_count = count($items);
                    if ($item_count > 0) {
                        $dc = $items[0]->children($dc_ns);
                        if (isset($dc->date)) {
                            $last_update = (string)$dc->date;
                        }
                    }
                }
                // Fallback: tenta simples <item> sem namespace
                if ($item_count === 0 && isset($xml->item)) {
                    $item_count = count($xml->item);
                    if ($item_count > 0 && isset($xml->item[0]->pubDate)) {
                        $last_update = (string)$xml->item[0]->pubDate;
                    }
                }
            }

            error_log('Itens encontrados: ' . $item_count);

            $domain = wp_parse_url($feed_url, PHP_URL_HOST);
            error_log('Domínio: ' . $domain);

            return array(
                'valid'       => true,
                'item_count'  => $item_count,
                'last_update' => $last_update ?: __('Desconhecido', 'manus-wp-reposter'),
                'domain'      => $domain,
                'feed_title'  => $feed_title ?: __('Feed sem título', 'manus-wp-reposter'),
                'debug'       => array('Análise feita com SimpleXML'),
            );

        } catch (Exception $e) {
            error_log('ERRO na análise: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se uma URL pertence a uma fonte confiável
     */
    public static function is_from_trusted_source( $url ) {
        $enabled = get_option( 'manus_wp_reposter_enable_veracity_check', false );
        
        if ( ! $enabled ) {
            return true; // Se verificação desativada, aceita tudo
        }

        $sources_json = get_option( 'manus_wp_reposter_trusted_sources', '[]' );
        $sources = json_decode( $sources_json, true );

        if ( ! is_array( $sources ) || empty( $sources ) ) {
            return true; // Se não há fontes configuradas, aceita tudo
        }

        $url_domain = wp_parse_url( $url, PHP_URL_HOST );
        $url_domain = str_replace( 'www.', '', $url_domain );

        foreach ( $sources as $source ) {
            $source_domain = str_replace( 'www.', '', $source['domain'] );
            if ( stripos( $url_domain, $source_domain ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitiza a lista de feeds agendados (JSON array)
     */
    public static function sanitize_scheduled_feeds( $value ) {
        if ( empty( $value ) ) {
            return '[]';
        }
        $decoded = json_decode( $value, true );
        if ( ! is_array( $decoded ) ) {
            return '[]';
        }
        $sanitized = array();
        foreach ( $decoded as $feed ) {
            $entry = array(
                'id'          => ! empty( $feed['id'] ) ? sanitize_text_field( $feed['id'] ) : uniqid( 'feed_', true ),
                'enabled'     => ! empty( $feed['enabled'] ) ? true : false,
                'feed_url'    => ! empty( $feed['feed_url'] ) ? esc_url_raw( trim( $feed['feed_url'] ) ) : '',
                'time'        => ! empty( $feed['time'] ) ? sanitize_text_field( $feed['time'] ) : '09:00',
                'quantity'    => isset( $feed['quantity'] ) ? max( 1, min( 20, (int) $feed['quantity'] ) ) : 1,
                'category'    => isset( $feed['category'] ) ? (int) $feed['category'] : 0,
                'translate'   => ! empty( $feed['translate'] ) ? true : false,
                'post_status' => ! empty( $feed['post_status'] ) ? sanitize_text_field( $feed['post_status'] ) : 'publish',
                'last_run'    => ! empty( $feed['last_run'] ) ? sanitize_text_field( $feed['last_run'] ) : '',
            );
            if ( empty( $entry['feed_url'] ) ) {
                continue; // Ignora entradas sem URL
            }
            // Valida formato do horário
            if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $entry['time'] ) ) {
                $entry['time'] = '09:00';
            }
            $sanitized[] = $entry;
        }
        return wp_json_encode( $sanitized );
    }

    /**
     * Renderiza o campo de feeds agendados individuais
     */
    public static function render_scheduled_feeds_field() {
        $feeds_json = get_option( 'manus_wp_reposter_scheduled_feeds', '[]' );
        $feeds      = json_decode( $feeds_json, true );
        if ( ! is_array( $feeds ) ) {
            $feeds = array();
        }

        // Buscar categorias disponíveis para os selects
        $categories = get_categories( array( 'hide_empty' => false ) );
        ?>
        <div id="manus-scheduled-feeds-wrapper">
            <p class="description" style="margin-bottom: 12px;">
                <?php _e( 'Configure múltiplos feeds com horário, categoria e tradução individuais. Cada feed será processado automaticamente no horário definido.', 'manus-wp-reposter' ); ?>
            </p>

            <div id="manus-scheduled-feeds-list">
                <?php if ( ! empty( $feeds ) ) : ?>
                    <?php foreach ( $feeds as $index => $feed ) : ?>
                        <?php self::render_scheduled_feed_row( $feed, $index, $categories ); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button type="button" class="button button-secondary" id="manus-add-scheduled-feed" style="margin-top: 10px;">
                <?php _e( '+ Adicionar Feed Agendado', 'manus-wp-reposter' ); ?>
            </button>

            <input type="hidden"
                   name="manus_wp_reposter_scheduled_feeds"
                   id="manus-scheduled-feeds-input"
                   value="<?php echo esc_attr( $feeds_json ); ?>" />
        </div>

        <?php
        // Template para novos feeds (oculto)
        $empty_feed = array(
            'id'          => '__NEW__',
            'enabled'     => true,
            'feed_url'    => '',
            'time'        => '09:00',
            'quantity'    => 1,
            'category'    => 0,
            'translate'   => false,
            'post_status' => 'publish',
            'last_run'    => '',
        );
        echo '<template id="manus-feed-row-template">';
        self::render_scheduled_feed_row( $empty_feed, '__IDX__', $categories );
        echo '</template>';
        ?>

        <script>
        (function($) {
            var feedIndex = <?php echo count( $feeds ); ?>;

            function updateFeedsInput() {
                var feeds = [];
                $('#manus-scheduled-feeds-list .manus-feed-row').each(function() {
                    var $row = $(this);
                    feeds.push({
                        id:          $row.find('.mf-id').val(),
                        enabled:     $row.find('.mf-enabled').is(':checked'),
                        feed_url:    $row.find('.mf-url').val(),
                        time:        $row.find('.mf-time').val(),
                        quantity:    $row.find('.mf-quantity').val(),
                        category:    $row.find('.mf-category').val(),
                        translate:   $row.find('.mf-translate').is(':checked'),
                        post_status: $row.find('.mf-post-status').val(),
                        last_run:    $row.find('.mf-last-run').val(),
                    });
                });
                $('#manus-scheduled-feeds-input').val(JSON.stringify(feeds));
            }

            $(document).on('change input', '#manus-scheduled-feeds-list input, #manus-scheduled-feeds-list select', updateFeedsInput);

            $(document).on('click', '.manus-remove-feed-row', function() {
                $(this).closest('.manus-feed-row').remove();
                updateFeedsInput();
            });

            $('#manus-add-scheduled-feed').on('click', function() {
                var template = document.getElementById('manus-feed-row-template');
                var html = template.innerHTML.replace(/__IDX__/g, feedIndex++).replace(/__NEW__/g, 'feed_' + Date.now());
                $('#manus-scheduled-feeds-list').append(html);
                updateFeedsInput();
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Renderiza uma linha de feed agendado
     */
    private static function render_scheduled_feed_row( $feed, $index, $categories ) {
        $last_run_label = ! empty( $feed['last_run'] ) ? esc_html( $feed['last_run'] ) : __( 'Nunca', 'manus-wp-reposter' );
        ?>
        <div class="manus-feed-row" style="background:#f9f9f9; border:1px solid #ddd; padding:12px; margin-bottom:10px; border-radius:4px;">
            <input type="hidden" class="mf-id" value="<?php echo esc_attr( $feed['id'] ); ?>" />
            <input type="hidden" class="mf-last-run" value="<?php echo esc_attr( $feed['last_run'] ?? '' ); ?>" />

            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:4px 8px 4px 0; white-space:nowrap; vertical-align:middle; width:80px;">
                        <label>
                            <input type="checkbox" class="mf-enabled" <?php checked( ! empty( $feed['enabled'] ) ); ?> />
                            <strong><?php _e( 'Ativo', 'manus-wp-reposter' ); ?></strong>
                        </label>
                    </td>
                    <td style="padding:4px 8px; vertical-align:middle;">
                        <label style="font-size:11px; display:block;"><?php _e( 'URL do Feed RSS', 'manus-wp-reposter' ); ?></label>
                        <input type="url" class="mf-url regular-text" style="width:100%;"
                               value="<?php echo esc_attr( $feed['feed_url'] ?? '' ); ?>"
                               placeholder="https://exemplo.com/feed" />
                    </td>
                    <td style="padding:4px 8px; vertical-align:middle; white-space:nowrap; width:120px;">
                        <label style="font-size:11px; display:block;"><?php _e( 'Horário', 'manus-wp-reposter' ); ?></label>
                        <input type="time" class="mf-time"
                               value="<?php echo esc_attr( $feed['time'] ?? '09:00' ); ?>" />
                    </td>
                    <td style="padding:4px 8px; vertical-align:middle; white-space:nowrap; width:80px;">
                        <label style="font-size:11px; display:block;"><?php _e( 'Qtd', 'manus-wp-reposter' ); ?></label>
                        <input type="number" class="mf-quantity" min="1" max="20" style="width:60px;"
                               value="<?php echo esc_attr( $feed['quantity'] ?? 1 ); ?>" />
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:4px 8px 4px 0; vertical-align:middle;">
                        <label style="font-size:11px; display:block;"><?php _e( 'Categoria', 'manus-wp-reposter' ); ?></label>
                        <select class="mf-category">
                            <option value="0"><?php _e( '— Usar padrão —', 'manus-wp-reposter' ); ?></option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo (int) $cat->term_id; ?>"
                                    <?php selected( (int) ( $feed['category'] ?? 0 ), (int) $cat->term_id ); ?>>
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="padding:4px 8px; vertical-align:middle;">
                        <label style="font-size:11px; display:block;"><?php _e( 'Status', 'manus-wp-reposter' ); ?></label>
                        <select class="mf-post-status">
                            <option value="publish" <?php selected( $feed['post_status'] ?? 'publish', 'publish' ); ?>><?php _e( 'Publicar', 'manus-wp-reposter' ); ?></option>
                            <option value="draft"   <?php selected( $feed['post_status'] ?? 'publish', 'draft' ); ?>><?php _e( 'Rascunho', 'manus-wp-reposter' ); ?></option>
                            <option value="pending" <?php selected( $feed['post_status'] ?? 'publish', 'pending' ); ?>><?php _e( 'Pendente', 'manus-wp-reposter' ); ?></option>
                        </select>
                    </td>
                    <td style="padding:4px 8px; vertical-align:middle; text-align:right;">
                        <label>
                            <input type="checkbox" class="mf-translate" <?php checked( ! empty( $feed['translate'] ) ); ?> />
                            <?php _e( 'Traduzir', 'manus-wp-reposter' ); ?>
                        </label>
                        <br>
                        <button type="button" class="button button-link-delete manus-remove-feed-row" style="margin-top:4px; color:#b32d2e;">
                            <?php _e( '✕ Remover', 'manus-wp-reposter' ); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding:2px 0 0 0; font-size:11px; color:#888;">
                        <?php printf( __( 'Última execução: %s', 'manus-wp-reposter' ), $last_run_label ); ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Handler AJAX para forçar execução imediata de todos os feeds agendados (teste)
     */
    public static function handle_ajax_run_scheduled_feeds_now() {
        if ( ! check_ajax_referer( 'manus_run_scheduled_now', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada' ) );
        }

        // Força execução do cron agora
        do_action( 'manus_wp_reposter_daily_import' );

        wp_send_json_success( array( 'message' => 'Feeds agendados executados com sucesso.' ) );
    }
}
