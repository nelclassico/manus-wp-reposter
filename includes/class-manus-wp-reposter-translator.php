<?php
/**
 * Classe de tradução do plugin - VERSÃO CORRIGIDA
 */
class Manus_WP_Reposter_Translator {
    
    private $api_key;
    private $api_service;
    private $cache_enabled;
    
    public function __construct() {
        $this->api_key = get_option( 'manus_deepl_api_key', '' );
        $this->api_service = get_option( 'manus_translation_service', 'deepl' );
        $this->cache_enabled = get_option( 'manus_translation_cache', true );
    }
    
    /**
     * Detecta idioma do texto
     */
    public function detect_language( $text ) {
        // Limita texto para análise
        $sample = substr( strip_tags( $text ), 0, 1000 );
        
        // Remove caracteres especiais e números
        $sample = preg_replace( '/[0-9\p{P}]/u', ' ', $sample );
        $sample = mb_strtolower( $sample, 'UTF-8' );
        
        // Palavras comuns em inglês
        $english_words = array(
            'the', 'and', 'to', 'of', 'a', 'in', 'that', 'is', 'it', 'for',
            'you', 'with', 'on', 'this', 'be', 'are', 'as', 'at', 'from',
            'have', 'or', 'by', 'one', 'had', 'not', 'but', 'what', 'all',
            'were', 'when', 'we', 'there', 'can', 'an', 'your', 'which',
            'their', 'will', 'would', 'if', 'about', 'get', 'just', 'know',
        );
        
        // Palavras comuns em português
        $portuguese_words = array(
            'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'do',
            'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas', 'por', 'para',
            'com', 'sem', 'que', 'se', 'não', 'como', 'mais', 'mas', 'foi',
            'está', 'ser', 'tem', 'era', 'são', 'esta', 'este', 'isso', 'isso',
            'isso', 'ele', 'ela', 'eles', 'elas', 'meu', 'minha', 'teu', 'tua',
        );
        
        // Conta ocorrências
        $words = preg_split( '/\s+/', $sample );
        $english_count = 0;
        $portuguese_count = 0;
        
        foreach ( $words as $word ) {
            $word = trim( $word );
            if ( strlen( $word ) < 3 ) continue;
            
            if ( in_array( $word, $english_words ) ) {
                $english_count++;
            }
            if ( in_array( $word, $portuguese_words ) ) {
                $portuguese_count++;
            }
        }
        
        // Determina idioma
        if ( $english_count > $portuguese_count && $english_count > 3 ) {
            return 'en';
        } elseif ( $portuguese_count > $english_count && $portuguese_count > 3 ) {
            return 'pt';
        }
        
        // Fallback: inglês
        return 'en';
    }
    
    /**
     * Traduz texto simples
     */
    public function translate_text( $text, $source_lang = 'auto', $target_lang = 'pt' ) {
        if ( empty( $text ) ) {
            return $text;
        }
        
        // Verifica cache primeiro
        if ( $this->cache_enabled ) {
            $cached = $this->get_cached_translation( $text, $source_lang, $target_lang );
            if ( $cached !== false ) {
                return $cached;
            }
        }
        
        // Se o texto for muito grande, divide em partes
        if ( strlen( $text ) > 30000 ) {
            $translated = $this->translate_large_text( $text, $source_lang, $target_lang );
        } else {
            // Escolhe serviço de tradução
            if ( $this->api_service === 'deepl' && ! empty( $this->api_key ) ) {
                $translated = $this->translate_with_deepl( $text, $source_lang, $target_lang );
            } else {
                // Fallback para Google Translate
                $translated = $this->translate_with_google( $text, $source_lang, $target_lang );
            }
        }
        
        if ( $translated && $translated !== $text && $this->cache_enabled ) {
            $this->cache_translation( $text, $translated, $source_lang, $target_lang );
        }
        
        return $translated ?: $text;
    }
    
