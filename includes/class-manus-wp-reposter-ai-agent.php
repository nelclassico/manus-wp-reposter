<?php
/**
 * Agente de IA para reescrita e adaptação de conteúdo
 * Suporta: Anthropic Claude, OpenAI GPT, Google Gemini, Groq (Free Tier)
 * VERSÃO OTIMIZADA: Preserva imagens no local correto e evita resumos excessivos
 */
class Manus_WP_Reposter_AI_Agent {

    // Provedores suportados
    const PROVIDER_CLAUDE = 'claude';
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_GEMINI = 'gemini';
    const PROVIDER_GROQ   = 'groq';
    const PROVIDER_NONE   = 'none';

    private $provider;
    private $api_key;
    private $model;
    private $tone;
    private $target_language;
    private $min_words;
    private $max_words;
    private $rewrite_enabled;
    private $preserve_multimedia;
    private $logger;

    public function __construct() {
        $this->provider        = get_option( 'manus_ai_provider', self::PROVIDER_NONE );
        $this->api_key         = get_option( 'manus_ai_api_key', '' );
        $this->model           = get_option( 'manus_ai_model', '' );
        $this->tone            = get_option( 'manus_ai_tone', 'jornalistico' );
        $this->target_language = get_option( 'manus_ai_target_language', 'pt-BR' );
        $this->min_words       = (int) get_option( 'manus_ai_min_words', 200 );
        $this->max_words       = (int) get_option( 'manus_ai_max_words', 800 );
        $this->rewrite_enabled = (bool) get_option( 'manus_ai_rewrite_enabled', false );
        $this->preserve_multimedia = (bool) get_option( 'manus_ai_preserve_multimedia', true );

        if ( class_exists( 'Manus_WP_Reposter_Logger' ) ) {
            $this->logger = Manus_WP_Reposter_Logger::instance();
        }
    }

    public function is_enabled() {
        return $this->rewrite_enabled
            && $this->provider !== self::PROVIDER_NONE
            && ! empty( $this->api_key );
    }

    /**
     * Testa a conexão com o provedor configurado
     */
    public function test_connection() {
        if ( empty( $this->api_key ) || $this->provider === self::PROVIDER_NONE ) {
            return array( 'success' => false, 'message' => 'Provedor ou chave de API não informados.' );
        }

        try {
            $prompt = "Responda apenas com a palavra 'OK' em formato JSON: {\"status\": \"OK\"}";
            $raw    = $this->call_api( $prompt );
            $parsed = $this->parse_response( $raw );

            if ( isset( $parsed['status'] ) && $parsed['status'] === 'OK' ) {
                return array( 'success' => true, 'message' => 'Conexão estabelecida com sucesso!' );
            }

            return array( 'success' => false, 'message' => 'Resposta inesperada da API.' );
        } catch ( Exception $e ) {
            return array( 'success' => false, 'message' => $e->getMessage() );
        }
    }

    /**
     * NOVO MÉTODO: Prepara o conteúdo HTML substituindo elementos multimídia por placeholders
     * Isso permite que a IA saiba EXATAMENTE onde as imagens e vídeos estavam.
     */
    private function prepare_content_with_placeholders( $html_content ) {
        $placeholders = array();
        
        // Função auxiliar para criar placeholders
        $callback = function( $matches ) use ( &$placeholders ) {
            $id = '[[MEDIA_ITEM_' . count( $placeholders ) . ']]';
            $placeholders[$id] = $matches[0];
            return " " . $id . " ";
        };

        // Substituir imagens, iframes, vídeos e embeds por placeholders
        $content = preg_replace_callback('/<img[^>]+>/i', $callback, $html_content);
        $content = preg_replace_callback('/<iframe[^>]+><\/iframe>/i', $callback, $content);
        $content = preg_replace_callback('/<video[^>]*>.*?<\/video>/is', $callback, $content);
        $content = preg_replace_callback('/<blockquote[^>]+class="(instagram-media|twitter-tweet|tiktok-embed)"[^>]*>.*?<\/blockquote>/is', $callback, $content);
        
        // Agora limpamos as outras tags HTML, mas mantemos os placeholders
        $content = wp_strip_all_tags( $content );
        
        return array(
            'content' => $content,
            'placeholders' => $placeholders
        );
    }

    /**
     * NOVO MÉTODO: Reinsere os elementos originais nos locais dos placeholders
     */
    private function restore_placeholders( $text, $placeholders ) {
        foreach ( $placeholders as $placeholder => $original_html ) {
            // Garante que o elemento multimídia tenha espaço ao redor
            $text = str_replace( $placeholder, "\n\n" . $original_html . "\n\n", $text );
        }
        return $text;
    }

