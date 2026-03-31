# Guia de Migração - Manus WP Reposter v2

## Visão Geral

Esta guia ajuda você a atualizar o plugin Manus WP Reposter para a versão 2, que inclui melhorias significativas na extração de conteúdo.

## O Que Mudou?

### Melhorias Principais

1. **Extração Inteligente de Conteúdo**: Novo algoritmo que reconhece conteúdo independentemente da estrutura HTML
2. **Menos Ruído**: Menus, sidebars e elementos indesejados são removidos com mais precisão
3. **Compatibilidade Expandida**: Funciona com mais tipos de sites e feeds
4. **Logging Melhorado**: Mais informações para debug e troubleshooting

### O Que Permanece Igual?

- Interface do usuário
- Configurações existentes
- Compatibilidade com WordPress
- Funcionalidade de tradução
- Processamento de imagens

## Passo a Passo da Atualização

### 1. Backup (Importante!)

Antes de qualquer coisa, faça backup:

```bash
# Backup do plugin
cp -r wp-content/plugins/manus-wp-reposter wp-content/plugins/manus-wp-reposter.backup

# Backup do banco de dados
mysqldump -u seu_usuario -p seu_banco > backup_$(date +%Y%m%d).sql
```

### 2. Desativar o Plugin

1. Acesse **Plugins** no painel do WordPress
2. Localize "Manus WP Reposter"
3. Clique em **Desativar**

### 3. Atualizar os Arquivos

**Opção A: Via FTP/SFTP**

1. Conecte ao servidor via FTP
2. Navegue para `wp-content/plugins/manus-wp-reposter/includes/`
3. Substitua `class-manus-wp-reposter-extractor.php` pela nova versão

**Opção B: Via SSH**

```bash
cd wp-content/plugins/manus-wp-reposter/includes/
# Backup do arquivo original
cp class-manus-wp-reposter-extractor.php class-manus-wp-reposter-extractor.php.v1

# Substituir pelo novo arquivo (copie o conteúdo de class-manus-wp-reposter-extractor-v2.php)
# Você pode fazer isso manualmente ou via script
```

### 4. Reativar o Plugin

1. Acesse **Plugins** no painel do WordPress
2. Localize "Manus WP Reposter"
3. Clique em **Ativar**

### 5. Testar a Nova Versão

#### Teste 1: Importação Manual

1. Acesse **Manus WP Reposter** → **Importar Manualmente**
2. Insira a URL de um feed RSS
3. Clique em **Importar**
4. Verifique se o conteúdo foi extraído corretamente

#### Teste 2: Verificar Logs

1. Acesse **Manus WP Reposter** → **Debug**
2. Procure por mensagens como:
   ```
   Manus Extractor V2: Score para nó div: 150 (text: 5000, p: 25, links: 0.15)
   ```
3. Confirme que o novo algoritmo está sendo usado

#### Teste 3: Comparar Resultados

Importe o mesmo artigo com a versão anterior (se tiver backup) e compare:
- Quantidade de conteúdo extraído
- Presença de menus/sidebars
- Qualidade das imagens

### 6. Configurar Importação Automática (Opcional)

Se você usa importação automática:

1. Acesse **Manus WP Reposter** → **Configurações**
2. Verifique se a URL do feed está configurada
3. Teste a importação automática:
   ```bash
   # Via WP-CLI
   wp manus-reposter import-daily
   ```

## Rollback (Se Necessário)

Se encontrar problemas, você pode voltar à versão anterior:

### Via Backup

```bash
# Restaurar arquivo original
cp includes/class-manus-wp-reposter-extractor.php.v1 includes/class-manus-wp-reposter-extractor.php

# Restaurar banco de dados (se necessário)
mysql -u seu_usuario -p seu_banco < backup_$(date +%Y%m%d).sql
```

### Via WordPress

1. Desative o plugin
2. Acesse **Plugins** → **Adicionar Novo**
3. Procure por "Manus WP Reposter"
4. Clique em **Instalar Agora** (isso reinstala a versão anterior)

## Troubleshooting

### Problema: Plugin não ativa após atualização

**Solução**:
1. Verifique se há erros de sintaxe PHP:
   ```bash
   php -l includes/class-manus-wp-reposter-extractor.php
   ```
2. Verifique os logs do WordPress:
   ```bash
   tail -f wp-content/debug.log
   ```
3. Se necessário, faça rollback

### Problema: Conteúdo não está sendo extraído

**Solução**:
1. Verifique os logs de debug
2. Tente com um feed diferente
3. Verifique se o site de origem permite scraping

### Problema: Importação automática não funciona

**Solução**:
1. Verifique se o cron do WordPress está ativo:
   ```bash
   wp cron test
   ```
2. Verifique as configurações de importação automática
3. Tente importar manualmente para confirmar que funciona

## Perguntas Frequentes

### P: Preciso reconfigurar o plugin?
**R**: Não. Todas as configurações anteriores são mantidas automaticamente.

### P: Meus posts importados anteriormente serão afetados?
**R**: Não. Apenas novas importações usarão o novo algoritmo.

### P: Posso usar a versão 1 e 2 simultaneamente?
**R**: Não. Apenas uma versão pode estar ativa por vez.

### P: Quanto tempo leva a atualização?
**R**: Apenas alguns minutos. Não há migração de dados.

### P: E se eu tiver customizações no código?
**R**: Se você modificou o arquivo `class-manus-wp-reposter-extractor.php`, você precisará reaplica as mudanças na nova versão. Recomendamos usar um plugin de gerenciamento de código ou controle de versão.

## Suporte

Se encontrar problemas durante a atualização:

1. **Documentação**: https://docs.manus.im/wp-reposter
2. **Forum**: https://forum.manus.im
3. **Email**: support@manus.im
4. **Chat**: https://chat.manus.im

## Próximas Etapas

Após atualizar com sucesso:

1. **Revisar Configurações**: Acesse o painel e verifique todas as opções
2. **Testar com Múltiplos Feeds**: Teste com diferentes tipos de feeds
3. **Monitorar Logs**: Acompanhe os logs para garantir funcionamento correto
4. **Ativar Importação Automática**: Se desejado, configure a importação automática

## Changelog

### v2.0.0 (Data)

**Novas Funcionalidades**:
- Algoritmo inteligente de extração de conteúdo
- Análise de profundidade de nós
- Detecção melhorada de densidade de links
- Logging expandido

**Correções**:
- Menus e sidebars não são mais incluídos no conteúdo
- Melhor reconhecimento de conteúdo em diferentes estruturas HTML
- Redução de falsos positivos na extração

**Melhorias**:
- Performance ligeiramente melhorada
- Mensagens de erro mais claras
- Documentação expandida

---

**Versão**: 2.0.0  
**Data**: 2024  
**Compatibilidade**: WordPress 5.0+, PHP 7.4+
