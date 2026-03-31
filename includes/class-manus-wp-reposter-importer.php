<?php
/**
 * Classe de importação do plugin
 */
class Manus_WP_Reposter_Importer {

    private $translator;
    private $extractor;
    private $logger;
    private $ai_agent;

    // -------------------------------------------------------------------------
    // Construtor
    // -------------------------------------------------------------------------

    public function __construct( $translator = null, $extractor = null ) {
        $this->translator = $translator;
        $this->extractor  = $extractor;

        if ( class_exists( 'Manus_WP_Reposter_Logger' ) ) {
            $this->logger = Manus_WP_Reposter_Logger::instance();
        }

        if ( class_exists( 'Manus_WP_Reposter_AI_Agent' ) ) {
            $this->ai_agent = new Manus_WP_Reposter_AI_Agent();
        }

        $this->log( 'INFO', 'Importador inicializado' );
    }

    // -------------------------------------------------------------------------
    // Importação diária (cron)
    // -------------------------------------------------------------------------

    public function run_daily_import() {
        $this->log( 'INFO', 'Iniciando importação diária automática' );

        // Garante que as dependências estão disponíveis no contexto do cron
        if ( ! $this->translator || ! $this->extractor ) {
            if ( class_exists( 'Manus_WP_Reposter' ) ) {
                $plugin = Manus_WP_Reposter::instance();
                if ( ! $this->translator ) $this->translator = $plugin->get_translator();
                if ( ! $this->extractor )  $this->extractor  = $plugin->get_extractor();
            }
        }

        $total_imported = 0;
        $total_skipped  = 0;
        $total_failed   = 0;

        // ---------------------------------------------------------------
        // 1) Processar feeds agendados individualmente (hora/link/categoria)
        // ---------------------------------------------------------------
        $scheduled_feeds = $this->get_scheduled_feeds_due_now();

        if ( ! empty( $scheduled_feeds ) ) {
            foreach ( $scheduled_feeds as $feed_config ) {
                $feed_url = ! empty( $feed_config['feed_url'] ) ? $feed_config['feed_url'] : '';
                if ( empty( $feed_url ) ) {
                    continue;
                }

                $quantity         = isset( $feed_config['quantity'] ) ? (int) $feed_config['quantity'] : 1;
                $translate_option = ! empty( $feed_config['translate'] );
                $category_id      = isset( $feed_config['category'] ) ? (int) $feed_config['category'] : 0;
                $post_status      = ! empty( $feed_config['post_status'] ) ? $feed_config['post_status'] : 'publish';

                $import_options = array(
                    'mode'            => 'normal',
                    'translate'       => $translate_option,
                    'download_images' => true,
                    'set_featured'    => true,
                    'post_status'     => $post_status,
                    'category_id'     => $category_id,
                );

                $this->log( 'INFO', sprintf(
                    'Processando feed agendado: %s (qtd: %d, traduzir: %s, categoria: %d)',
                    $feed_url,
                    $quantity,
                    $translate_option ? 'sim' : 'não',
                    $category_id
                ) );

                try {
                    $result = $this->import_from_feed( $feed_url, $quantity, $import_options );
                    $total_imported += $result['imported'];
                    $total_skipped  += $result['skipped'];
                    $total_failed   += $result['failed'];
                } catch ( Exception $e ) {
                    $this->log( 'ERROR', 'Erro no feed agendado ' . $feed_url . ': ' . $e->getMessage() );
                    $total_failed++;
                }

                // Registra que este feed foi processado agora
                $this->mark_scheduled_feed_as_run( $feed_config );
            }
        }

        // ---------------------------------------------------------------
        // 2) Fallback: feed principal (quando não há feeds agendados configurados)
        // ---------------------------------------------------------------
        if ( ! $this->has_any_scheduled_feed() ) {
            $feed_url = get_option( 'manus_wp_reposter_feed_url' );
            if ( empty( $feed_url ) ) {
                $this->log( 'ERROR', 'URL do feed não configurada e nenhum feed agendado encontrado' );
                return;
            }

            $daily_quantity   = (int) get_option( 'manus_wp_reposter_daily_quantity', 1 );
            $translate_option = get_option( 'manus_wp_reposter_auto_translate', false );
            $default_category = (int) get_option( 'manus_wp_reposter_default_category', 0 );

            $import_options = array(
                'mode'            => 'normal',
                'translate'       => (bool) $translate_option,
                'download_images' => true,
                'set_featured'    => true,
                'post_status'     => 'publish',
                'category_id'     => $default_category,
            );

            try {
                $result = $this->import_from_feed( $feed_url, $daily_quantity, $import_options );
                $total_imported += $result['imported'];
                $total_skipped  += $result['skipped'];
                $total_failed   += $result['failed'];
            } catch ( Exception $e ) {
                $this->log( 'ERROR', 'Erro crítico na importação diária (feed principal): ' . $e->getMessage() );
            }
        }

        $this->log( 'INFO', sprintf(
            'Importação diária concluída: %d importados, %d pulados, %d falhas',
            $total_imported,
            $total_skipped,
            $total_failed
        ) );
    }

