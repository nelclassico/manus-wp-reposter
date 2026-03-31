# Exemplos de Uso - Manus WP Reposter

Este documento fornece exemplos práticos de como usar o plugin Manus WP Reposter.

## Exemplo 1: Importar Notícias de um Blog de Tecnologia

### Configuração

1. Acesse **Configurações > WP Reposter**.
2. Cole a URL do feed RSS do blog de tecnologia: `https://techblog.exemplo.com/feed/`
3. Selecione a categoria **Tecnologia** como categoria padrão.
4. Clique em **Salvar Configurações**.

### Resultado

A cada dia, um novo artigo do blog de tecnologia será importado automaticamente e publicado em seu site com:
- Título original do artigo
- Conteúdo completo do artigo
- Link para o artigo original no topo

## Exemplo 2: Importação Manual para Testes

### Passo a Passo

1. Configure a URL do feed RSS (conforme o Exemplo 1).
2. Na página de configurações, clique em **Importar Agora**.
3. Aguarde alguns segundos enquanto o plugin busca e processa o artigo.
4. Verifique os **Logs de Importação** para confirmar o sucesso.
5. Acesse **Posts** para visualizar o artigo importado.

### Verificação

Você deve ver:
- Um novo post com o título do artigo original
- Um bloco de atribuição no topo com o link para o artigo original
- O conteúdo completo do artigo

## Exemplo 3: Monitorar Múltiplos Feeds (Personalizações)

Por padrão, o plugin importa de um único feed. Se você deseja importar de múltiplos feeds, você pode:

### Opção 1: Usar um Agregador de Feeds

Crie um feed agregador que combine múltiplos feeds em um único feed RSS, e configure o plugin para usar esse feed agregador.

### Opção 2: Clonar o Plugin

Crie múltiplas instâncias do plugin (com nomes diferentes) para importar de diferentes feeds simultaneamente.

### Opção 3: Personalizar o Código

Edite o arquivo `includes/class-manus-wp-reposter-importer.php` para suportar múltiplos feeds na configuração.

## Exemplo 4: Personalizar a Atribuição de Crédito

Se você deseja modificar como o crédito é atribuído, edite o arquivo `includes/class-manus-wp-reposter-importer.php` e procure pela função `add_credit_attribution()`.

### Exemplo de Customização

```php
// Padrão
$credit_html = '<div style="border-left: 4px solid #007cba; padding: 15px; background-color: #f5f5f5; margin-bottom: 20px;">';
$credit_html .= '<p style="margin: 0; font-weight: bold;">Artigo Original:</p>';
$credit_html .= '<p style="margin: 5px 0 0 0;">';
$credit_html .= '<strong>' . esc_html( $source_name ) . '</strong><br>';
$credit_html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $url ) . '</a>';
$credit_html .= '</p>';
$credit_html .= '</div>';

// Customizado (exemplo: usando um blockquote)
$credit_html = '<blockquote style="border-left: 4px solid #007cba; padding: 15px; margin: 20px 0;">';
$credit_html .= '<p>Este artigo foi originalmente publicado em <strong>' . esc_html( $source_name ) . '</strong>.</p>';
$credit_html .= '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">Leia o artigo original</a></p>';
$credit_html .= '</blockquote>';
```

## Exemplo 5: Resolver Problemas de Extração de Conteúdo

Se o plugin não conseguir extrair o conteúdo completo de um site específico:

### Passo 1: Verificar os Logs

Verifique se há mensagens de erro nos **Logs de Importação**.

### Passo 2: Testar a URL Manualmente

1. Acesse o site do artigo diretamente no navegador.
2. Verifique a estrutura HTML (clique com botão direito > Inspecionar).
3. Procure por elementos como `<article>`, `<main>`, ou `<div class="post-content">`.

### Passo 3: Adicionar um Seletor Customizado

Se você encontrar um padrão específico, adicione-o à função `extract_by_common_selectors()` no arquivo `includes/class-manus-wp-reposter-content-extractor.php`:

```php
$selectors = array(
    "//div[@class='post-content']",
    "//div[@class='entry-content']",
    "//div[@class='article-content']",
    "//div[@class='seu-seletor-customizado']", // Adicione aqui
    // ... outros seletores
);
```

## Exemplo 6: Usar com Serviços de Terceiros

Para sites muito complexos, considere usar uma API de extração de conteúdo:

### Mercury Web Parser

```php
// Exemplo de integração (não incluído no plugin por padrão)
$api_key = 'sua-chave-api';
$url = 'https://exemplo.com/artigo';

$response = wp_remote_get( "https://mercury.postlight.com/api/v1/parse?url=" . urlencode( $url ), array(
    'headers' => array(
        'x-api-key' => $api_key,
    ),
) );

$data = json_decode( wp_remote_retrieve_body( $response ), true );
$content = $data['content'];
```

## Exemplo 7: Agendar Importações em Horários Específicos

Por padrão, a importação ocorre diariamente. Para modificar o horário:

Edite o arquivo `includes/class-manus-wp-reposter.php`:

```php
public function schedule_daily_import() {
    if ( ! wp_next_scheduled( 'manus_wp_reposter_daily_import' ) ) {
        // Agenda para executar diariamente às 14:00 (2:00 PM)
        $time = strtotime( 'tomorrow 14:00' );
        wp_schedule_event( $time, 'daily', 'manus_wp_reposter_daily_import' );
    }
}
```

## Exemplo 8: Adicionar Tags Automáticas

Se você deseja adicionar tags automáticas aos posts importados:

Edite o arquivo `includes/class-manus-wp-reposter-importer.php` e modifique a função `publish_post()`:

```php
private function publish_post( $title, $content, $original_url, $original_date = null ) {
    // ... código anterior ...

    $post_id = wp_insert_post( $post_data );

    if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
        // Adiciona tags automáticas
        wp_set_post_tags( $post_id, array( 'Importado', 'Feed RSS' ), true );
        
        // ... resto do código ...
    }

    return $post_id;
}
```

---

Para mais informações, consulte o arquivo README.md.
