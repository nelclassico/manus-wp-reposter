/**
 * JavaScript da área administrativa
 */
(function($) {
    'use strict';
    
    // Objeto global do plugin
    window.manusReposter = {
        
        /**
         * Inicialização
         */
        init: function() {
            this.bindEvents();
            this.checkPage();
        },
        
        /**
         * Vincula eventos
         */
        bindEvents: function() {
            // Teste de feed
            $(document).on('submit', 'form[action*="test_feed"]', this.handleTestFeed);
            
            // Importação manual
            $(document).on('submit', 'form[action*="import_now"]', this.handleImport);
            
            // Limpeza de logs
            $(document).on('click', '.manus-clear-logs', this.handleClearLogs);
            
            // Exportação de logs
            $(document).on('click', '.manus-export-logs', this.handleExportLogs);
            
            // Toggle de detalhes
            $(document).on('click', '.manus-toggle-details', this.toggleDetails);
        },
        
        /**
         * Verifica página atual
         */
        checkPage: function() {
            var urlParams = new URLSearchParams(window.location.search);
            
            // Mostra notificação de sucesso na importação
            if (urlParams.has('imported')) {
                var imported = urlParams.get('imported');
                var skipped = urlParams.get('skipped') || 0;
                var failed = urlParams.get('failed') || 0;
                
                this.showImportResult(imported, skipped, failed);
            }
            
            // Remove parâmetros da URL
            if (urlParams.has('imported') || urlParams.has('skipped') || urlParams.has('failed')) {
                var newUrl = window.location.pathname + window.location.search
                    .replace(/&?imported=\d+/, '')
                    .replace(/&?skipped=\d+/, '')
                    .replace(/&?failed=\d+/, '')
                    .replace(/^\?&/, '?')
                    .replace(/^\?$/, '');
                
                window.history.replaceState({}, document.title, newUrl);
            }
        },
        
        /**
         * Testa feed
         */
        handleTestFeed: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('input[type="submit"]');
            var originalText = $button.val();
            var feedUrl = $form.find('input[name="test_feed_url"]').val();
            
            if (!feedUrl) {
                alert('Por favor, insira a URL do feed.');
                return;
            }
            
            // Mostra loading
            $button.val('Testando...').prop('disabled', true);
            
            $.ajax({
                url: manusReposter.ajax_url,
                type: 'POST',
                data: {
                    action: 'manus_test_feed',
                    nonce: manusReposter.nonce,
                    feed_url: feedUrl
                },
                success: function(response) {
                    if (response.success) {
                        manusReposter.showTestResult(response.data);
                    } else {
                        alert('Erro: ' + response.data);
                    }
                },
                error: function() {
                    alert('Erro ao testar feed. Verifique sua conexão.');
                },
                complete: function() {
                    $button.val(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Mostra resultado do teste
         */
        showTestResult: function(data) {
            var html = '<div class="notice notice-success">';
            html += '<p><strong>Feed testado com sucesso!</strong></p>';
            html += '<p><strong>Título:</strong> ' + data.title + '</p>';
            html += '<p><strong>Site:</strong> <a href="' + data.url + '" target="_blank">' + data.url + '</a></p>';
            html += '<p><strong>Itens no feed:</strong> ' + data.count + '</p>';
            
            if (data.sample && data.sample.length > 0) {
                html += '<p><strong>Amostra de itens:</strong></p>';
                html += '<ul>';
                $.each(data.sample, function(i, item) {
                    html += '<li><a href="' + item.url + '" target="_blank">' + item.title + '</a> (' + item.date + ')</li>';
                });
                html += '</ul>';
            }
            
            html += '</div>';
            
            $('.wrap h1').after(html);
        },
        
        /**
         * Processa importação
         */
        handleImport: function(e) {
            if (!confirm('Tem certeza que deseja iniciar a importação? Isso pode levar alguns minutos.')) {
                e.preventDefault();
                return;
            }
            
            var $form = $(this);
            var $button = $form.find('input[type="submit"]');
            var originalText = $button.val();
            
            // Mostra loading
            $button.val('Importando...').prop('disabled', true);
            
            // Adiciona spinner
            $button.before('<span class="manus-reposter-spinner"></span>');
        },
        
        /**
         * Mostra resultado da importação
         */
        showImportResult: function(imported, skipped, failed) {
            var message = 'Importação concluída! ';
            var details = [];
            var type = 'success';
            
            if (imported > 0) {
                details.push(imported + ' importados');
            }
            if (skipped > 0) {
                details.push(skipped + ' pulados');
            }
            if (failed > 0) {
                details.push(failed + ' falhas');
                type = failed > 0 ? 'warning' : 'success';
            }
            
            message += details.join(', ') + '.';
            
            var html = '<div class="notice notice-' + type + ' is-dismissible">';
            html += '<p>' + message + '</p>';
            html += '</div>';
            
            $('.wrap h1').after(html);
            
            // Auto-dismiss após 10 segundos
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 10000);
        },
        
        /**
         * Limpa logs
         */
        handleClearLogs: function(e) {
            if (!confirm('Tem certeza que deseja limpar todos os logs? Esta ação não pode ser desfeita.')) {
                e.preventDefault();
                return false;
            }
        },
        
        /**
         * Exporta logs
         */
        handleExportLogs: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text('Exportando...').prop('disabled', true);
            
            $.ajax({
                url: manusReposter.ajax_url,
                type: 'POST',
                data: {
                    action: 'manus_export_logs',
                    nonce: manusReposter.nonce
                },
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(blob) {
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'manus-reposter-logs-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                },
                error: function() {
                    alert('Erro ao exportar logs.');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Alterna detalhes
         */
        toggleDetails: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $details = $button.next('.manus-details');
            
            if ($details.is(':visible')) {
                $details.hide();
                $button.text('Mostrar detalhes');
            } else {
                $details.show();
                $button.text('Ocultar detalhes');
            }
        }
        
    };
    
    // Inicializa quando o documento está pronto
    $(document).ready(function() {
        manusReposter.init();
    });
    
})(jQuery);