    /**
     * Traduz HTML mantendo tags
     */
    public function translate_html( $html, $source_lang = 'auto', $target_lang = 'pt' ) {
        if ( empty( $html ) ) {
            return $html;
        }
        
        // Verifica cache
        if ( $this->cache_enabled ) {
            $cached = $this->get_cached_translation( $html, $source_lang, $target_lang, 'html' );
            if ( $cached !== false ) {
                return $cached;
            }
        }
        
        // Pré-processa o HTML para proteger elementos importantes
        $processed_html = $this->preprocess_html( $html );
        
        // Divide o HTML em partes gerenciáveis se for muito grande
        if ( strlen( $processed_html ) > 20000 ) {
            $translated = $this->translate_large_html( $processed_html, $source_lang, $target_lang );
        } else {
            // Usa DeepL para HTML (preserva tags)
            if ( $this->api_service === 'deepl' && ! empty( $this->api_key ) ) {
                $translated = $this->translate_html_with_deepl( $processed_html, $source_lang, $target_lang );
            } else {
                // Para outros serviços, usa método seguro
                $translated = $this->translate_html_fallback( $processed_html, $source_lang, $target_lang );
            }
        }
        
        // Pós-processa o HTML traduzido
        if ( $translated && $translated !== $processed_html ) {
            $translated = $this->postprocess_html( $translated, $html );
            
            if ( $this->cache_enabled ) {
                $this->cache_translation( $html, $translated, $source_lang, $target_lang, 'html' );
            }
            
            return $translated;
        }
        
        return $html;
    }
    
    /**
     * Pré-processa HTML para tradução
     */
    private function preprocess_html( $html ) {
        // Protege URLs
        $html = preg_replace_callback( '/(https?:\/\/[^\s<>"\']+)/i', function( $matches ) {
            return '###URL_' . md5( $matches[1] ) . '###';
        }, $html );
        
        // Protege emails
        $html = preg_replace_callback( '/([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})/i', function( $matches ) {
            return '###EMAIL_' . md5( $matches[1] ) . '###';
        }, $html );
        
        // Protege código
        $html = preg_replace_callback( '/<code[^>]*>.*?<\/code>/is', function( $matches ) {
            return '###CODE_' . md5( $matches[0] ) . '###';
        }, $html );
        
        // Protege pré-formatado
        $html = preg_replace_callback( '/<pre[^>]*>.*?<\/pre>/is', function( $matches ) {
            return '###PRE_' . md5( $matches[0] ) . '###';
        }, $html );
        
        return $html;
    }
    
    /**
     * Pós-processa HTML após tradução
     */
    private function postprocess_html( $translated_html, $original_html ) {
        // Restaura URLs
        preg_match_all( '/###URL_([a-f0-9]{32})###/', $translated_html, $url_matches );
        if ( ! empty( $url_matches[1] ) ) {
            preg_match_all( '/(https?:\/\/[^\s<>"\']+)/i', $original_html, $original_urls );
            foreach ( $url_matches[1] as $index => $hash ) {
                if ( isset( $original_urls[0][ $index ] ) ) {
                    $translated_html = str_replace( 
                        '###URL_' . $hash . '###', 
                        $original_urls[0][ $index ], 
                        $translated_html 
                    );
                }
            }
        }
        
        // Restaura emails
        preg_match_all( '/###EMAIL_([a-f0-9]{32})###/', $translated_html, $email_matches );
        if ( ! empty( $email_matches[1] ) ) {
            preg_match_all( '/([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})/i', $original_html, $original_emails );
            foreach ( $email_matches[1] as $index => $hash ) {
                if ( isset( $original_emails[0][ $index ] ) ) {
                    $translated_html = str_replace( 
                        '###EMAIL_' . $hash . '###', 
                        $original_emails[0][ $index ], 
                        $translated_html 
                    );
                }
            }
        }
        
        // Restaura código
        preg_match_all( '/###CODE_([a-f0-9]{32})###/', $translated_html, $code_matches );
        if ( ! empty( $code_matches[1] ) ) {
            preg_match_all( '/<code[^>]*>.*?<\/code>/is', $original_html, $original_codes );
            foreach ( $code_matches[1] as $index => $hash ) {
                if ( isset( $original_codes[0][ $index ] ) ) {
                    $translated_html = str_replace( 
                        '###CODE_' . $hash . '###', 
                        $original_codes[0][ $index ], 
                        $translated_html 
                    );
                }
            }
        }
        
        // Restaura pré-formatado
        preg_match_all( '/###PRE_([a-f0-9]{32})###/', $translated_html, $pre_matches );
        if ( ! empty( $pre_matches[1] ) ) {
            preg_match_all( '/<pre[^>]*>.*?<\/pre>/is', $original_html, $original_pres );
            foreach ( $pre_matches[1] as $index => $hash ) {
                if ( isset( $original_pres[0][ $index ] ) ) {
                    $translated_html = str_replace( 
                        '###PRE_' . $hash . '###', 
                        $original_pres[0][ $index ], 
                        $translated_html 
                    );
                }
            }
        }
        
        return $translated_html;
    }
    
