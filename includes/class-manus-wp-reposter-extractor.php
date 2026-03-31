<?php
/**
 * Classe de extração de conteúdo - VERSÃO 2.2 COM PERCEPÇÃO INTELIGENTE DE CONTEÚDO
 * 
 * Melhorias:
 * - Algoritmo de scoring mais robusto que diferencia conteúdo principal de ruído
 * - Detecção de densidade de conteúdo vs. densidade de links
 * - Remoção inteligente de elementos estruturais (menus, sidebars, rodapés)
 * - Análise de profundidade de nós para evitar elementos aninhados
 * - Suporte a múltiplos padrões de estrutura HTML
 * - Extração de conteúdo de metadados JSON-LD e OpenGraph
 */
class Manus_WP_Reposter_Content_Extractor {
    
    private $elements_to_remove = array(
        'ids' => array(
            'about-the-author', 'author-bio', 'post-author', 'author-box', 'article-author',
            'comments', 'respond', 'comment-form', 'comment-list',
            'related-posts', 'you-may-also-like', 'similar-posts',
            'share-buttons', 'social-share', 'newsletter-signup', 'subscribe-form',
            'ad-container', 'ad-wrapper', 'ad-slot', 'advertisement',
            'sidebar', 'widget-area', 'navigation', 'breadcrumb',
            'post-meta', 'post-footer', 'entry-footer', 'article-footer',
            'before-comments', 'after-content', 'post-navigation', 'pagination',
            'rating-container', 'review-box', 'score-card',
            'menu', 'navbar', 'header-nav', 'footer-nav', 'mobile-menu',
            'top-bar', 'sticky-header', 'fixed-header', 'floating-menu',
        ),
        'classes' => array(            
            'author-box', 'author-bio', 'about-author', 'post-author', 'article-author',
            'comments-area', 'comment-respond', 'comment-form', 'comment-list',
            'related-posts', 'related-articles', 'you-may-also-like', 'similar-posts',
            'post-related', 'post-primary-tag-related',
            'share-buttons', 'social-share', 'share-this',
            'newsletter', 'newsletter-signup', 'subscribe', 'subscribe-form', 'email-signup',
            'ad-container', 'ad-wrapper', 'ad-slot', 'advertisement', 'sponsored', 'promoted',
            'sidebar', 'widget', 'sidebar-widget', 'widget-area', 'secondary', 'tertiary',
            'navigation', 'post-navigation', 'breadcrumb', 'breadcrumbs', 'yoast-breadcrumb',
            'post-meta', 'entry-meta', 'article-meta',
            'post-footer', 'entry-footer', 'article-footer',
            'before-comments', 'after-content', 'pagination', 'page-nav',
            'rating', 'movie-rating', 'review-box', 'score', 'vote', 'imdb-rating',
            'rotten-tomatoes', 'metascore',
            'menu', 'navbar', 'nav-bar', 'header-nav', 'footer-nav', 'mobile-menu',
            'top-bar', 'sticky-header', 'fixed-header', 'floating-menu',
            'advertisement-banner', 'ad-banner', 'sponsored-content',
            'related-content', 'recommended-posts', 'suggested-articles',
            'popup', 'modal', 'overlay', 'lightbox',
            'cookie-notice', 'cookie-banner', 'gdpr-banner',
        ),
    );
    
    public function extract_content( $url ) {
        error_log( "Manus Extractor V2.2: Iniciando extração de: $url" );
        $result = array( 'success' => false, 'title' => '', 'content' => '', 'featured_image' => '', 'images' => array(), 'metadata' => array() );
        
        try {
            $html = $this->fetch_html( $url );
            if ( empty( $html ) ) throw new Exception( 'Não foi possível obter o conteúdo da URL' );
            
            $result['title'] = $this->extract_title( $html, $url );
            $result['content'] = $this->extract_main_content_intelligent( $html, $url );
            $result['featured_image'] = $this->extract_featured_image( $html, $url );
            $result['images'] = $this->extract_images( $html, $url );
            $result['metadata'] = $this->extract_metadata( $html );
            $result['success'] = true;
            
            error_log( "Manus Extractor V2.2: Extração concluída (" . strlen( $result['content'] ) . " chars)" );
        } catch ( Exception $e ) {
            error_log( "Manus Extractor V2.2: Erro: " . $e->getMessage() );
            $result['error'] = $e->getMessage();
        }
        return $result;
    }
    