    /**
     * MANTIDO PARA COMPATIBILIDADE: Extrai e preserva elementos multimídia do conteúdo HTML
     * (Agora usado como fallback ou para estatísticas)
     */
    private function extract_multimedia_elements( $html_content ) {
        $multimedia = array(
            'images' => array(),
            'videos' => array(),
            'embeds' => array(),
            'iframes' => array()
        );
        
        preg_match_all('/<img[^>]+>/i', $html_content, $images);
        $multimedia['images'] = $images[0];
        
        preg_match_all('/<iframe[^>]+><\/iframe>/i', $html_content, $iframes);
        $multimedia['iframes'] = $iframes[0];
        
        preg_match_all('/<video[^>]*>.*?<\/video>/is', $html_content, $videos);
        $multimedia['videos'] = $videos[0];
        
        preg_match_all('/<blockquote[^>]+class="instagram-media"[^>]*>.*?<\/blockquote>/is', $html_content, $instagram);
        preg_match_all('/<blockquote[^>]+class="twitter-tweet"[^>]*>.*?<\/blockquote>/is', $html_content, $twitter);
        preg_match_all('/<blockquote[^>]+class="tiktok-embed"[^>]*>.*?<\/blockquote>/is', $html_content, $tiktok);
        
        $multimedia['embeds'] = array_merge($instagram[0], $twitter[0], $tiktok[0]);
        
        return $multimedia;
    }
    
    /**
     * REESCRITO: Reinsere elementos multimídia no conteúdo reescrito
     * Agora prioriza a restauração via placeholders para precisão cirúrgica.
     */
    private function reinsert_multimedia_elements( $rewritten_content, $multimedia_elements, $original_content ) {
        // Se o conteúdo reescrito já contém os placeholders [[MEDIA_ITEM_X]], eles serão tratados no método restore_placeholders
        // Este método permanece aqui para garantir que nenhuma lógica de fallback seja quebrada.
        return $rewritten_content;
    }

    public function rewrite( $title, $content, $options = array() ) {
        $result = array(
            'title'            => $title,
            'content'          => $content,
            'meta_description' => '',
            'success'          => false,
            'error'            => '',
        );

        if ( ! $this->is_enabled() ) {
            $result['error'] = 'Agente de IA não configurado ou desativado.';
            return $result;
        }

        // Nova lógica de preparação: Preservar posição da multimídia via placeholders
        $prepared = array('content' => wp_strip_all_tags($content), 'placeholders' => array());
        if ( $this->preserve_multimedia ) {
            $prepared = $this->prepare_content_with_placeholders( $content );
        }
        
        $plain_content = $prepared['content'];
        $word_count = str_word_count( wp_strip_all_tags($plain_content) );

        // Remover conteúdo muito curto
        if ( $word_count < 30 && empty( $prepared['placeholders'] ) ) {
            $result['error'] = 'Conteúdo muito curto para reescrita.';
            return $result;
        }

        $translate     = ! empty( $options['translate'] );
        $source_lang   = ! empty( $options['source_lang'] ) ? $options['source_lang'] : 'auto';
        
        // Build prompt agora recebe o número de placeholders para instruir a IA
        $prompt = $this->build_prompt( $title, $plain_content, $translate, $source_lang, count($prepared['placeholders']) );

        $this->log( 'INFO', sprintf(
            'Agente IA: iniciando reescrita via %s (traduzir: %s, palavras originais: %d)',
            $this->provider,
            $translate ? 'sim' : 'não',
            $word_count
        ) );

        try {
            $raw = $this->call_api( $prompt );

            if ( empty( $raw ) ) {
                throw new Exception( 'Resposta vazia da API.' );
            }

            $parsed = $this->parse_response( $raw );

            if ( ! empty( $parsed['title'] ) ) {
                $result['title'] = $parsed['title'];
            }
            if ( ! empty( $parsed['content'] ) ) {
                $rewritten_text = $parsed['content'];
                
                // Restaurar imagens e vídeos nos locais EXATOS onde estavam
                if ( ! empty( $prepared['placeholders'] ) ) {
                    $rewritten_text = $this->restore_placeholders( $rewritten_text, $prepared['placeholders'] );
                }
                
                $result['content'] = $rewritten_text;
            }
            if ( ! empty( $parsed['meta_description'] ) ) {
                $result['meta_description'] = $parsed['meta_description'];
            }

            $result['success'] = true;
            $this->log( 'INFO', 'Agente IA: reescrita concluída com sucesso.' );

        } catch ( Exception $e ) {
            $result['error'] = $e->getMessage();
            $this->log( 'ERROR', 'Agente IA: erro na reescrita — ' . $e->getMessage() );
        }

        return $result;
    }