    /**
     * Traduz texto grande dividindo em partes
     */
    private function translate_large_text( $text, $source_lang, $target_lang ) {
        // Divide o texto em parágrafos
        $paragraphs = preg_split( '/\n\s*\n/', $text );
        
        if ( count( $paragraphs ) <= 1 ) {
            // Se não tem parágrafos, divide por sentenças
            $paragraphs = preg_split( '/(?<=[.!?])\s+/', $text );
        }
        
        $translated_parts = array();
        $current_chunk = '';
        $chunks = array();
        
        // Agrupa partes em chunks de até 10000 caracteres
        foreach ( $paragraphs as $paragraph ) {
            $paragraph = trim( $paragraph );
            if ( empty( $paragraph ) ) {
                continue;
            }
            
            if ( strlen( $current_chunk ) + strlen( $paragraph ) > 10000 ) {
                if ( ! empty( $current_chunk ) ) {
                    $chunks[] = $current_chunk;
                    $current_chunk = $paragraph;
                }
            } else {
                $current_chunk .= ( empty( $current_chunk ) ? '' : "\n\n" ) . $paragraph;
            }
        }
        
        if ( ! empty( $current_chunk ) ) {
            $chunks[] = $current_chunk;
        }
        
        // Traduz cada chunk
        foreach ( $chunks as $chunk ) {
            $translated_chunk = $this->translate_text( $chunk, $source_lang, $target_lang );
            $translated_parts[] = $translated_chunk;
        }
        
        // Combina as partes traduzidas
        return implode( "\n\n", $translated_parts );
    }
    
    /**
     * Traduz HTML grande dividindo em partes
     */
    private function translate_large_html( $html, $source_lang, $target_lang ) {
        // Divide o HTML em seções baseadas em tags de título ou parágrafos
        $sections = preg_split( '/(?=<(h[1-6]|p|div|section|article)[^>]*>)/i', $html );
        
        $translated_sections = array();
        
        foreach ( $sections as $section ) {
            $section = trim( $section );
            if ( empty( $section ) ) {
                $translated_sections[] = $section;
                continue;
            }
            
            // Se a seção for muito grande, divide ainda mais
            if ( strlen( $section ) > 15000 ) {
                $sub_sections = preg_split( '/(?=<p[^>]*>)/i', $section );
                $translated_sub_sections = array();
                
                foreach ( $sub_sections as $sub_section ) {
                    if ( strlen( $sub_section ) > 5000 ) {
                        // Para partes muito grandes, usa tradução de texto simples
                        $text_content = strip_tags( $sub_section );
                        $translated_text = $this->translate_text( $text_content, $source_lang, $target_lang );
                        $translated_sub_sections[] = '<p>' . $translated_text . '</p>';
                    } else {
                        $translated_sub_sections[] = $this->translate_html_fallback( $sub_section, $source_lang, $target_lang );
                    }
                }
                
                $translated_sections[] = implode( '', $translated_sub_sections );
            } else {
                $translated_sections[] = $this->translate_html_fallback( $section, $source_lang, $target_lang );
            }
        }
        
        return implode( '', $translated_sections );
    }
    
