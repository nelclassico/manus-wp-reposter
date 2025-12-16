<?php
/**
 * Classe de extração de conteúdo - COM REMOÇÃO COMPLETA DE ELEMENTOS
 */
class Manus_WP_Reposter_Content_Extractor {
    
    private $elements_to_remove = array(
        // IDs específicos para remover COMPLETAMENTE
        'ids' => array(
            'about-the-author',
            'author-bio',
            'post-author',
            'author-box',
            'article-author',
            'comments',
            'respond',
            'comment-form',
            'comment-list',
            'related-posts',
            'you-may-also-like',
            'similar-posts',
            'share-buttons',
            'social-share',
            'newsletter-signup',
            'subscribe-form',
            'ad-container',
            'ad-wrapper',
            'ad-slot',
            'advertisement',
            'sidebar',
            'widget-area',
            'navigation',
            'breadcrumb',
            'post-meta',
            'post-footer',
            'entry-footer',
            'article-footer',
            'before-comments',
            'after-content',
            'post-navigation',
            'pagination',
            'rating-container',
            'review-box',
            'score-card',
        ),
        
        // Classes específicas para remover COMPLETAMENTE
        'classes' => array(            
            'author-box',
            'author-bio',
            'about-author',
            'post-author',
            'article-author',
            'comments-area',
            'comment-respond',
            'comment-form',
            'comment-list',
            'related-posts',
            'related-articles',
            'you-may-also-like',
            'similar-posts',
            'post-related',
            'post-primary-tag-related',
            'share-buttons',
            'social-share',
            'share-this',
            'newsletter',
            'newsletter-signup',
            'subscribe',
            'subscribe-form',
            'email-signup',
            'ad-container',
            'ad-wrapper',
            'ad-slot',
            'advertisement',
            'sponsored',
            'promoted',
            'sidebar',
            'widget',
            'sidebar-widget',
            'widget-area',
            'secondary',
            'tertiary',
            'navigation',
            'post-navigation',
            'breadcrumb',
            'breadcrumbs',
            'yoast-breadcrumb',
            'post-meta',
            'entry-meta',
            'article-meta',
            'post-footer',
            'entry-footer',
            'article-footer',
            'before-comments',
            'after-content',
            'pagination',
            'page-nav',
            'rating',
            'movie-rating',
            'review-box',
            'score',
            'vote',
            'imdb-rating',
            'rotten-tomatoes',
            'metascore',
        ),
    );
    
