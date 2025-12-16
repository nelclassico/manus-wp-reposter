<?php
/**
 * Funções auxiliares do plugin
 */

// Evita acesso direto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Loga mensagens usando o sistema de posts
 */
function manus_log( $level, $message, $context = null ) {
    // Tenta obter a instância do logger
    if ( class_exists( 'Manus_WP_Reposter_Logger' ) ) {
        try {
            $logger = Manus_WP_Reposter_Logger::instance();
            return $logger->add_log( $level, $message, $context );
        } catch ( Exception $e ) {
            // Fallback para error_log se falhar
            error_log( "[Manus $level] $message - Erro no logger: " . $e->getMessage() );
        }
    }
    
    // Fallback direto para error_log
    error_log( "[Manus $level] $message" );
    if ( $context ) {
        error_log( "Contexto: " . print_r( $context, true ) );
    }
    
    return false;
}

/**
 * Função de conveniência para logs de info
 */
function manus_log_info( $message, $context = null ) {
    return manus_log( 'INFO', $message, $context );
}

/**
 * Função de conveniência para logs de erro
 */
function manus_log_error( $message, $context = null ) {
    return manus_log( 'ERROR', $message, $context );
}

/**
 * Função de conveniência para logs de warning
 */
function manus_log_warning( $message, $context = null ) {
    return manus_log( 'WARNING', $message, $context );
}

/**
 * Função de conveniência para logs de sucesso
 */
function manus_log_success( $message, $context = null ) {
    return manus_log( 'SUCCESS', $message, $context );
}

/**
 * Função segura para debug
 */
function manus_debug_log( $message, $context = null ) {
    // Só loga se debug estiver ativado
    if ( ! defined( 'MANUS_DEBUG' ) || ! MANUS_DEBUG ) {
        return;
    }
    
    if ( class_exists( 'Manus_WP_Reposter_Debug' ) ) {
        $debug = Manus_WP_Reposter_Debug::instance();
        $debug->log( 'DEBUG', $message, $context );
    }
}