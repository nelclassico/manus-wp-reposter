<?php
/**
 * Classe de importação do plugin
 */
class Manus_WP_Reposter_Importer {

    private $translator;
    private $extractor;
    private $logger;

    /**
     * Construtor - recebe dependências
     */
    public function __construct( $translator = null, $extractor = null ) {
        $this->translator = $translator;
        $this->extractor = $extractor;

        // Tenta obter o logger
        if ( class_exists( 'Manus_WP_Reposter_Logger' ) ) {
            $this->logger = Manus_WP_Reposter_Logger::instance();
        }

        $this->log( 'INFO', 'Importador inicializado' );
    }


    /**
     * Executa importação diária automática
     */
    /**
     * Executa importação diária automática - VERSÃO CORRIGIDA E SEGURA
     */
    public function run_daily_import() {
        $this->log( 'INFO', 'Iniciando importação diária automática' );

        // VERIFICAÇÃO DE DEPENDÊNCIAS
        error_log('=== MANUS AUTO-POST DEBUG ===');

        // Se não temos tradutor/extrator, tenta obter do plugin principal
        if ( !$this->translator || !$this->extractor ) {
            error_log('AUTO-POST: Dependências não carregadas, tentando obter dinamicamente...');

            // Tenta obter a instância principal do plugin
            if ( class_exists('Manus_WP_Reposter') && method_exists('Manus_WP_Reposter', 'instance') ) {
                $plugin = Manus_WP_Reposter::instance();

                // Se o plugin tiver métodos getters
                if ( !$this->translator && method_exists($plugin, 'get_translator') ) {
                    $this->translator = $plugin->get_translator();
                    error_log('AUTO-POST: Tradutor obtido dinamicamente');
                }

                if ( !$this->extractor && method_exists($plugin, 'get_extractor') ) {
                    $this->extractor = $plugin->get_extractor();
                    error_log('AUTO-POST: Extrator obtido dinamicamente');
                }
            }
        }

        error_log('AUTO-POST: Tradutor: ' . ($this->translator ? 'OK' : 'FALTA'));
        error_log('AUTO-POST: Extrator: ' . ($this->extractor ? 'OK' : 'FALTA'));

        $feed_url = get_option( 'manus_wp_reposter_feed_url' );

        if ( empty( $feed_url ) ) {
            $this->log( 'ERROR', 'URL do feed não configurada nas configurações' );
            return;
        }

        // Opções da importação automática
        $translate_option = get_option( 'manus_wp_reposter_auto_translate', false );

        // Força a tradução para TRUE na importação diária, conforme solicitado pelo usuário
        $should_translate = (bool) $this->translator;


        $result = $this->import_from_feed( $feed_url, 3, array(
            'mode' => 'normal',
            'translate' => true,
            'download_images' => true,
            'set_featured' => true,
            'post_status' => 'draft',
        ) );

        $this->log( 'INFO', sprintf(
            'Importação diária concluída: %d importados, %d pulados, %d falhas',
            $result['imported'],
            $result['skipped'],
            $result['failed']
        ) );
    }

    /**
     * Executa importação manual
     */
    public function run_manual_import( $quantity = 5, $options = array() ) {
        $this->log( 'INFO', 'Iniciando importação manual', array(
            'quantity' => $quantity,
            'options' => $options
        ) );

        $default_options = array(
            'mode' => 'normal',
            'translate' => false,
            'download_images' => true,
            'set_featured' => true,
            'post_status' => 'draft',
        );

        $options = wp_parse_args( $options, $default_options );

        $feed_url = get_option( 'manus_wp_reposter_feed_url' );

        if ( empty( $feed_url ) ) {
            $this->log( 'ERROR', 'URL do feed não configurada nas configurações' );
            return array(
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
                'error' => 'Configure a URL do feed RSS nas configurações primeiro.',
            );
        }

        $result = $this->import_from_feed( $feed_url, $quantity, $options );

        $this->log( 'INFO', sprintf(
            'Importação manual concluída: %d importados, %d pulados, %d falhas',
            $result['imported'],
            $result['skipped'],
            $result['failed']
        ) );

        return $result;
    }