    /**
     * Retorna todos os feeds agendados que devem ser executados agora.
     */
    private function get_scheduled_feeds_due_now() {
        $feeds_json = get_option( 'manus_wp_reposter_scheduled_feeds', '[]' );
        $feeds      = json_decode( $feeds_json, true );

        if ( ! is_array( $feeds ) || empty( $feeds ) ) {
            return array();
        }

        $tz  = wp_timezone();
        $now = new DateTimeImmutable( 'now', $tz );
        $due = array();

        foreach ( $feeds as $feed ) {
            if ( empty( $feed['enabled'] ) ) {
                continue;
            }

            $scheduled_time = ! empty( $feed['time'] ) ? $feed['time'] : '09:00';
            if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $scheduled_time ) ) {
                $scheduled_time = '09:00';
            }

            list( $hour, $minute ) = explode( ':', $scheduled_time );
            $run_at = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $now->format( 'Y-m-d' ) . " {$hour}:{$minute}:00",
                $tz
            );

            // Verifica se o horário já passou hoje
            if ( $now < $run_at ) {
                continue;
            }

            // Verifica se já foi executado hoje
            $last_run = ! empty( $feed['last_run'] ) ? $feed['last_run'] : '';
            if ( ! empty( $last_run ) ) {
                try {
                    $last_run_date = new DateTimeImmutable( $last_run, $tz );
                    if ( $last_run_date->format( 'Y-m-d' ) === $now->format( 'Y-m-d' ) ) {
                        continue; // Já executou hoje
                    }
                } catch ( Exception $e ) {
                    // Data inválida, ignora e reprocessa
                }
            }

            $due[] = $feed;
        }

        return $due;
    }

    /**
     * Verifica se há ao menos um feed agendado configurado
     */
    private function has_any_scheduled_feed() {
        $feeds_json = get_option( 'manus_wp_reposter_scheduled_feeds', '[]' );
        $feeds      = json_decode( $feeds_json, true );
        return is_array( $feeds ) && ! empty( $feeds );
    }

    /**
     * Registra que um feed agendado foi executado (atualiza last_run)
     */
    private function mark_scheduled_feed_as_run( $feed_config ) {
        $feeds_json = get_option( 'manus_wp_reposter_scheduled_feeds', '[]' );
        $feeds      = json_decode( $feeds_json, true );

        if ( ! is_array( $feeds ) ) {
            return;
        }

        $tz  = wp_timezone();
        $now = new DateTimeImmutable( 'now', $tz );

        foreach ( $feeds as &$feed ) {
            if ( isset( $feed['id'] ) && isset( $feed_config['id'] ) && $feed['id'] === $feed_config['id'] ) {
                $feed['last_run'] = $now->format( 'Y-m-d H:i:s' );
                break;
            }
        }
        unset( $feed );

        update_option( 'manus_wp_reposter_scheduled_feeds', wp_json_encode( $feeds ) );
    }

    // -------------------------------------------------------------------------
    // Importação manual
    // -------------------------------------------------------------------------

    public function run_manual_import( $quantity = 5, $options = array() ) {
        $default_options = array(
            'mode'            => 'normal',
            'translate'       => false,
            'download_images' => true,
            'set_featured'    => true,
            'post_status'     => 'draft',
            'feed_url'        => '',
        );
        $options  = wp_parse_args( $options, $default_options );
        $feed_url = ! empty( $options['feed_url'] )
            ? $options['feed_url']
            : get_option( 'manus_wp_reposter_feed_url' );

        if ( empty( $feed_url ) ) {
            return array(
                'imported' => 0,
                'skipped'  => 0,
                'failed'   => 0,
                'error'    => 'URL do feed não configurada',
            );
        }

        return $this->import_from_feed( $feed_url, $quantity, $options );
    }

    // -------------------------------------------------------------------------
    // Core de importação
    // -------------------------------------------------------------------------

    private function import_from_feed( $feed_url, $quantity, $options ) {
        $result = array( 'imported' => 0, 'skipped' => 0, 'failed' => 0, 'items' => array() );

        require_once ABSPATH . WPINC . '/feed.php';
        $feed = fetch_feed( $feed_url );

        if ( is_wp_error( $feed ) ) {
            $result['error'] = $feed->get_error_message();
            $this->log( 'ERROR', 'Erro ao buscar feed: ' . $result['error'] );
            return $result;
        }

        $items = $feed->get_items( 0, $feed->get_item_quantity( $quantity ) );
        $items = array_reverse( $items ); // Mais antigo primeiro

        foreach ( $items as $item ) {
            $item_result = $this->process_feed_item( $item, $feed, $options );

            if ( $item_result['status'] === 'imported' ) {
                $result['imported']++;
            } elseif ( $item_result['status'] === 'skipped' ) {
                $result['skipped']++;
            } else {
                $result['failed']++;
                $this->log( 'WARNING', 'Falha ao importar: ' . ( $item_result['title'] ?? '' ) . ' — ' . ( $item_result['error'] ?? '' ) );
            }
        }

        return $result;
    }

    private function process_feed_item( $item, $feed, $options ) {
        $item_url   = $item->get_permalink();
        $item_title = $item->get_title();

        // Verifica duplicata
        if ( $options['mode'] === 'normal' && $this->is_already_imported( $item_url ) ) {
            return array( 'status' => 'skipped', 'title' => $item_title );
        }

        // Verifica fonte confiável
        if ( class_exists( 'Manus_WP_Reposter_Automation_Settings' ) ) {
            if ( ! Manus_WP_Reposter_Automation_Settings::is_from_trusted_source( $item_url ) ) {
                return array( 'status' => 'skipped', 'title' => $item_title, 'reason' => 'Fonte não confiável' );
            }
        }

        try {
            if ( ! $this->extractor ) {
                throw new Exception( 'Extrator não disponível' );
            }

            $extraction     = $this->extractor->extract_content( $item_url );
            $content        = '';
            $title          = $item_title;
            $featured_image = '';

            if ( $extraction['success'] && strlen( strip_tags( $extraction['content'] ) ) > 500 ) {
                $content        = $extraction['content'];
                $title          = ! empty( $extraction['title'] ) ? $extraction['title'] : $item_title;
                $featured_image = $extraction['featured_image'];
            } else {
                $content = $item->get_content();
                if ( empty( $content ) ) {
                    $content = $item->get_description();
                }
                if ( strlen( strip_tags( $content ) ) < 200 && ! empty( $extraction['content'] ) ) {
                    $content = $extraction['content'];
                }
            }

            if ( empty( $content ) ) {
                throw new Exception( 'Conteúdo não encontrado' );
            }

            // Reescrita / tradução via agente de IA (tem prioridade sobre o tradutor simples)
            $meta_description = '';
            if ( $this->ai_agent && $this->ai_agent->is_enabled() ) {
                $ai_result = $this->ai_agent->rewrite( $title, $content, array(
                    'translate'   => ! empty( $options['translate'] ),
                    'source_lang' => 'auto',
                ) );
                if ( $ai_result['success'] ) {
                    $title            = $ai_result['title'];
                    $content          = $ai_result['content'];
                    $meta_description = $ai_result['meta_description'];
                } else {
                    // Fallback: usa tradutor simples se IA falhar
                    $this->log( 'WARNING', 'Agente IA falhou, usando tradutor simples: ' . $ai_result['error'] );
                    if ( ! empty( $options['translate'] ) && $this->translator ) {
                        $content = $this->translator->translate_html( $content, 'en', 'pt' );
                        $title   = $this->translator->translate_text( $title, 'en', 'pt' );
                    }
                }
            } elseif ( ! empty( $options['translate'] ) && $this->translator ) {
                // Sem agente de IA: usa tradutor simples
                $content = $this->translator->translate_html( $content, 'en', 'pt' );
                $title   = $this->translator->translate_text( $title, 'en', 'pt' );
            }

            $post_id = $this->create_post( $title, $content, $options );

            // Salva meta descrição gerada pela IA (compatível com Yoast, RankMath e SEOPress)
            if ( ! empty( $meta_description ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
                update_post_meta( $post_id, 'rank_math_description', $meta_description );
                update_post_meta( $post_id, '_seopress_titles_desc', $meta_description );
                update_post_meta( $post_id, '_manus_meta_description', $meta_description );
            }

            if ( ! empty( $featured_image ) && $options['set_featured'] ) {
                $this->set_featured_image( $post_id, $featured_image, $title );
            }

            $this->save_post_meta( $post_id, $item_url, $feed );

            return array( 'status' => 'imported', 'post_id' => $post_id, 'title' => $title );

        } catch ( Exception $e ) {
            return array( 'status' => 'failed', 'title' => $item_title, 'error' => $e->getMessage() );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function is_already_imported( $url ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_manus_original_url' AND meta_value = %s LIMIT 1",
            $url
        ) );
    }

    private function create_post( $title, $content, $options ) {
        global $wp_rewrite;

        // CORREÇÃO: garante que wp_rewrite está inicializado no contexto do cron,
        // evitando o Warning "Attempt to read property feeds on null" no post.php
        if ( is_null( $wp_rewrite ) ) {
            $wp_rewrite = new WP_Rewrite();
        }

        $post_author      = (int) get_option( 'manus_wp_reposter_post_author', 1 );
        // Prioridade: category_id passado nas options > configuração global
        $default_category = isset( $options['category_id'] ) && $options['category_id'] > 0
            ? (int) $options['category_id']
            : (int) get_option( 'manus_wp_reposter_default_category', 0 );

        $post_data = array(
            'post_title'    => wp_strip_all_tags( $title ),
            'post_content'  => $content,
            'post_status'   => $options['post_status'],
            'post_author'   => $post_author,
            'post_type'     => 'post',
            // CORREÇÃO: usa a data atual para o post aparecer no topo do blog,
            // não a data original da notícia (que poderia ser dias atrás)
            'post_date'     => current_time( 'mysql' ),
            'post_date_gmt' => current_time( 'mysql', 1 ),
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            throw new Exception( $post_id->get_error_message() );
        }

        if ( $default_category ) {
            wp_set_post_categories( $post_id, array( $default_category ) );
        }

        return $post_id;
    }

    private function set_featured_image( $post_id, $image_url, $title ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $image_url );
        if ( is_wp_error( $tmp ) ) {
            return false;
        }

        $file_array = array(
            'name'     => sanitize_file_name( basename( $image_url ) ),
            'tmp_name' => $tmp,
        );

        $id = media_handle_sideload( $file_array, $post_id, $title );
        @unlink( $tmp );

        if ( ! is_wp_error( $id ) ) {
            set_post_thumbnail( $post_id, $id );
        }

        return $id;
    }

    private function save_post_meta( $post_id, $url, $feed ) {
        update_post_meta( $post_id, '_manus_original_url', $url );
        update_post_meta( $post_id, '_manus_imported', true );
        update_post_meta( $post_id, '_manus_source_title', $feed->get_title() );
    }

    private function log( $level, $message, $context = null ) {
        if ( $this->logger ) {
            $this->logger->add_log( $level, $message, $context );
        } else {
            error_log( "[Manus {$level}] {$message}" );
        }
    }
}