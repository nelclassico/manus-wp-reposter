<?php
/**
 * Classe de processamento de imagens do plugin Manus WP Reposter.
 * Responsável por extrair, fazer download e processar imagens do conteúdo.
 */
class Manus_WP_Reposter_Image_Processor {

    /**
     * Processa as imagens do conteúdo HTML.
     * Faz download das imagens, registra como attachments e retorna o HTML atualizado.
     *
     * @param string $html O HTML do conteúdo.
     * @param int $post_id O ID do post.
     * @param string $base_url A URL base para resolver URLs relativas.
     * @return string O HTML com as imagens processadas.
     */
    public function process_images( $html, $post_id, $base_url ) {
        $dom = new DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        $xpath = new DOMXPath( $dom );

        // Encontra todas as imagens
        $images = $xpath->query( '//img' );

        foreach ( $images as $img ) {
            $src = $img->getAttribute( 'src' );

            if ( empty( $src ) ) {
                continue;
            }

            // Resolve URLs relativas
            $src = $this->resolve_url( $src, $base_url );

            // Faz download da imagem
            $attachment_id = $this->download_and_attach_image( $src, $post_id );

            if ( ! empty( $attachment_id ) ) {
                // Atualiza o atributo src da imagem para usar a URL local do WordPress
                $attachment = wp_get_attachment_url( $attachment_id );
                $img->setAttribute( 'src', $attachment );

                // Adiciona classes para melhor formatação
                $classes = $img->getAttribute( 'class' );
                $classes .= ' wp-image-' . $attachment_id;
                $img->setAttribute( 'class', trim( $classes ) );
            }
        }

        // Converte de volta para HTML
        $html = $dom->saveHTML();

        // Remove as tags de abertura e fechamento do documento
        $html = preg_replace( '/<\?xml[^?]+\?>/', '', $html );
        $html = preg_replace( '<!DOCTYPE[^>]+>', '', $html );
        $html = preg_replace( '/<html[^>]*>/', '', $html );
        $html = preg_replace( '/<\/html>/', '', $html );
        $html = preg_replace( '/<body[^>]*>/', '', $html );
        $html = preg_replace( '/<\/body>/', '', $html );
        $html = preg_replace( '/<head[^>]*>.*?<\/head>/is', '', $html );

        return trim( $html );
    }

    /**
     * Faz download de uma imagem e a registra como attachment do post.
     *
     * @param string $image_url A URL da imagem.
     * @param int $post_id O ID do post.
     * @return int O ID do attachment ou 0 em caso de falha.
     */
    private function download_and_attach_image( $image_url, $post_id ) {
        // Valida a URL
        if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            return 0;
        }

        // Faz o download da imagem
        $response = wp_remote_get( $image_url, array(
            'timeout' => 30,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) ) {
            return 0;
        }

        $image_data = wp_remote_retrieve_body( $response );
        if ( empty( $image_data ) ) {
            return 0;
        }

        // Cria um nome único para a imagem
        $filename = basename( parse_url( $image_url, PHP_URL_PATH ) );
        if ( empty( $filename ) || strpos( $filename, '.' ) === false ) {
            $filename = 'image-' . time() . '-' . rand( 1000, 9999 ) . '.jpg';
        }

        // Define o diretório de upload
        $upload_dir = wp_upload_dir();
        $image_path = $upload_dir['path'] . '/' . $filename;

        // Salva a imagem
        if ( file_put_contents( $image_path, $image_data ) === false ) {
            return 0;
        }

        // Obtém informações do arquivo
        $filetype = wp_check_filetype( $image_path );
        if ( empty( $filetype['type'] ) ) {
            @unlink( $image_path );
            return 0;
        }

        // Prepara os dados do attachment
        $attachment_data = array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $image_path ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        // Insere o attachment
        $attachment_id = wp_insert_attachment( $attachment_data, $image_path, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $image_path );
            return 0;
        }

        // Gera os metadados do attachment
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $image_path );
        wp_update_attachment_metadata( $attachment_id, $attach_data );

        return $attachment_id;
    }

    /**
     * Resolve uma URL relativa para uma URL absoluta.
     *
     * @param string $url A URL (relativa ou absoluta).
     * @param string $base_url A URL base.
     * @return string A URL absoluta.
     */
    private function resolve_url( $url, $base_url ) {
        // Se já é uma URL absoluta, retorna
        if ( preg_match( '#^https?://#', $url ) ) {
            return $url;
        }

        // Se é uma URL relativa, resolve
        $base_parts = parse_url( $base_url );
        $base_scheme = $base_parts['scheme'] ?? 'https';
        $base_host = $base_parts['host'] ?? '';
        $base_path = dirname( $base_parts['path'] ?? '/' );

        if ( strpos( $url, '/' ) === 0 ) {
            // URL absoluta no domínio
            return $base_scheme . '://' . $base_host . $url;
        } else {
            // URL relativa
            return $base_scheme . '://' . $base_host . $base_path . '/' . $url;
        }
    }
}
