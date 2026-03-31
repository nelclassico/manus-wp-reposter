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
            this.initFeedAnalysis(); // NOVO: Inicializa análise de feed
            this.initTrustedSources(); // NOVO: Inicializa fontes confiáveis
        },
        
        /**
         * Vincula eventos
         */
        bindEvents: function() {
            // Teste de feed
            $(document).on('submit', 'form[action*="test_feed"]', this.handleTestFeed.bind(this));
            
            // Importação manual
            $(document).on('submit', 'form[action*="import_now"]', this.handleImport.bind(this));
            
            // Limpeza de logs
            $(document).on('click', '.manus-clear-logs', this.handleClearLogs.bind(this));
            
            // Exportação de logs
            $(document).on('click', '.manus-export-logs', this.handleExportLogs.bind(this));
            
            // Toggle de detalhes
            $(document).on('click', '.manus-toggle-details', this.toggleDetails.bind(this));
        },
        
        /**
         * NOVO: Inicializa funcionalidade de análise de feed
         */
        initFeedAnalysis: function() {
            $('#manus-analyze-feed-button').on('click', this.handleFeedAnalysis.bind(this));
        },

        /**
         * NOVO: Inicializa funcionalidade de fontes confiáveis
         */
        initTrustedSources: function() {
            // Adicionar nova fonte
            window.manusAddTrustedSource = this.addTrustedSource.bind(this);
            
            // Remover fonte
            $(document).on('click', '.manus-remove-source', this.removeTrustedSource.bind(this));
            
            // Atualizar quando campos mudarem
            $(document).on('input', '.manus-source-domain, .manus-source-name', this.updateTrustedSourcesInput.bind(this));
        },

        handleFeedAnalysis: function(e) {
            e.preventDefault();
            
            var self = this;
            var $button = $(e.currentTarget);
            var $input = $('#manus-feed-url-analysis');
            var $result = $('#manus-feed-analysis-result');
            var feedUrl = $input.val().trim();
            
            if (!feedUrl) {
                alert(self.getText('enter_feed_url', 'Por favor, insira uma URL de feed.'));
                return;
            }
            
            $button.prop('disabled', true).text('Analisando...');
            $result.html('<div class="notice notice-info"><p>🔍 Analisando feed...</p></div>');
            
            // Log para debug
            console.log('🔍 MANUS: Enviando requisição...');
            console.log('🔍 MANUS: Nonce:', manusReposterData.nonce);
            console.log('🔍 MANUS: URL:', manusReposterData.ajax_url);
            
            // FAZER A REQUISIÇÃO
            $.ajax({
                url: manusReposterData.ajax_url,
                type: 'POST',
                data: {
                    action: 'manus_analyze_feed',
                    feed_url: feedUrl,
                    nonce: manusReposterData.nonce
                },
                dataType: 'json',
                timeout: 30000, // 30 segundos de timeout
                success: function(response) {
                    console.log('✅ MANUS: Resposta recebida:', response);
                    
                    if (response && response.success && response.data) {
                        self.displayAnalysisResult(response.data);
                    } 
                    else if (response && response.data) {
                        self.displayErrorResult(response.data);
                    }
                    else {
                        self.displayErrorResult({
                            message: 'Resposta inesperada do servidor',
                            debug: [JSON.stringify(response)]
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ MANUS: Erro AJAX:', status, error);
                    console.error('❌ MANUS: Resposta:', xhr.responseText);
                    
                    self.displayErrorResult({
                        message: 'Erro na requisição: ' + error,
                        debug: [
                            'Status: ' + status,
                            'URL: ' + manusReposterData.ajax_url,
                            'Resposta: ' + (xhr.responseText ? xhr.responseText.substring(0, 200) : 'vazia')
                        ]
                    });
                },
                complete: function() {
                    $button.prop('disabled', false).text(self.getText('analyze_feed', 'Analisar Feed'));
                }
            });
        },

        /**
         * Exibe resultado da análise
         */
        displayAnalysisResult: function(data) {
            var $result = $('#manus-feed-analysis-result');
            
            var html = '<div class="manus-feed-analysis-box" style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-top: 10px;">';
            html += '<h4 style="margin-top: 0; color: #46b450;">✅ Análise Concluída</h4>';
            
            // Informações principais
            html += '<div style="margin-bottom: 15px;">';
            html += '<p><strong>Título do Feed:</strong> ' + (data.feed_title || 'Desconhecido') + '</p>';
            html += '<p><strong>Domínio:</strong> ' + data.domain + '</p>';
            html += '<p><strong>Itens encontrados:</strong> ' + data.item_count + '</p>';
            html += '<p><strong>Última atualização:</strong> ' + data.last_update + '</p>';
            html += '</div>';
            
            // Debug messages
            if (data.debug && data.debug.length > 0) {
                html += '<div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
                html += '<p><strong>🔍 Log de Debug:</strong></p>';
                html += '<pre style="background: #f8f8f8; padding: 10px; font-size: 11px; max-height: 300px; overflow: auto; white-space: pre-wrap;">';
                for (var i = 0; i < data.debug.length; i++) {
                    var msg = data.debug[i];
                    var color = msg.includes('❌') ? '#dc3232' : (msg.includes('✅') ? '#46b450' : '#666');
                    html += '<span style="color: ' + color + ';">' + msg + '</span>\n';
                }
                html += '</pre>';
                html += '</div>';
            }
            
            html += '</div>';
            
            $result.html(html);
        },


       /**
        * Exibe erro com debug
        */
       displayErrorResult: function(data) {
           var $result = $('#manus-feed-analysis-result');
           
           // Garantir que data é um objeto
           data = data || {};
           
           var html = '<div class="notice notice-error" style="padding: 15px;">';
           html += '<p><strong>❌ ERRO NA ANÁLISE</strong></p>';
           
           if (data.message) {
               html += '<p>' + data.message + '</p>';
           } else {
               html += '<p>Erro desconhecido ao analisar o feed.</p>';
           }
           
           if (data.debug && data.debug.length > 0) {
               html += '<div style="margin-top: 10px;">';
               html += '<p><strong>🔍 Log de Debug:</strong></p>';
               html += '<pre style="background: #f8f8f8; padding: 10px; font-size: 11px; max-height: 300px; overflow: auto; white-space: pre-wrap;">';
               for (var j = 0; j < data.debug.length; j++) {
                   html += data.debug[j] + '\n';
               }
               html += '</pre>';
               html += '</div>';
           } else {
               html += '<div style="margin-top: 10px;">';
               html += '<p><strong>🔍 Resposta completa do servidor:</strong></p>';
               html += '<pre style="background: #f8f8f8; padding: 10px; font-size: 11px; max-height: 300px; overflow: auto; white-space: pre-wrap;">';
               html += JSON.stringify(data, null, 2);
               html += '</pre>';
               html += '</div>';
           }
           
           html += '</div>';
           
           $result.html(html);
       },

        /**
         * NOVO: Adiciona fonte confiável
         */
        addTrustedSource: function() {
            var $list = $('#manus-trusted-sources-list');
            
            var html = '<div style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
            html += '<input type="text" class="manus-source-domain" placeholder="exemplo.com" style="flex: 1;">';
            html += '<input type="text" class="manus-source-name" placeholder="Nome da Fonte" style="flex: 1;">';
            html += '<button type="button" class="button button-secondary manus-remove-source">' + this.getText('remove', 'Remover') + '</button>';
            html += '</div>';
            
            $list.append(html);
            this.updateTrustedSourcesInput();
        },

        /**
         * NOVO: Remove fonte confiável
         */
        removeTrustedSource: function(e) {
            $(e.currentTarget).closest('div').remove();
            this.updateTrustedSourcesInput();
        },

        /**
         * NOVO: Atualiza input hidden com fontes confiáveis
         */
        updateTrustedSourcesInput: function() {
            var sources = [];
            
            $('#manus-trusted-sources-list > div').each(function() {
                var $domain = $(this).find('.manus-source-domain');
                var $name = $(this).find('.manus-source-name');
                
                if ($domain.val().trim() && $name.val().trim()) {
                    sources.push({
                        domain: $domain.val().trim(),
                        name: $name.val().trim()
                    });
                }
            });
            
            $('#manus-trusted-sources-input').val(JSON.stringify(sources));
        },

        /**
         * Helper para obter textos (fallback caso as strings não estejam definidas)
         */
        getText: function(key, defaultValue) {
            return (manusReposterData.strings && manusReposterData.strings[key]) ? manusReposterData.strings[key] : defaultValue;
        },
        
        /**
         * Verifica página atual (código original)
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
         * Testa feed (código original)
         */
        handleTestFeed: function(e) {
            e.preventDefault();
            
            var $form = $(e.currentTarget);
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
                url: manusReposterData.ajax_url,
                type: 'POST',
                data: {
                    action: 'manus_test_feed',
                    nonce: manusReposterData.nonce,
                    feed_url: feedUrl
                },
                success: function(response) {
                    if (response.success) {
                        this.showTestResult(response.data);
                    } else {
                        alert('Erro: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    alert('Erro ao testar feed. Verifique sua conexão.');
                },
                complete: function() {
                    $button.val(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Mostra resultado do teste (código original)
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
         * Processa importação (código original)
         */
        handleImport: function(e) {
            if (!confirm('Tem certeza que deseja iniciar a importação? Isso pode levar alguns minutos.')) {
                e.preventDefault();
                return;
            }
            
            var $form = $(e.currentTarget);
            var $button = $form.find('input[type="submit"]');
            var originalText = $button.val();
            
            // Mostra loading
            $button.val('Importando...').prop('disabled', true);
            
            // Adiciona spinner
            $button.before('<span class="manus-reposter-spinner"></span>');
        },
        
        /**
         * Mostra resultado da importação (código original)
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
         * Limpa logs (código original)
         */
        handleClearLogs: function(e) {
            if (!confirm('Tem certeza que deseja limpar todos os logs? Esta ação não pode ser desfeita.')) {
                e.preventDefault();
                return false;
            }
        },
        
        /**
         * Exporta logs (código original)
         */
        handleExportLogs: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var originalText = $button.text();
            
            $button.text('Exportando...').prop('disabled', true);
            
            $.ajax({
                url: manusReposterData.ajax_url,
                type: 'POST',
                data: {
                    action: 'manus_export_logs',
                    nonce: manusReposterData.nonce
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
         * Alterna detalhes (código original)
         */
        toggleDetails: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
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