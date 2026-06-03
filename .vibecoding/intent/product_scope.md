# Escopo do Produto

## Incluído

### CMS principal
- Painel administrativo para gestão de conteúdo
- Publicação e edição de páginas institucionais
- Gestão de configurações do site
- Organização de mídia enviada ao sistema

### Gestão de jogos
- Cadastro, edição e exclusão de jogos
- Organização por engine, plataforma e console
- Upload de arquivos ZIP de jogos
- Extração automática de conteúdo para execução
- Páginas públicas para visualização e acesso aos jogos

### Blog e conteúdo editorial
- Cadastro de posts de blog
- Publicação de novidades e notícias
- Organização de conteúdo textual para SEO e comunidade

### Elementos institucionais
- Depoimentos
- FAQ
- Membros da equipe
- Banners e destaques da homepage

### Catálogo retro
- Catálogo de jogos retro
- Gestão de plataformas, engines e consoles
- Telas específicas para navegação retro (`retro.php`, `retro-play.php`, `retro-console.php`)

### Usuários e segurança
- Gestão de usuários
- Gestão de papéis e permissões
- Autenticação baseada em sessão
- Proteção CSRF em formulários administrativos

### Armazenamento e manutenção
- Suporte a SQLite e MySQL/MariaDB via PDO
- Migrações automáticas de schema
- Otimização e rotinas internas de manutenção
- Estrutura preparada para uso com Docker

## Excluído

- API pública ou API REST
- SPA ou frontend com framework JavaScript
- Integração com Composer
- Uso de npm, bundlers ou pipeline de build
- Microserviços
- Sistema de pagamentos
- Marketplace
- Funcionalidades de comunidade em tempo real
- Autenticação social
- Aplicativos móveis nativos
- Camadas arquiteturais desnecessárias para o escopo atual

## Limite funcional

O produto deve permanecer como um CMS leve, orientado a conteúdo e gestão interna do estúdio, com foco em operação simples, manutenção previsível e compatibilidade com o ambiente atual do projeto.