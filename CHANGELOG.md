# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

## [1.0.0] - 2025-11-27

### Adicionado

- **Importação de Feed RSS**: Funcionalidade principal para buscar e processar feeds RSS.
- **Extração de Conteúdo Completo**: Sistema inteligente de extração que tenta múltiplas estratégias para obter o conteúdo completo do artigo.
- **Atribuição de Crédito**: Bloco de atribuição automático no início de cada artigo importado.
- **Agendamento Diário**: Importação automática de um artigo por dia usando o sistema de cron do WordPress.
- **Importação Manual**: Botão na página de configurações para forçar a importação imediatamente.
- **Prevenção de Duplicidade**: Verificação automática para evitar importar o mesmo artigo duas vezes.
- **Sistema de Logs**: Registro detalhado de todas as operações para debugging.
- **Painel Administrativo**: Interface intuitiva para configurar e gerenciar o plugin.
- **Suporte a Categorias**: Opção para atribuir uma categoria padrão aos posts importados.
- **Documentação Completa**: README, exemplos de uso e guia de troubleshooting.

### Características Técnicas

- Arquitetura orientada a objetos com separação clara de responsabilidades.
- Uso de hooks e filtros do WordPress para máxima compatibilidade.
- Segurança: Validação de URLs, sanitização de dados e verificação de permissões.
- Performance: Otimização de requisições HTTP e processamento de DOM.
- Compatibilidade: WordPress 5.0+ e PHP 7.2+.

### Notas

- Este é o lançamento inicial do plugin.
- O sistema de extração de conteúdo pode precisar de ajustes para sites com estruturas HTML muito específicas.
- Recomenda-se testar a importação manualmente antes de confiar no agendamento automático.

---

## Versões Futuras (Planejado)

### [1.1.0] - Planejado

- Suporte a múltiplos feeds RSS simultâneos.
- Integração com APIs de extração de conteúdo (Mercury Web Parser, etc).
- Filtros de palavras-chave para importar apenas artigos específicos.
- Opção de agendar importações em horários customizados.
- Adição automática de tags aos posts importados.
- Suporte a imagens do artigo original.
- Dashboard com estatísticas de importação.

### [1.2.0] - Planejado

- Interface de configuração avançada com suporte a regex.
- Sistema de templates para personalizar o formato dos posts.
- Integração com redes sociais para compartilhamento automático.
- Suporte a webhooks para notificações de importação.
- API REST para gerenciar configurações programaticamente.

---

Para relatar bugs ou sugerir melhorias, entre em contato através do repositório do projeto.