    private function build_prompt( $title, $content, $translate, $source_lang, $media_count = 0 ) {
        $word_count = str_word_count( wp_strip_all_tags($content) );
        
        $tone_map = array(
            'jornalistico'  => 'jornalístico: imparcial, informativo e direto',
            'descontraido'  => 'descontraído: acessível, próximo do leitor, sem perder a precisão dos fatos',
            'institucional' => 'institucional: formal, respeitoso, adequado para órgãos públicos e empresas',
        );
        $tone_desc = isset( $tone_map[ $this->tone ] ) ? $tone_map[ $this->tone ] : $tone_map['jornalistico'];

        $lang_instruction = '';
        if ( $translate ) {
            $lang_map = array(
                'pt-BR' => 'português brasileiro',
                'pt-PT' => 'português europeu',
                'es'    => 'espanhol',
                'en'    => 'inglês',
                'fr'    => 'francês',
            );
            $lang_name        = isset( $lang_map[ $this->target_language ] ) ? $lang_map[ $this->target_language ] : 'português brasileiro';
            $lang_instruction = "O texto original pode estar em outro idioma. Reescreva SEMPRE em {$lang_name}, sem mencionar que houve tradução.\n";
        }

        // Lógica de proteção contra resumos excessivos
        // Se o texto original tem 160 palavras, o novo deve ter pelo menos 140.
        $target_min = max( $this->min_words, round($word_count * 0.85) );
        $target_max = max( $this->max_words, round($word_count * 1.3) );
        
        $media_instruction = "";
        if ( $media_count > 0 ) {
            $media_instruction = "\n\nINSTRUÇÕES OBRIGATÓRIAS SOBRE MULTIMÍDIA:\n";
            $media_instruction .= "- O texto contém marcadores como [[MEDIA_ITEM_0]], [[MEDIA_ITEM_1]], etc.\n";
            $media_instruction .= "- Estes marcadores representam imagens e vídeos originais.\n";
            $media_instruction .= "- Você DEVE manter todos os marcadores no texto reescrito, posicionando-os nos parágrafos onde o contexto faz mais sentido.\n";
            $media_instruction .= "- NÃO remova, não altere e não traduza os marcadores [[MEDIA_ITEM_X]].\n";
        }

        $prompt = <<<PROMPT
Você é um editor sênior de um portal de notícias de alta qualidade. Sua tarefa é reescrever a notícia abaixo para torná-la única e profissional, mantendo TODA a profundidade original.

OBJETIVOS:
- Melhorar a fluidez e coesão entre os parágrafos.
- Eliminar repetições, mas preservar TODOS os fatos, nomes, datas e citações.
- Tom: {$tone_desc}
- IMPORTANTE: NÃO RESUMA. Mantenha a mesma quantidade de informação e detalhamento do original.

{$lang_instruction}
{$media_instruction}

REGRAS:
1. O texto final deve ter entre {$target_min} e {$target_max} palavras. 
2. NÃO invente informações.
3. Use tags <p> e </p> para estruturar os parágrafos.
4. Mantenha a formatação <strong> para ênfase quando necessário.
5. Responda APENAS com o JSON no formato solicitado.

FORMATO DE RESPOSTA:
{
  "title": "Título reescrito aqui",
  "meta_description": "Resumo de até 155 caracteres para SEO",
  "content": "<p>Conteúdo reescrito aqui, incluindo os marcadores [[MEDIA_ITEM_X]] se houver...</p>"
}

--- TÍTULO ORIGINAL ---
{$title}

--- CONTEÚDO ORIGINAL ---
{$content}

--- INFORMAÇÕES ADICIONAIS ---
- Palavras no original: {$word_count}
- Elementos multimídia para preservar: {$media_count}
PROMPT;

        return $prompt;
    }

    private function call_api( $prompt ) {
        switch ( $this->provider ) {
            case self::PROVIDER_CLAUDE:
                return $this->call_claude( $prompt );
            case self::PROVIDER_OPENAI:
                return $this->call_openai( $prompt );
            case self::PROVIDER_GEMINI:
                return $this->call_gemini( $prompt );
            case self::PROVIDER_GROQ:
                return $this->call_groq( $prompt );
            default:
                throw new Exception( 'Provedor de IA não reconhecido: ' . $this->provider );
        }
    }

