# Manus WP Reposter

Um plugin WordPress poderoso que busca feeds RSS de sites externos, extrai o conteúdo completo das notícias e as publica automaticamente como posts em seu site, com devida atribuição de crédito ao site original.

## Características

- **Importação de Feed RSS**: Busca automaticamente feeds RSS de qualquer site.
- **Extração de Conteúdo Completo**: Não apenas resumos, mas o artigo completo é extraído e publicado.
- **Atribuição de Crédito**: Cada post publicado inclui um bloco de atribuição com link para o artigo original.
- **Agendamento Diário**: Importa automaticamente um artigo por dia (configurável).
- **Importação Manual**: Botão para forçar a importação imediatamente quando necessário.
- **Prevenção de Duplicidade**: Verifica se o artigo já foi publicado antes de importar.
- **Sistema de Logs**: Registra todas as operações para facilitar o debugging.
- **Interface Administrativa Intuitiva**: Painel de controle simples e direto.

## Instalação

1. Faça o download do plugin e extraia-o na pasta `/wp-content/plugins/` do seu WordPress.
2. Acesse o painel de administração do WordPress.
3. Vá para **Plugins** e ative o **Manus WP Reposter**.
4. Acesse **Configurações > WP Reposter** para configurar o plugin.

## Configuração

### Passo 1: Configurar o Feed RSS

1. Acesse **Configurações > WP Reposter**.
2. No campo **URL do Feed RSS**, insira a URL completa do feed que deseja importar (ex: `https://exemplo.com/feed/`).
3. (Opcional) Selecione uma **Categoria Padrão** para os posts importados.
4. Clique em **Salvar Configurações**.

### Passo 2: Ativar o Agendamento

O agendamento é ativado automaticamente quando o plugin é ativado. O sistema importará um artigo por dia automaticamente.

### Passo 3: Testar a Importação

1. Na página de configurações, clique em **Importar Agora** para testar a importação imediatamente.
2. Verifique os **Logs de Importação** para confirmar que tudo funcionou corretamente.
3. Acesse a página de **Posts** para verificar se o novo artigo foi publicado.

## Como Funciona

### Fluxo de Importação

1. **Busca do Feed**: O plugin busca o feed RSS configurado.
2. **Processamento de Itens**: Para cada item do feed (limitado a 1 por dia), o plugin:
   - Verifica se o artigo já foi publicado (evita duplicidade).
   - Extrai o conteúdo completo da URL do artigo.
   - Adiciona um bloco de atribuição de crédito no início do conteúdo.
   - Publica o artigo como um novo post no WordPress.

### Extração de Conteúdo

O plugin utiliza múltiplas estratégias para extrair o conteúdo completo:

1. **Seletores CSS Comuns**: Procura por classes e IDs comuns (ex: `.post-content`, `.entry-content`).
2. **Tag `<article>`**: Tenta usar a tag semântica `<article>`.
3. **Tag `<main>`**: Procura pela tag `<main>`.
4. **Classes de Conteúdo**: Busca por classes que indicam conteúdo (ex: `.post-body`).

Se nenhuma estratégia funcionar, o plugin usa o resumo do feed como fallback.

### Atribuição de Crédito

Cada artigo importado inclui um bloco de atribuição no topo com:
- Nome do site de origem
- Link para o artigo original
- Formatação clara e profissional

## Logs

O plugin mantém um registro de todas as operações. Você pode visualizar os logs na página de configurações do plugin. Os logs incluem:

- **Data/Hora**: Quando a operação ocorreu.
- **Nível**: ERROR (erro) ou INFO (informação).
- **Mensagem**: Descrição detalhada da operação.

### Limpando Logs

Para limpar todos os logs, clique em **Limpar Logs** na página de configurações. Apenas as últimas 100 mensagens são mantidas automaticamente.

## Personalizações Avançadas

### Modificar a Estratégia de Extração

Se o plugin não conseguir extrair o conteúdo corretamente de um site específico, você pode personalizar a estratégia de extração editando o arquivo `includes/class-manus-wp-reposter-content-extractor.php`.

### Usar uma Biblioteca de Extração Robusta

Para sites complexos, considere integrar uma biblioteca de extração de artigos como:

- **Readability** (JavaScript)
- **PHP-Web-Article-Extractor** (PHP)
- **Trafilatura** (Python)
- **APIs de Terceiros** (Mercury Web Parser, etc)

### Modificar o Horário de Agendamento

Por padrão, a importação ocorre diariamente. Para modificar o horário, edite o arquivo `includes/class-manus-wp-reposter.php` e altere a função `schedule_daily_import()`.

## Troubleshooting

### O plugin não importa nada

1. Verifique se a URL do feed RSS está correta.
2. Teste a URL em um leitor de RSS online (ex: Feedly).
3. Verifique os logs para mensagens de erro.
4. Certifique-se de que o WordPress pode fazer requisições HTTP (função `wp_remote_get` habilitada).

### O conteúdo não é extraído corretamente

1. O site pode ter uma estrutura HTML única que não é reconhecida pelas estratégias padrão.
2. Você pode precisar personalizar a extração para este site específico.
3. Como alternativa, o plugin usará o resumo do feed.

### Os posts não estão sendo publicados

1. Verifique se há permissões de publicação suficientes.
2. Certifique-se de que o usuário padrão (administrador) tem permissão para publicar posts.
3. Verifique os logs para mensagens de erro específicas.

## Suporte e Contribuições

Para relatar bugs, sugerir melhorias ou contribuir com o desenvolvimento, visite o repositório do projeto no GitHub.

## Licença

Este plugin é licenciado sob a GPL2. Veja o arquivo LICENSE para mais detalhes.

## Autor

Desenvolvido por **Manus AI** - https://manus.im

---

**Versão**: 1.0.0  
**Compatibilidade**: WordPress 5.0+  
**Requisitos**: PHP 7.2+