    /**
     * Extrai conteúdo de uma URL
     */
    public function extract_content( $url ) {
        error_log( "Manus Extractor: Iniciando extração de: $url" );
        
        $result = array(
            'success' => false,
            'title' => '',
            'content' => '',
            'featured_image' => '',
            'images' => array(),
            'metadata' => array(),
        );
        
        try {
            // Busca o HTML
            $html = $this->fetch_html( $url );
            
            if ( empty( $html ) ) {
                throw new Exception( 'Não foi possível obter o conteúdo da URL' );
            }
            
            // Extrai título
            $result['title'] = $this->extract_title( $html, $url );
            
            // Extrai conteúdo principal
            $result['content'] = $this->extract_main_content_with_filters( $html, $url );
            
            // Extrai imagem destacada
            $result['featured_image'] = $this->extract_featured_image( $html, $url );
            
            // Extrai outras imagens
            $result['images'] = $this->extract_images( $html, $url );
            
            // Extrai metadados
            $result['metadata'] = $this->extract_metadata( $html );
            
            $result['success'] = true;
            
            error_log( "Manus Extractor: Extração concluída com sucesso" );
            error_log( "Manus Extractor: Título: " . substr( $result['title'], 0, 100 ) . "..." );
            error_log( "Manus Extractor: Conteúdo: " . strlen( $result['content'] ) . " caracteres" );
            error_log( "Manus Extractor: Imagem destacada: " . $result['featured_image'] );
            
        } catch ( Exception $e ) {
            error_log( "Manus Extractor: Erro na extração: " . $e->getMessage() );
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Busca HTML da URL
     */
    private function fetch_html( $url ) {
        $response = wp_remote_get( $url, array(
            'timeout' => 30,
            'sslverify' => false,
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Erro na requisição: ' . $response->get_error_message() );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            throw new Exception( 'HTTP Error: ' . $status_code );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        
        // Verifica se é HTML
        if ( strpos( $content_type, 'text/html' ) === false ) {
            throw new Exception( 'Conteúdo não é HTML: ' . $content_type );
        }
        
        // Detecta encoding
        $encoding = $this->detect_encoding( $body );
        if ( $encoding && $encoding !== 'UTF-8' ) {
            $body = mb_convert_encoding( $body, 'UTF-8', $encoding );
        }
        
        return $body;
    }
    
    /**
     * Detecta encoding do HTML
     */
    private function detect_encoding( $html ) {
        // Verifica meta tag charset
        if ( preg_match( '/<meta[^>]+charset=["\']?([^"\'>]+)["\']?/i', $html, $matches ) ) {
            return strtoupper( trim( $matches[1] ) );
        }
        
        // Fallback para UTF-8
        return 'UTF-8';
    }
    
    /**
     * Extrai título
     */
    private function extract_title( $html, $url ) {
        // Tenta obter do HTML
        if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
            $title = trim( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            if ( ! empty( $title ) ) {
                return $title;
            }
        }
        
        // Tenta meta og:title
        if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            $title = trim( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            if ( ! empty( $title ) ) {
                return $title;
            }
        }
        
        // Fallback: extrai do URL
        $parsed_url = parse_url( $url );
        $path = $parsed_url['path'] ?? '';
        $title = basename( $path );
        $title = str_replace( array( '-', '_' ), ' ', $title );
        $title = ucwords( $title );
        
        return $title ?: 'Artigo Importado';
    }
    
    /**
     * Extrai conteúdo principal com filtros específicos
     */
    private function extract_main_content_with_filters( $html, $url ) {
        // Primeiro, extrai o conteúdo principal
        $content = $this->extract_by_selectors( $html );
        
        if ( ! empty( $content ) && strlen( strip_tags( $content ) ) > 500 ) {
            return $this->clean_content_complete_removal( $content, $url );
        }
        
        // Se não encontrou, usa algoritmo
        $content = $this->extract_by_algorithm( $html );
        
        if ( ! empty( $content ) ) {
            return $this->clean_content_complete_removal( $content, $url );
        }
        
        return '';
    }
    
    /**
     * Extrai por seletores comuns
     */
    private function extract_by_selectors( $html ) {
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        $xpath = new DOMXPath( $dom );
        
        // Seletores de conteúdo em ordem de prioridade
        $selectors = array(
            "//article",
            "//main",
            "//div[contains(@class, 'post-content')]",
            "//div[contains(@class, 'entry-content')]",
            "//div[contains(@class, 'article-content')]",
            "//div[contains(@class, 'story-content')]",
            "//div[contains(@class, 'content') and not(contains(@class, 'menu'))]",
            "//div[@id='content']",
            "//div[contains(@class, 'post-body')]",
            "//div[contains(@class, 'article-body')]",
        );
        
        foreach ( $selectors as $selector ) {
            $nodes = $xpath->query( $selector );
            if ( $nodes->length > 0 ) {
                foreach ( $nodes as $node ) {
                    $text = trim( $node->textContent );
                    // Verifica se tem conteúdo suficiente
                    if ( strlen( $text ) > 500 ) {
                        return $dom->saveHTML( $node );
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extrai usando algoritmo tipo Readability
     */
    private function extract_by_algorithm( $html ) {
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        $xpath = new DOMXPath( $dom );
        
        // Remove elementos indesejados
        $this->remove_unwanted_elements( $dom, $xpath );
        
        // Encontra todos os elementos que podem conter conteúdo
        $content_nodes = $xpath->query( "//div|//section|//article|//main" );
        
        $best_node = null;
        $best_score = 0;
        
        foreach ( $content_nodes as $node ) {
            $score = $this->score_node( $node, $xpath );
            
            if ( $score > $best_score ) {
                $best_score = $score;
                $best_node = $node;
            }
        }
        
        if ( $best_node ) {
            return $dom->saveHTML( $best_node );
        }
        
        return '';
    }
    
    /**
     * Remove elementos indesejados
     */
    private function remove_unwanted_elements( $dom, $xpath ) {
        // Remove scripts, styles, forms, etc.
        $tags_to_remove = array( 'script', 'style', 'form', 'iframe', 'noscript' );
        
        foreach ( $tags_to_remove as $tag ) {
            $elements = $xpath->query( "//{$tag}" );
            foreach ( $elements as $element ) {
                if ( $element->parentNode ) {
                    $element->parentNode->removeChild( $element );
                }
            }
        }
    }
    
    /**
     * Calcula score para um nó
     */
    private function score_node( $node, $xpath ) {
        $text = trim( $node->textContent );
        $text_length = strlen( $text );
        
        if ( $text_length < 100 ) {
            return 0;
        }
        
        // Penaliza elementos com muitos links
        $links = $xpath->query( ".//a", $node );
        $link_density = $links->length / ( $text_length / 100 );
        
        // Score base
        $base_score = log( $text_length );
        
        // Ajusta score
        $score = $base_score;
        $score -= $link_density * 0.5;
        
        return $score;
    }
    
    /**
     * Limpa o conteúdo com REMOÇÃO COMPLETA de elementos
     */
    private function clean_content_complete_removal( $html, $url ) {
        if ( empty( $html ) ) {
            return '';
        }
        
        // Primeiro, limpeza com DOMDocument para remoção precisa
        $html = $this->remove_elements_with_dom( $html );
        
        // Depois, limpeza com regex para capturar o que o DOM pode ter perdido
        $html = $this->remove_elements_with_regex( $html );
        
        // Remove scripts, styles, forms
        $html = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $html );
        $html = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $html );
        $html = preg_replace( '/<form\b[^>]*>(.*?)<\/form>/is', '', $html );
        $html = preg_replace( '/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $html );
        
        // Remove comentários
        $html = preg_replace( '/<!--.*?-->/s', '', $html );
        
        // Remoção específica por URL (domínio)
        $html = $this->remove_site_specific_elements( $html, $url );
        
        // Remove atributos desnecessários
        $html = $this->clean_attributes( $html );
        
        // Remove elementos vazios
        $html = $this->remove_empty_elements( $html );
        
        // Formata parágrafos
        $html = $this->format_paragraphs( $html );
        
        return trim( $html );
    }
    
    /**
     * Remove elementos usando DOMDocument - REMOÇÃO COMPLETA
     */
    private function remove_elements_with_dom( $html ) {
        if ( empty( $html ) ) {
            return '';
        }
        
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        $xpath = new DOMXPath( $dom );
        
        // Remove elementos por ID
        foreach ( $this->elements_to_remove['ids'] as $id ) {
            // Busca por ID exato
            $elements = $xpath->query( "//*[@id='{$id}']" );
            $this->remove_elements_from_dom( $elements );
            
            // Busca por ID que contém (para variações)
            $elements = $xpath->query( "//*[contains(@id, '{$id}')]" );
            $this->remove_elements_from_dom( $elements );
            
            // Também busca por variações do ID
            $variations = $this->get_id_variations( $id );
            foreach ( $variations as $variation ) {
                $elements = $xpath->query( "//*[@id='{$variation}']" );
                $this->remove_elements_from_dom( $elements );
                
                $elements = $xpath->query( "//*[contains(@id, '{$variation}')]" );
                $this->remove_elements_from_dom( $elements );
            }
        }
        
        // Remove elementos por classe
        foreach ( $this->elements_to_remove['classes'] as $class ) {
            // Busca por classe exata
            $elements = $xpath->query( "//*[contains(@class, '{$class}')]" );
            $this->remove_elements_from_dom( $elements );
            
            // Busca por classe exata (com espaços)
            $elements = $xpath->query( "//*[@class='{$class}']" );
            $this->remove_elements_from_dom( $elements );
            
            // Também busca por variações da classe
            $variations = $this->get_class_variations( $class );
            foreach ( $variations as $variation ) {
                $elements = $xpath->query( "//*[contains(@class, '{$variation}')]" );
                $this->remove_elements_from_dom( $elements );
            }
        }
        
        // Remove sidebars, navs, etc. de forma genérica
        $generic_selectors = array(
            "//aside",
            "//nav",
            "//footer[not(ancestor::article)]",
            "//header[not(ancestor::article)]",
        );
        
        foreach ( $generic_selectors as $selector ) {
            $elements = $xpath->query( $selector );
            $this->remove_elements_from_dom( $elements );
        }
        
        // Obtém o HTML limpo
        $body = $dom->getElementsByTagName( 'body' )->item(0);
        $clean_html = '';
        
        if ( $body ) {
            foreach ( $body->childNodes as $child ) {
                $clean_html .= $dom->saveHTML( $child );
            }
        }
        
        return $clean_html;
    }
    
    /**
     * Remove elementos do DOM
     */
    private function remove_elements_from_dom( $elements ) {
        $elements_to_remove = array();
        
        // Primeiro coleta todos os elementos para evitar problemas com a iteração
        foreach ( $elements as $element ) {
            $elements_to_remove[] = $element;
        }
        
        // Agora remove todos
        foreach ( $elements_to_remove as $element ) {
            if ( $element->parentNode ) {
                $element->parentNode->removeChild( $element );
            }
        }
    }
    
    /**
     * Remove elementos usando regex - para complementar o DOM
     */
    private function remove_elements_with_regex( $html ) {
        // Remove elementos por ID com regex (backup)
        foreach ( $this->elements_to_remove['ids'] as $id ) {
            // Padrão 1: elemento com ID específico
            $pattern = '/<[^>]*\s+id\s*=\s*["\']' . preg_quote( $id, '/' ) . '["\'][^>]*>.*?<\/[^>]+>/is';
            $html = preg_replace( $pattern, '', $html );
            
            // Padrão 2: elemento que CONTÉM o ID
            $pattern = '/<[^>]*\s+id\s*=\s*["\'][^"\']*' . preg_quote( $id, '/' ) . '[^"\']*["\'][^>]*>.*?<\/[^>]+>/is';
            $html = preg_replace( $pattern, '', $html );
            
            // Também para variações
            $variations = $this->get_id_variations( $id );
            foreach ( $variations as $variation ) {
                $pattern = '/<[^>]*\s+id\s*=\s*["\']' . preg_quote( $variation, '/' ) . '["\'][^>]*>.*?<\/[^>]+>/is';
                $html = preg_replace( $pattern, '', $html );
            }
        }
        
        // Remove elementos por classe com regex (backup)
        foreach ( $this->elements_to_remove['classes'] as $class ) {
            // Padrão 1: classe específica
            $pattern = '/<[^>]*\s+class\s*=\s*["\'][^"\']*\b' . preg_quote( $class, '/' ) . '\b[^"\']*["\'][^>]*>.*?<\/[^>]+>/is';
            $html = preg_replace( $pattern, '', $html );
            
            // Padrão 2: classe exata
            $pattern = '/<[^>]*\s+class\s*=\s*["\']' . preg_quote( $class, '/' ) . '["\'][^>]*>.*?<\/[^>]+>/is';
            $html = preg_replace( $pattern, '', $html );
        }
        
        return $html;
    }
    
    /**
     * Obtém variações de um ID
     */
    private function get_id_variations( $id ) {
        $variations = array();
        
        // Original
        $variations[] = $id;
        
        // Com underscores
        $variations[] = str_replace( '-', '_', $id );
        
        // Sem hífens
        $variations[] = str_replace( '-', '', $id );
        
        // CamelCase
        $variations[] = $this->to_camel_case( $id );
        
        // Capitalized
        $variations[] = ucfirst( str_replace( '-', '', $id ) );
        
        return array_unique( $variations );
    }
    
    /**
     * Obtém variações de uma classe
     */
    private function get_class_variations( $class ) {
        $variations = array();
        
        // Original
        $variations[] = $class;
        
        // Com underscores
        $variations[] = str_replace( '-', '_', $class );
        
        // Sem hífens
        $variations[] = str_replace( '-', '', $class );
        
        return array_unique( $variations );
    }
    
    /**
     * Converte para camelCase
     */
    private function to_camel_case( $string ) {
        $string = str_replace( array( '-', '_' ), ' ', $string );
        $string = ucwords( $string );
        $string = str_replace( ' ', '', $string );
        $string = lcfirst( $string );
        return $string;
    }
    
    /**
     * Remove elementos específicos por site
     */
    private function remove_site_specific_elements( $html, $url ) {
        $domain = parse_url( $url, PHP_URL_HOST );
        
        // Remove elementos específicos por domínio
        switch ( $domain ) {
            case 'imdb.com':
                // Remove ratings específicos do IMDB
                $patterns = array(
                    '/<div[^>]*class="[^"]*\bimdb-rating\b[^"]*"[^>]*>.*?<\/div>/is',
                    '/<span[^>]*class="[^"]*\brating\b[^"]*"[^>]*>.*?<\/span>/is',
                    '/<div[^>]*class="[^"]*\bstar-rating\b[^"]*"[^>]*>.*?<\/div>/is',
                );
                foreach ( $patterns as $pattern ) {
                    $html = preg_replace( $pattern, '', $html );
                }
                break;
        }
        
        return $html;
    }
    
    /**
     * Limpa atributos desnecessários
     */
    private function clean_attributes( $html ) {
        // Remove atributos de eventos
        $html = preg_replace( '/\s(onclick|onload|onmouseover|onmouseout|onkeypress|onkeydown|onkeyup)="[^"]*"/i', '', $html );
        
        // Remove estilos inline
        $html = preg_replace( '/\sstyle="[^"]*"/i', '', $html );
        
        // Remove classes desnecessárias (exceto algumas úteis)
        $html = preg_replace_callback( '/<([a-zA-Z][a-zA-Z0-9]*)[^>]*>/', function( $matches ) {
            $tag = $matches[1];
            $attributes = $matches[0];
            
            // Se tem classe, limpa
            if ( preg_match( '/class="([^"]*)"/', $attributes, $class_matches ) ) {
                $classes = explode( ' ', $class_matches[1] );
                $clean_classes = array();
                
                foreach ( $classes as $class ) {
                    $class = trim( $class );
                    if ( ! empty( $class ) ) {
                        // Mantém apenas classes úteis
                        if ( preg_match( '/^(wp-|align|size-|caption|image|img-|content|post|article)/', $class ) ) {
                            $clean_classes[] = $class;
                        }
                    }
                }
                
                if ( empty( $clean_classes ) ) {
                    $attributes = preg_replace( '/\s*class="[^"]*"/', '', $attributes );
                } else {
                    $attributes = preg_replace( '/class="[^"]*"/', 'class="' . implode( ' ', $clean_classes ) . '"', $attributes );
                }
            }
            
            return $attributes;
        }, $html );
        
        return $html;
    }
    
    /**
     * Remove elementos vazios
     */
    private function remove_empty_elements( $html ) {
        // Remove elementos completamente vazios
        $html = preg_replace( '/<(\w+)[^>]*>\s*<\/\1>/s', '', $html );
        
        // Remove elementos com muito pouco texto
        $html = preg_replace_callback( '/<(\w+)[^>]*>(.*?)<\/\1>/s', function( $matches ) {
            $tag = $matches[1];
            $content = trim( strip_tags( $matches[2] ) );
            
            // Se tem menos de 10 caracteres e não é uma tag importante
            if ( strlen( $content ) < 10 && ! in_array( $tag, array( 'a', 'img', 'br', 'hr', 'input' ) ) ) {
                return '';
            }
            
            return $matches[0];
        }, $html );
        
        return $html;
    }
    
    /**
     * Formata parágrafos
     */
    private function format_paragraphs( $html ) {
        // Se não tem parágrafos, cria
        if ( ! preg_match( '/<p[^>]*>/', $html ) ) {
            $lines = preg_split( '/(<br\s*\/?>\s*)+/i', $html );
            $paragraphs = array();
            
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( ! empty( $line ) && strlen( strip_tags( $line ) ) > 20 ) {
                    $paragraphs[] = '<p>' . $line . '</p>';
                }
            }
            
            if ( ! empty( $paragraphs ) ) {
                $html = implode( "\n", $paragraphs );
            }
        }
        
        // Remove múltiplas quebras de linha
        $html = preg_replace( '/\n\s*\n+/', "\n\n", $html );
        
        return $html;
    }
    
    /**
     * Extrai imagem destacada
     */
    private function extract_featured_image( $html, $base_url ) {
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( $html );
        $xpath = new DOMXPath( $dom );
        
        // Tenta várias fontes
        $sources = array(
            "//meta[@property='og:image']/@content",
            "//meta[@property='og:image:url']/@content",
            "//meta[@name='twitter:image']/@content",
            "//meta[@name='twitter:image:src']/@content",
            "//meta[@itemprop='image']/@content",
        );
        
        foreach ( $sources as $source ) {
            $nodes = $xpath->query( $source );
            if ( $nodes->length > 0 ) {
                $image_url = $nodes->item(0)->nodeValue;
                $image_url = $this->make_absolute_url( $image_url, $base_url );
                
                // Verifica se parece ser uma imagem
                if ( $this->is_image_url( $image_url ) ) {
                    return $image_url;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extrai todas as imagens
     */
    private function extract_images( $html, $base_url ) {
        $images = array();
        
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( $html );
        $xpath = new DOMXPath( $dom );
        
        $img_nodes = $xpath->query( "//img/@src" );
        
        foreach ( $img_nodes as $node ) {
            $image_url = $node->nodeValue;
            $image_url = $this->make_absolute_url( $image_url, $base_url );
            
            if ( $this->is_image_url( $image_url ) ) {
                $images[] = $image_url;
            }
        }
        
        return array_unique( $images );
    }
    
    /**
     * Extrai metadados
     */
    private function extract_metadata( $html ) {
        $metadata = array();
        
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( $html );
        $xpath = new DOMXPath( $dom );
        
        // Meta description
        $desc_nodes = $xpath->query( "//meta[@name='description']/@content" );
        if ( $desc_nodes->length > 0 ) {
            $metadata['description'] = $desc_nodes->item(0)->nodeValue;
        }
        
        return $metadata;
    }
    
    /**
     * Converte URL relativa para absoluta
     */
    private function make_absolute_url( $url, $base_url ) {
        if ( empty( $url ) ) {
            return '';
        }
        
        // Se já é absoluta
        if ( preg_match( '#^https?://#i', $url ) ) {
            return $url;
        }
        
        // Parse da URL base
        $base_parts = parse_url( $base_url );
        $base_scheme = $base_parts['scheme'] ?? 'https';
        $base_host = $base_parts['host'] ?? '';
        $base_path = $base_parts['path'] ?? '';
        
        // Se começa com //
        if ( strpos( $url, '//' ) === 0 ) {
            return $base_scheme . ':' . $url;
        }
        
        // Se começa com /
        if ( strpos( $url, '/' ) === 0 ) {
            return $base_scheme . '://' . $base_host . $url;
        }
        
        // URL relativa
        $base_path = dirname( $base_path );
        if ( $base_path === '.' || $base_path === '/' ) {
            $base_path = '';
        }
        
        return $base_scheme . '://' . $base_host . '/' . trim( $base_path . '/' . $url, '/' );
    }
    
    /**
     * Verifica se URL é de imagem
     */
    private function is_image_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }
        
        $image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg' );
        $path = parse_url( $url, PHP_URL_PATH );
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        
        return in_array( $extension, $image_extensions );
    }
    
    /**
     * Método público para adicionar elementos à lista de remoção
     */
    public function add_elements_to_remove( $type, $elements ) {
        if ( isset( $this->elements_to_remove[$type] ) ) {
            $this->elements_to_remove[$type] = array_merge( 
                $this->elements_to_remove[$type], 
                (array) $elements 
            );
            $this->elements_to_remove[$type] = array_unique( $this->elements_to_remove[$type] );
        }
    }
    
    /**
     * Método público para ver a lista atual
     */
    public function get_elements_to_remove() {
        return $this->elements_to_remove;
    }
}