    private function call_claude( $prompt ) {
        $model = ! empty( $this->model ) ? $this->model : 'claude-3-haiku-20240307';

        $body = wp_json_encode( array(
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => array(
                array( 'role' => 'user', 'content' => $prompt ),
            ),
        ) );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 90,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => $body,
        ) );

        $this->assert_http_ok( $response, 'Claude' );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['content'][0]['text'] ?? '';
    }

    private function call_openai( $prompt ) {
        $model = ! empty( $this->model ) ? $this->model : 'gpt-4o-mini';

        $body = wp_json_encode( array(
            'model'    => $model,
            'messages' => array(
                array( 'role' => 'system', 'content' => 'Você é um editor de textos jornalísticos experiente. Preserve a integridade do conteúdo e elementos multimídia. Responda SEMPRE em JSON válido.' ),
                array( 'role' => 'user', 'content' => $prompt ),
            ),
            'max_tokens'      => 4096,
            'response_format' => array( 'type' => 'json_object' ),
        ) );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 90,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'body' => $body,
        ) );

        $this->assert_http_ok( $response, 'OpenAI' );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function call_gemini( $prompt ) {
        $model    = ! empty( $this->model ) ? $this->model : 'gemini-1.5-flash';
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->api_key;

        $body = wp_json_encode( array(
            'contents' => array(
                array( 'parts' => array( array( 'text' => $prompt ) ) ),
            ),
            'generationConfig' => array(
                'maxOutputTokens' => 4096,
                'temperature'     => 0.7,
                'responseMimeType' => 'application/json',
            ),
        ) );

        $response = wp_remote_post( $endpoint, array(
            'timeout' => 90,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => $body,
        ) );

        $this->assert_http_ok( $response, 'Gemini' );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    private function call_groq( $prompt ) {
        $model = ! empty( $this->model ) ? $this->model : 'llama-3.3-70b-versatile';

        $body = wp_json_encode( array(
            'model'    => $model,
            'messages' => array(
                array( 'role' => 'system', 'content' => 'Você é um editor de textos jornalísticos experiente. Preserve a integridade do conteúdo. Responda SEMPRE em JSON válido.' ),
                array( 'role' => 'user', 'content' => $prompt ),
            ),
            'max_tokens'      => 4096,
            'response_format' => array( 'type' => 'json_object' ),
        ) );

        $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', array(
            'timeout' => 90,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'body' => $body,
        ) );

        $this->assert_http_ok( $response, 'Groq' );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function assert_http_ok( $response, $provider_name ) {
        if ( is_wp_error( $response ) ) {
            throw new Exception( "Erro de conexão com {$provider_name}: " . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $body = wp_remote_retrieve_body( $response );
            $this->log( 'ERROR', "API {$provider_name} retornou HTTP {$code}: {$body}" );
            throw new Exception( "A API do {$provider_name} retornou um erro (HTTP {$code}). Verifique sua chave e limites." );
        }
    }

    private function parse_response( $raw ) {
        $json = trim( $raw );
        if ( strpos( $json, '```json' ) !== false ) {
            $json = preg_replace( '/^```json\s*|\s*```$/', '', $json );
        }
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            preg_match( '/\{[^{}]*"title"[^{}]*"content"[^{}]*\}/s', $json, $matches );
            if ( ! empty( $matches[0] ) ) {
                $json = $matches[0];
            }
        }

        $data = json_decode( $json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->log( 'ERROR', 'Falha ao decodificar JSON da IA: ' . substr($json, 0, 500) );
            throw new Exception( 'A IA retornou um formato inválido. Tente novamente.' );
        }

        return $data;
    }

    private function log( $level, $message ) {
        if ( $this->logger ) {
            $this->logger->add_log( $level, $message );
        }
    }

    public static function get_models_for_provider( $provider ) {
        $models = array(
            self::PROVIDER_CLAUDE => array(
                'claude-3-haiku-20240307' => 'Claude 3 Haiku (Rápido/Barato)',
                'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet (Alta Qualidade)',
            ),
            self::PROVIDER_OPENAI => array(
                'gpt-4o-mini' => 'GPT-4o Mini (Recomendado)',
                'gpt-4o'      => 'GPT-4o (Poderoso)',
            ),
            self::PROVIDER_GEMINI => array(
                'gemini-1.5-flash' => 'Gemini 1.5 Flash (Rápido)',
                'gemini-1.5-pro'   => 'Gemini 1.5 Pro (Inteligente)',
            ),
            self::PROVIDER_GROQ => array(
                'llama-3.3-70b-versatile' => 'Llama 3.3 70B (Versátil/Grátis)',
                'llama-3.1-8b-instant'    => 'Llama 3.1 8B (Instantâneo/Grátis)',
                'mixtral-8x7b-32768'      => 'Mixtral 8x7B (Grátis)',
            ),
        );

        return isset( $models[ $provider ] ) ? $models[ $provider ] : array();
    }
}