    /**
     * Importa de um feed RSS
     */
    private function import_from_feed( $feed_url, $quantity, $options ) {
        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'items' => array(),
        );

        try {
            $this->log( 'INFO', 'Buscando feed: ' . $feed_url );

            // Usa fetch_feed do WordPress
            add_filter( 'wp_feed_cache_transient_lifetime', function() {
                return 1800; // 30 minutos de cache
            } );

            $feed = fetch_feed( $feed_url );

            remove_filter( 'wp_feed_cache_transient_lifetime', '__return_zero' );

            if ( is_wp_error( $feed ) ) {
                $this->log( 'ERROR', 'Erro ao buscar feed: ' . $feed->get_error_message() );
                $result['error'] = $feed->get_error_message();
                return $result;
            }

            $this->log( 'INFO', 'Feed encontrado: ' . $feed->get_title() );

            // Obtém itens
            $max_items = $feed->get_item_quantity( $quantity );
            $items = $feed->get_items( 0, $max_items );

            if ( empty( $items ) ) {
                $this->log( 'WARNING', 'Nenhum item encontrado no feed' );
                return $result;
            }

            $this->log( 'INFO', sprintf( 'Encontrados %d itens no feed', count( $items ) ) );

            // Processa do mais antigo para o mais novo
            $items = array_reverse( $items );

            foreach ( $items as $item ) {
                $item_result = $this->process_feed_item( $item, $feed, $options );

                if ( $item_result['status'] === 'imported' ) {
                    $result['imported']++;
                    $result['items'][] = array(
                        'id' => $item_result['post_id'],
                        'title' => $item_result['title'],
                        'status' => 'imported',
                    );
                } elseif ( $item_result['status'] === 'skipped' ) {
                    $result['skipped']++;
                } else {
                    $result['failed']++;
                    $result['items'][] = array(
                        'title' => $item_result['title'],
                        'status' => 'failed',
                        'error' => $item_result['error'],
                    );
                }
            }

        } catch ( Exception $e ) {
            $this->log( 'ERROR', 'Erro na importação: ' . $e->getMessage() );
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Processa um item do feed
     */
    private function process_feed_item( $item, $feed, $options ) {
        $item_url = $item->get_permalink();
        $item_title = $item->get_title();

        $this->log( 'INFO', 'Processando item: ' . $item_title, array(
            'url' => $item_url,
        ) );

        // Verifica se já foi importado (modo normal)
        if ( $options['mode'] === 'normal' && $this->is_already_imported( $item_url ) ) {
            $this->log( 'INFO', 'Item já importado anteriormente: ' . $item_title );
            return array(
                'status' => 'skipped',
                'title' => $item_title,
                'reason' => 'already_imported',
            );
        }

        try {
            // Verifica se temos o extrator
            if ( ! $this->extractor ) {
                throw new Exception( 'Extrator de conteúdo não disponível' );
            }

            // Extrai conteúdo completo
            $this->log( 'INFO', 'Extraindo conteúdo: ' . $item_url );
            $extraction = $this->extractor->extract_content( $item_url );

            if ( ! $extraction['success'] || empty( $extraction['content'] ) ) {
                $this->log( 'WARNING', 'Não foi possível extrair conteúdo, usando resumo do feed' );

                // Usa resumo do feed como fallback
                $content = $item->get_description();
                $title = $item_title;
                $featured_image = '';

                if ( empty( $content ) ) {
                    throw new Exception( 'Não foi possível obter conteúdo do item' );
                }
            } else {
                $content = $extraction['content'];
                $title = ! empty( $extraction['title'] ) ? $extraction['title'] : $item_title;
                $featured_image = $extraction['featured_image'];
            }

            // Traduz se necessário
            if ( $options['translate'] && $this->translator ) {
                $this->log( 'INFO', 'Traduzindo conteúdo' );

                // Detecta idioma
                $language = $this->translator->detect_language( $content );

                if ( $language === 'en' ) {
                    // Traduz título
                    $translated_title = $this->translator->translate_text( $title, 'en', 'pt' );

                    // Traduz conteúdo
                    $content = $this->translator->translate_html( $content, 'en', 'pt' );

                    $title = $translated_title ?: $title;
                    $this->log( 'INFO', 'Conteúdo traduzido com sucesso' );
                }
            }

            // Limpa e formata conteúdo
            $content = $this->clean_and_format_content( $content );

            // Adiciona atribuição de crédito
            $content = $this->add_credit_attribution( $content, $item_url, $feed );

            // Cria post
            $post_id = $this->create_post( $title, $content, $item, $options );

            if ( ! $post_id ) {
                throw new Exception( 'Falha ao criar post' );
            }

            // Define imagem destacada se disponível
            if ( $options['set_featured'] && ! empty( $featured_image ) ) {
                $this->set_featured_image( $post_id, $featured_image, $title );
            }

            // Salva meta informações
            $this->save_post_meta( $post_id, $item_url, $feed );

            $this->log( 'SUCCESS', 'Item importado com sucesso: ' . $title, array(
                'post_id' => $post_id,
                'url' => $item_url,
            ) );

            return array(
                'status' => 'imported',
                'post_id' => $post_id,
                'title' => $title,
            );

        } catch ( Exception $e ) {
            $this->log( 'ERROR', 'Erro ao processar item: ' . $e->getMessage(), array(
                'item' => $item_title,
                'url' => $item_url,
            ) );

            return array(
                'status' => 'failed',
                'title' => $item_title,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Verifica se URL já foi importada
     */
    private function is_already_imported( $url ) {
        $args = array(
            'post_type'  => 'post',
            'meta_key'   => '_manus_original_url',
            'meta_value' => $url,
            'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
            'posts_per_page' => 1,
            'fields' => 'ids',
        );

        $posts = get_posts( $args );
        return ! empty( $posts );
    }

    /**
     * Limpa e formata conteúdo
     */
    private function clean_and_format_content( $content ) {
        // Remove scripts e estilos
        $content = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $content );
        $content = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $content );

        // Remove comentários HTML
        $content = preg_replace( '/<!--.*?-->/s', '', $content );

        // Remove múltiplos espaços e quebras
        $content = preg_replace( '/\s+/', ' ', $content );

        // Adiciona parágrafos se necessário
        if ( ! preg_match( '/<p>/', $content ) ) {
            $content = wpautop( $content );
        }

        return trim( $content );
    }

    /**
     * Adiciona atribuição de crédito
     */
    private function add_credit_attribution( $content, $url, $feed ) {
        $source_name = $feed->get_title();
        $source_url = $feed->get_link();

        $credit = "\n\n<div class=\"manus-reposter-credit\" style=\"";
        $credit .= "border-left: 4px solid #007cba;";
        $credit .= "padding: 15px;";
        $credit .= "background-color: #f8f9fa;";
        $credit .= "margin: 20px 0;";
        $credit .= "border-radius: 4px;";
        $credit .= "font-size: 14px;";
        $credit .= "\">";

        $credit .= '<p style="margin: 0 0 8px 0; font-weight: bold; color: #007cba;">';
        $credit .= __( 'Fonte Original:', 'manus-wp-reposter' );
        $credit .= '</p>';

        if ( $source_name ) {
            $credit .= '<p style="margin: 0 0 5px 0;">';
            $credit .= '<strong>' . esc_html( $source_name ) . '</strong>';

            if ( $source_url ) {
                $credit .= ' (<a href="' . esc_url( $source_url ) . '" target="_blank" rel="nofollow noopener">';
                $credit .= __( 'Site', 'manus-wp-reposter' ) . '</a>)';
            }
            $credit .= '</p>';
        }

        $credit .= '<p style="margin: 0;">';
        $credit .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">';
        $credit .= __( 'Artigo original', 'manus-wp-reposter' ) . '</a>';
        $credit .= ' - ' . __( 'Publicado via Manus WP Reposter', 'manus-wp-reposter' );
        $credit .= '</p>';
        $credit .= '</div>';

        return $content . $credit;
    }

    /**
     * Cria post no WordPress
     */
    private function create_post( $title, $content, $item, $options ) {
        $post_data = array(
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => $content,
            'post_status'  => $options['post_status'],
            'post_type'    => 'post',
            'post_author'  => get_option( 'manus_wp_reposter_post_author', 1 ),
        );

        // Tenta usar a data do artigo original
        $item_date = $item->get_date( 'Y-m-d H:i:s' );
        if ( $item_date ) {
            $post_data['post_date'] = $item_date;
            $post_data['post_date_gmt'] = get_gmt_from_date( $item_date );
        }

        // Adiciona categoria padrão se configurada
        $default_category = get_option( 'manus_wp_reposter_default_category', 0 );
        if ( $default_category ) {
            $post_data['post_category'] = array( $default_category );
        }

        // Insere post
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            throw new Exception( $post_id->get_error_message() );
        }

        return $post_id;
    }

    /**
     * Define imagem destacada
     */
    private function set_featured_image( $post_id, $image_url, $title ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $this->log( 'INFO', 'Baixando imagem destacada: ' . $image_url );

        // Baixa imagem
        $tmp_file = download_url( $image_url, 30 );

        if ( is_wp_error( $tmp_file ) ) {
            $this->log( 'WARNING', 'Erro ao baixar imagem: ' . $tmp_file->get_error_message() );
            return false;
        }

        // Prepara array de arquivo
        $file_array = array(
            'name' => basename( parse_url( $image_url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp_file,
        );

        // Faz upload
        $attachment_id = media_handle_sideload( $file_array, $post_id, $title );

        // Limpa arquivo temporário
        @unlink( $tmp_file );

        if ( is_wp_error( $attachment_id ) ) {
            $this->log( 'WARNING', 'Erro ao criar attachment: ' . $attachment_id->get_error_message() );
            return false;
        }

        // Define como imagem destacada
        set_post_thumbnail( $post_id, $attachment_id );

        $this->log( 'INFO', 'Imagem destacada definida', array(
            'post_id' => $post_id,
            'attachment_id' => $attachment_id,
        ) );

        return $attachment_id;
    }

    /**
     * Salva meta informações do post
     */
    private function save_post_meta( $post_id, $original_url, $feed ) {
        // URL original
        update_post_meta( $post_id, '_manus_original_url', esc_url_raw( $original_url ) );

        // Data de importação
        update_post_meta( $post_id, '_manus_import_date', current_time( 'mysql' ) );

        // Informações da fonte
        update_post_meta( $post_id, '_manus_source_title', $feed->get_title() );
        update_post_meta( $post_id, '_manus_source_url', $feed->get_link() );

        // Marca como importado pelo plugin
        update_post_meta( $post_id, '_manus_imported', true );

        $this->log( 'INFO', 'Meta dados salvos para post ID: ' . $post_id );
    }

    /**
     * Registra log de forma segura (sem output prematuro)
     */
    private function log( $level, $message, $context = null ) {
        // Salva em opção transiente para logs durante a execução
        $current_logs = get_transient( 'manus_import_logs' );
        if ( ! is_array( $current_logs ) ) {
            $current_logs = array();
        }

        $log_entry = array(
            'time' => current_time( 'mysql' ),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        );

        $current_logs[] = $log_entry;

        // Mantém apenas os últimos 50 logs
        if ( count( $current_logs ) > 50 ) {
            $current_logs = array_slice( $current_logs, -50 );
        }

        set_transient( 'manus_import_logs', $current_logs, HOUR_IN_SECONDS );

        // Apenas loga no arquivo de debug se estiver ativado
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_message = "[Manus $level] $message";
            if ( $context ) {
                $log_message .= " - Context: " . json_encode( $context );
            }
            // Usa error_log apenas se WP_DEBUG_LOG estiver ativado
            error_log( $log_message );
        }
    }
}