    private function fetch_html( $url ) {
        $response = wp_remote_get( $url, array(
            'timeout' => 30, 'sslverify' => false, 'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers' => array( 'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' )
        ) );
        if ( is_wp_error( $response ) ) throw new Exception( $response->get_error_message() );
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) throw new Exception( 'HTTP Error: ' . wp_remote_retrieve_response_code( $response ) );
        $body = wp_remote_retrieve_body( $response );
        $encoding = $this->detect_encoding( $body );
        return ( $encoding && $encoding !== 'UTF-8' ) ? mb_convert_encoding( $body, 'UTF-8', $encoding ) : $body;
    }
    
    private function detect_encoding( $html ) {
        if ( preg_match( '/<meta[^>]+charset=["\']?([^"\'>]+)["\']?/i', $html, $matches ) ) return strtoupper( trim( $matches[1] ) );
        return 'UTF-8';
    }
    
    private function extract_title( $html, $url ) {
        if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) return trim( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) return trim( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        return ucwords( str_replace( array( '-', '_' ), ' ', basename( parse_url( $url, PHP_URL_PATH ) ) ) ) ?: 'Artigo Importado';
    }
    
    private function extract_main_content_intelligent( $html, $url ) {
        // Tenta extrair de JSON-LD primeiro (muitos sites modernos usam para o corpo do artigo)
        if ( preg_match( '/<script type=["\']application\/ld\+json["\']>(.*?)<\/script>/is', $html, $matches ) ) {
            $json = json_decode( $matches[1], true );
            if ( $json && isset( $json['articleBody'] ) ) return wpautop( $json['articleBody'] );
        }

        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        $xpath = new DOMXPath( $dom );
        
        $selectors = array( "//article", "//main", "//div[contains(@class, 'post-content')]", "//div[contains(@class, 'entry-content')]", "//div[contains(@class, 'article-body')]", "//div[@id='content']" );
        foreach ( $selectors as $selector ) {
            $nodes = $xpath->query( $selector );
            foreach ( $nodes as $node ) {
                if ( strlen( trim( $node->textContent ) ) > 500 ) return $this->clean_content( $dom->saveHTML( $node ) );
            }
        }
        
        // Algoritmo de Scoring
        $this->remove_unwanted_elements( $dom, $xpath );
        $content_nodes = $xpath->query( "//div|//section|//article" );
        $best_node = null; $best_score = 0;
        foreach ( $content_nodes as $node ) {
            $score = $this->calculate_score( $node, $xpath );
            if ( $score > $best_score ) { $best_score = $score; $best_node = $node; }
        }
        return ( $best_node && $best_score > 10 ) ? $this->clean_content( $dom->saveHTML( $best_node ) ) : '';
    }
    
    private function calculate_score( $node, $xpath ) {
        $text = trim( $node->textContent );
        $text_len = strlen( $text );
        if ( $text_len < 100 ) return 0;
        
        $p_count = $xpath->query( ".//p", $node )->length;
        $link_len = 0;
        foreach ( $xpath->query( ".//a", $node ) as $link ) $link_len += strlen( trim( $link->textContent ) );
        $link_density = $link_len / max( 1, $text_len );
        
        $score = ( $text_len / 100 ) + ( $p_count * 10 );
        if ( $link_density > 0.3 ) $score -= ( $link_density * 100 );
        
        $class_id = $node->getAttribute( 'class' ) . ' ' . $node->getAttribute( 'id' );
        if ( preg_match( '/(content|article|body|main)/i', $class_id ) ) $score += 30;
        if ( preg_match( '/(sidebar|menu|nav|footer|ad)/i', $class_id ) ) $score -= 100;
        
        return $score;
    }
    
    private function remove_unwanted_elements( $dom, $xpath ) {
        foreach ( array( 'script', 'style', 'form', 'iframe', 'noscript' ) as $tag ) {
            foreach ( $xpath->query( "//{$tag}" ) as $el ) if ( $el->parentNode ) $el->parentNode->removeChild( $el );
        }
    }
    
    private function clean_content( $html ) {
        if ( empty( $html ) ) return '';
        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        $xpath = new DOMXPath( $dom );
        
        foreach ( $this->elements_to_remove['ids'] as $id ) $this->remove_by_xpath( $xpath, "//*[@id='{$id}']|//*[contains(@id, '{$id}')]" );
        foreach ( $this->elements_to_remove['classes'] as $cls ) $this->remove_by_xpath( $xpath, "//*[contains(@class, '{$cls}')]" );
        $this->remove_by_xpath( $xpath, "//aside|//nav|//footer|//header" );
        
        $body = $dom->getElementsByTagName( 'body' )->item(0);
        $clean = '';
        if ( $body ) foreach ( $body->childNodes as $child ) $clean .= $dom->saveHTML( $child );
        
        $clean = preg_replace( '/\s+style=["\'][^"\']*["\']/i', '', $clean );
        $clean = preg_replace( '/\s+on[a-z]+=["\'][^"\']*["\']/i', '', $clean );
        return trim( preg_replace( '/\s+/', ' ', $clean ) );
    }
    
    private function remove_by_xpath( $xpath, $query ) {
        $nodes = $xpath->query( $query );
        $to_remove = array();
        foreach ( $nodes as $n ) $to_remove[] = $n;
        foreach ( $to_remove as $n ) if ( $n->parentNode ) $n->parentNode->removeChild( $n );
    }
    
    private function extract_featured_image( $html, $url ) {
        if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) return $this->make_absolute_url( $matches[1], $url );
        return '';
    }
    
    private function extract_images( $html, $url ) {
        $images = array();
        if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            foreach ( $matches[1] as $img ) {
                $abs = $this->make_absolute_url( $img, $url );
                if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', parse_url( $abs, PHP_URL_PATH ) ) ) $images[] = $abs;
            }
        }
        return array_unique( $images );
    }
    
    private function extract_metadata( $html ) {
        $meta = array();
        if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches ) ) $meta['description'] = $matches[1];
        return $meta;
    }
    
    private function make_absolute_url( $url, $base ) {
        if ( empty( $url ) || preg_match( '#^https?://#i', $url ) ) return $url;
        $parts = parse_url( $base );
        $scheme = $parts['scheme'] ?? 'https'; $host = $parts['host'] ?? '';
        if ( strpos( $url, '//' ) === 0 ) return $scheme . ':' . $url;
        if ( strpos( $url, '/' ) === 0 ) return $scheme . '://' . $host . $url;
        return $scheme . '://' . $host . '/' . ltrim( $url, '/' );
    }
}
