<?php
class Manus_WP_Reposter_Multi_Feed {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function parse_item_data( $item ) {
        return array(
            'featured_image' => $this->extract_featured_image( $item ),
            'creator'        => $this->extract_creator( $item ),
            'categories'     => $this->extract_categories( $item ),
            'description'    => $item->get_description(),
        );
    }

    private function extract_featured_image( $item ) {
        $enclosures = $item->get_enclosures();
        if ( ! empty( $enclosures ) ) {
            foreach ( $enclosures as $enclosure ) {
                if ( $enclosure->get_link() && strpos( $enclosure->get_type(), 'image' ) !== false ) {
                    return $enclosure->get_link();
                }
            }
        }
        if ( $media_content = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'content' ) ) {
            if ( isset( $media_content[0]['attribs']['']['url'] ) ) {
                return $media_content[0]['attribs']['']['url'];
            }
        }
        if ( $media_thumbnail = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'thumbnail' ) ) {
            if ( isset( $media_thumbnail[0]['attribs']['']['url'] ) ) {
                return $media_thumbnail[0]['attribs']['']['url'];
            }
        }
        $content = $item->get_content();
        if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
            return $matches[1];
        }
        return '';
    }

    private function extract_creator( $item ) {
        if ( $creator = $item->get_item_tags( 'http://purl.org/dc/elements/1.1/', 'creator' ) ) {
            return $creator[0]['data'];
        }
        if ( $author = $item->get_author() ) {
            return $author->get_name();
        }
        return '';
    }

    private function extract_categories( $item ) {
        $categories      = array();
        $item_categories = $item->get_categories();
        if ( ! empty( $item_categories ) ) {
            foreach ( $item_categories as $category ) {
                $categories[] = $category->get_label();
            }
        }
        return array_unique( $categories );
    }
}