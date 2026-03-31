# Manus WP Reposter - Versão 2 Atualizada

## 🎯 O Que Foi Melhorado?

Este é o **Manus WP Reposter v2** com **extração inteligente de conteúdo**. O plugin agora consegue:

✅ **Reconhecer conteúdo automaticamente** - Não depende mais de tags específicas  
✅ **Remover menus e sidebars** - Sem deixar rastros de navegação  
✅ **Funcionar com qualquer estrutura HTML** - Blogs, notícias, portais, etc.  
✅ **Evitar apagar conteúdo legítimo** - Algoritmo mais preciso  
✅ **Processar múltiplos feeds** - Sem perder qualidade  

## 📋 Problemas Resolvidos

| Problema | Solução |
|----------|---------|
| Plugin só reconhecia tags específicas | Novo algoritmo analisa múltiplos fatores |
| Menus e navegação apareciam no conteúdo | Detecção de densidade de links e profundidade |
| Conteúdo era apagado em feeds diferentes | Scoring mais sofisticado e adaptável |
| Sidebars e widgets eram incluídos | Lista expandida de elementos a remover |
| Dificuldade em debug | Logging melhorado com métricas detalhadas |

## 🚀 Como Usar

### Instalação Rápida

1. **Extraia o plugin** na pasta `wp-content/plugins/`
2. **Ative** no painel do WordPress
3. **Configure** a URL do feed RSS
4. **Importe** manualmente ou configure importação automática

### Primeiro Teste

```
1. Vá para: Manus WP Reposter → Importar Manualmente
2. Cole a URL de um feed RSS
3. Clique em "Importar"
4. Verifique se o conteúdo foi extraído corretamente
```

### Verificar Logs

```
1. Vá para: Manus WP Reposter → Debug
2. Procure por mensagens com "V2" para confirmar nova versão
3. Veja o score dos nós candidatos
```

## 🔧 Configuração Avançada

### Adicionar Elementos Customizados para Remover

Se você tem um site específico com elementos indesejados, você pode adicionar à lista:

```php
// No arquivo functions.php do seu tema
add_filter( 'manus_reposter_elements_to_remove', function( $elements ) {
    $elements['classes'][] = 'meu-menu-customizado';
    $elements['ids'][] = 'meu-sidebar-id';
    return $elements;
} );
```

### Ajustar Threshold de Score

Se o plugin está extraindo muito ou pouco conteúdo, você pode ajustar o threshold:

```php
// Edite a linha no método extract_by_intelligent_algorithm()
if ( $best_node && $best_score > 10 ) { // Aumente para 20, 30, etc.
    return $dom->saveHTML( $best_node );
}
```

## 📊 Como Funciona o Novo Algoritmo

O plugin analisa cada elemento da página e calcula um "score" baseado em:

```
Score = 
  + (tamanho_texto / 100)
  + (parágrafos * 10)
  + (headings * 5)
  + (imagens * 2)
  + (listas * 3)
  - (densidade_links * 100) se > 30%
  - 50 se profundidade > 8 níveis
  + 30 se tag semântica (article, main)
  + 25 se classe de conteúdo
  - 100 se classe de não-conteúdo (sidebar, menu, etc.)
```

**Resultado**: O elemento com maior score é selecionado como conteúdo principal.

## 🐛 Troubleshooting

### Conteúdo não está sendo extraído

**Causa**: O site pode ter estrutura HTML muito diferente.

**Solução**:
1. Verifique os logs para ver o score dos nós
2. Se o melhor score for < 10, o site precisa de configuração customizada
3. Adicione classes/IDs específicas do site à lista de remoção

### Menus ainda aparecem no conteúdo

**Causa**: O site usa classes/IDs não reconhecidos.

**Solução**:
1. Inspecione o HTML do menu (F12 no navegador)
2. Identifique a classe ou ID
3. Adicione à lista de remoção

### Conteúdo legítimo está sendo removido

**Causa**: Elemento contém classe/ID na lista de remoção.

**Solução**:
1. Verifique qual elemento está sendo removido
2. Se for legítimo, remova da lista de remoção
3. Ou use um seletor mais específico

## 📚 Arquivos Incluídos

- `class-manus-wp-reposter-extractor.php` - **NOVO** Extrator inteligente
- `class-manus-wp-reposter-extractor.php.backup` - Versão anterior (para rollback)
- `MELHORIAS-V2.md` - Documentação técnica detalhada
- `GUIA-MIGRACAO.md` - Guia passo-a-passo de atualização
- `README-ATUALIZACAO.md` - Este arquivo

## ✨ Recursos Principais

### 1. Extração Inteligente
- Analisa estrutura HTML automaticamente
- Funciona com qualquer layout de site
- Aprende padrões de conteúdo

### 2. Remoção de Ruído
- Remove menus, sidebars, rodapés
- Detecta e remove anúncios
- Elimina elementos flutuantes

### 3. Preservação de Conteúdo
- Mantém parágrafos, headings, listas
- Preserva imagens e links relevantes
- Formata conteúdo adequadamente

### 4. Logging e Debug
- Registra score de cada nó
- Mostra métricas de conteúdo
- Facilita troubleshooting

## 🔄 Atualizar de Versão Anterior

Se você tinha a v1 instalada:

1. **Backup** do arquivo original
2. **Desative** o plugin
3. **Substitua** `class-manus-wp-reposter-extractor.php`
4. **Reative** o plugin
5. **Teste** com um feed

Veja `GUIA-MIGRACAO.md` para instruções detalhadas.

## 📞 Suporte

- **Documentação**: https://docs.manus.im/wp-reposter
- **Forum**: https://forum.manus.im
- **Email**: support@manus.im
- **Issues**: https://github.com/manus/wp-reposter/issues

## 📝 Changelog

### v2.0.0
- ✨ Novo algoritmo inteligente de extração
- 🎯 Análise de profundidade de nós
- 🔗 Detecção melhorada de densidade de links
- 📊 Logging expandido com métricas
- 🛡️ Remoção expandida de elementos
- 🚀 Performance ligeiramente melhorada

### v1.0.0 (Anterior)
- Extração por seletores CSS
- Remoção básica de elementos
- Logging simples

## 📄 Licença

GPL v2 ou posterior

## 👥 Créditos

Desenvolvido pela **Manus Team**  
Baseado em algoritmos de extração de conteúdo tipo Readability

---

**Versão**: 2.0.0  
**Data**: 2024  
**Status**: Estável ✅  
**Compatibilidade**: WordPress 5.0+, PHP 7.4+

**Aproveite a nova versão! 🎉**