    /**
     * Traduz usando DeepL API (texto simples) - CORRIGIDO
     */
    private function translate_with_deepl( $text, $source_lang, $target_lang ) {
        if ( empty( $this->api_key ) ) {
            return $this->translate_with_google( $text, $source_lang, $target_lang );
        }
        
        try {
            // Determina endpoint baseado na chave API
            $endpoint = strpos( $this->api_key, ':fx' ) !== false 
                ? 'https://api-free.deepl.com/v2/translate'
                : 'https://api.deepl.com/v2/translate';
            
            $params = array(
                'auth_key' => $this->api_key,
                'text' => $text,
                'target_lang' => strtoupper( $target_lang ),
                'preserve_formatting' => '1',
                'split_sentences' => '1',
            );
            
            if ( $source_lang !== 'auto' && $source_lang !== '' ) {
                $params['source_lang'] = strtoupper( $source_lang );
            }
            
            $args = array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => $params,
            );
            
            $response = wp_remote_post( $endpoint, $args );
            
            if ( is_wp_error( $response ) ) {
                error_log( 'DeepL API Error: ' . $response->get_error_message() );
                return false;
            }
            
            $status = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            
            if ( $status !== 200 ) {
                error_log( 'DeepL API HTTP Error ' . $status . ': ' . $body );
                return false;
            }
            
            $data = json_decode( $body, true );
            
            if ( ! isset( $data['translations'][0]['text'] ) ) {
                error_log( 'DeepL API Invalid Response: ' . $body );
                return false;
            }
            
            return $data['translations'][0]['text'];
            
        } catch ( Exception $e ) {
            error_log( 'DeepL Translation Exception: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Traduz HTML com DeepL - CORRIGIDO
     */
    private function translate_html_with_deepl( $html, $source_lang, $target_lang ) {
        if ( empty( $this->api_key ) ) {
            return $this->translate_html_fallback( $html, $source_lang, $target_lang );
        }
        
        try {
            $endpoint = strpos( $this->api_key, ':fx' ) !== false 
                ? 'https://api-free.deepl.com/v2/translate'
                : 'https://api.deepl.com/v2/translate';
            
            $params = array(
                'auth_key' => $this->api_key,
                'text' => $html,
                'target_lang' => strtoupper( $target_lang ),
                'tag_handling' => 'html',
                'preserve_formatting' => '1',
                'split_sentences' => '1',
                'ignore_tags' => 'code,pre,script,style',
            );
            
            if ( $source_lang !== 'auto' && $source_lang !== '' ) {
                $params['source_lang'] = strtoupper( $source_lang );
            }
            
            $args = array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => $params,
            );
            
            $response = wp_remote_post( $endpoint, $args );
            
            if ( is_wp_error( $response ) ) {
                error_log( 'DeepL HTML API Error: ' . $response->get_error_message() );
                return false;
            }
            
            $status = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            
            if ( $status !== 200 ) {
                error_log( 'DeepL HTML API HTTP Error ' . $status . ': ' . $body );
                return false;
            }
            
            $data = json_decode( $body, true );
            
            if ( ! isset( $data['translations'][0]['text'] ) ) {
                error_log( 'DeepL HTML API Invalid Response: ' . $body );
                return false;
            }
            
            return $data['translations'][0]['text'];
            
        } catch ( Exception $e ) {
            error_log( 'DeepL HTML Translation Exception: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Fallback para tradução de HTML
     */
    private function translate_html_fallback( $html, $source_lang, $target_lang ) {
        // Extrai texto, mantendo placeholders para tags
        $placeholders = array();
        $text = preg_replace_callback( '/<[^>]+>/', function( $matches ) use ( &$placeholders ) {
            $placeholder = '###TAG_' . count( $placeholders ) . '###';
            $placeholders[] = $matches[0];
            return ' ' . $placeholder . ' ';
        }, $html );
        
        // Traduz texto
        $translated_text = $this->translate_with_google( $text, $source_lang, $target_lang );
        
        if ( ! $translated_text ) {
            return $html;
        }
        
        // Restaura tags
        foreach ( $placeholders as $index => $tag ) {
            $placeholder = '###TAG_' . $index . '###';
            $translated_text = str_replace( $placeholder, $tag, $translated_text );
        }
        
        return $translated_text;
    }
    
    /**
     * Traduz usando Google Translate (fallback) - CORRIGIDO
     */
    private function translate_with_google( $text, $source_lang, $target_lang ) {
        try {
            // Lista de idiomas suportados
            $supported_langs = array( 'en', 'pt', 'es', 'fr', 'de', 'it', 'nl', 'pl', 'ru' );
            
            if ( $source_lang !== 'auto' && ! in_array( $source_lang, $supported_langs ) ) {
                $source_lang = 'auto';
            }
            
            if ( ! in_array( $target_lang, $supported_langs ) ) {
                $target_lang = 'pt';
            }
            
            // Tenta usar a API do Google Translate via servidor público
            $servers = array(
                'https://translate.argosopentech.com/translate',
                'https://libretranslate.de/translate',
                'https://translate.terraprint.co/translate',
            );
            
            foreach ( $servers as $server ) {
                $response = wp_remote_post( $server, array(
                    'timeout' => 15,
                    'headers' => array( 
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ),
                    'body' => json_encode( array(
                        'q' => $text,
                        'source' => $source_lang === 'auto' ? '' : $source_lang,
                        'target' => $target_lang,
                        'format' => 'text',
                    ) ),
                ) );
                
                if ( ! is_wp_error( $response ) ) {
                    $status = wp_remote_retrieve_response_code( $response );
                    if ( $status === 200 ) {
                        $body = wp_remote_retrieve_body( $response );
                        $data = json_decode( $body, true );
                        
                        if ( isset( $data['translatedText'] ) && ! empty( $data['translatedText'] ) ) {
                            return $data['translatedText'];
                        }
                    }
                }
            }
            
            // Fallback para método antigo do Google Translate
            $url = sprintf(
                'https://translate.googleapis.com/translate_a/single?client=gtx&sl=%s&tl=%s&dt=t&q=%s',
                urlencode( $source_lang ),
                urlencode( $target_lang ),
                urlencode( $text )
            );
            
            $response = wp_remote_get( $url, array(
                'timeout' => 15,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ) );
            
            if ( is_wp_error( $response ) ) {
                return false;
            }
            
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( empty( $data[0] ) ) {
                return false;
            }
            
            $translation = '';
            foreach ( $data[0] as $segment ) {
                if ( isset( $segment[0] ) ) {
                    $translation .= $segment[0];
                }
            }
            
            return $translation ?: false;
            
        } catch ( Exception $e ) {
            error_log( 'Google Translation Error: ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Obtém tradução do cache
     */
    private function get_cached_translation( $text, $source_lang, $target_lang, $type = 'text' ) {
        $hash = md5( $text . $source_lang . $target_lang . $type );
        $transient_key = 'manus_translation_' . $hash;
        
        $cached = get_transient( $transient_key );
        
        return $cached !== false ? $cached : false;
    }
    
    /**
     * Armazena tradução no cache
     */
    private function cache_translation( $original, $translated, $source_lang, $target_lang, $type = 'text' ) {
        $hash = md5( $original . $source_lang . $target_lang . $type );
        $transient_key = 'manus_translation_' . $hash;
        
        // Cache por 30 dias para traduções bem-sucedidas
        set_transient( $transient_key, $translated, 30 * DAY_IN_SECONDS );
    }
    
    /**
     * Testa conexão com serviço de tradução
     */
    public function test_connection() {
        $test_text = 'Hello, world! This is a test.';
        
        if ( $this->api_service === 'deepl' && ! empty( $this->api_key ) ) {
            $result = $this->translate_with_deepl( $test_text, 'en', 'pt' );
            if ( $result && $result !== $test_text ) {
                return array(
                    'success' => true,
                    'service' => 'DeepL',
                    'original' => $test_text,
                    'translated' => $result,
                    'message' => 'Conexão com DeepL estabelecida com sucesso!',
                );
            }
        }
        
        // Testa Google Translate como fallback
        $result = $this->translate_with_google( $test_text, 'en', 'pt' );
        if ( $result && $result !== $test_text ) {
            return array(
                'success' => true,
                'service' => 'Google Translate',
                'original' => $test_text,
                'translated' => $result,
                'message' => 'Conexão com Google Translate estabelecida!',
            );
        }
        
        return array(
            'success' => false,
            'error' => 'Não foi possível conectar a nenhum serviço de tradução',
        );
    }
    
    /**
     * Limpa cache de traduções
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_manus_translation_%' 
            OR option_name LIKE '_transient_timeout_manus_translation_%'"
        );
        
        return true;
    }
    
    /**
     * Processa conteúdo completo para tradução
     */
    public function process_content_translation( $title, $content, $excerpt = '', $translate = true, $target_lang = 'pt' ) {
        $result = array(
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'translated' => false,
        );
        
        if ( ! $translate ) {
            return $result;
        }
        
        // Detecta idioma do conteúdo
        $combined_text = $title . ' ' . strip_tags( $content );
        $source_lang = $this->detect_language( $combined_text );
        
        // Se já está no idioma alvo, não traduz
        if ( $source_lang === $target_lang ) {
            return $result;
        }
        
        // Traduz título
        if ( ! empty( $title ) ) {
            $translated_title = $this->translate_text( $title, $source_lang, $target_lang );
            if ( $translated_title && $translated_title !== $title ) {
                $result['title'] = $translated_title;
                $result['translated'] = true;
            }
        }
        
        // Traduz conteúdo
        if ( ! empty( $content ) ) {
            $translated_content = $this->translate_html( $content, $source_lang, $target_lang );
            if ( $translated_content && $translated_content !== $content ) {
                $result['content'] = $translated_content;
                $result['translated'] = true;
            }
        }
        
        // Traduz excerpt
        if ( ! empty( $excerpt ) ) {
            $translated_excerpt = $this->translate_text( $excerpt, $source_lang, $target_lang );
            if ( $translated_excerpt && $translated_excerpt !== $excerpt ) {
                $result['excerpt'] = $translated_excerpt;
            }
        }
        
        return $result;
    }
}