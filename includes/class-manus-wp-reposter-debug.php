<?php
class Manus_WP_Reposter_Debug {

    private static $instance  = null;
    private $debug_enabled    = false;
    private $log_buffer       = array();

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->debug_enabled = defined( 'MANUS_DEBUG' ) && MANUS_DEBUG;
    }

    public function log( $level, $message, $context = null ) {
        if ( ! $this->debug_enabled ) {
            return;
        }
        $this->log_buffer[] = array(
            'timestamp' => microtime( true ),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        );
        if ( in_array( $level, array( 'ERROR', 'FATAL' ) ) ) {
            $this->flush_logs();
        }
    }

    public function flush_logs() {
        if ( empty( $this->log_buffer ) ) {
            return;
        }
        $log_file    = WP_CONTENT_DIR . '/debug-manus.log';
        $log_content = '';
        foreach ( $this->log_buffer as $log ) {
            $log_content .= sprintf(
                "[%s] %s: %s\n",
                date( 'Y-m-d H:i:s', (int) $log['timestamp'] ),
                $log['level'],
                $log['message']
            );
            if ( $log['context'] ) {
                $log_content .= 'Context: ' . print_r( $log['context'], true ) . "\n";
            }
            $log_content .= "\n";
        }
        file_put_contents( $log_file, $log_content, FILE_APPEND | LOCK_EX );
        $this->log_buffer = array();
    }

    public function __destruct() {
        $this->flush_logs();
    }
}