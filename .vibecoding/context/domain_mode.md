# Modelo de Domínio

## Entidades principais

### Game
Representa um jogo publicado no CMS.

- Atributos típicos: título, slug, descrição, mídia, status, engine, plataforma, console, arquivos ZIP, destaque
- Relações:
  - pertence a uma engine
  - pode estar associado a uma ou mais plataformas
  - pode estar associado a um console
  - pode aparecer no catálogo público e no catálogo retro

### Banner
Representa destaque visual exibido no site.

- Relações:
  - pertence ao conteúdo de apresentação da homepage

### BlogPost
Representa um artigo, notícia ou atualização editorial.

- Relações:
  - pode ser listado na homepage e na área de blog

### Testimonial
Representa um depoimento de comunidade, cliente ou parceiro.

- Relações:
  - pode ser exibido como prova social

### FAQItem
Representa uma pergunta e resposta frequente.

- Relações:
  - pertence à base de suporte informacional do site

### TeamMember
Representa um membro da equipe do estúdio.

- Relações:
  - compõe a apresentação institucional do Jogatinando

### User
Representa um usuário autenticado do sistema administrativo.

- Relações:
  - possui um papel ou conjunto de permissões
  - interage com a área administrativa

### Role
Representa um conjunto de permissões administrativas.

- Relações:
  - é atribuído a usuários

### Platform
Representa uma plataforma suportada pelos jogos.

- Relações:
  - pode ser vinculada a jogos e ao catálogo

### Engine
Representa a engine ou tecnologia usada pelo jogo.

- Relações:
  - organiza jogos e rotas de exibição

### Console
Representa um console ou agrupamento retro de navegação.

- Relações:
  - organiza o catálogo retro e suas páginas específicas

### RetroGame
Representa um item do catálogo retro.

- Relações:
  - pode estar vinculado a console, engine e plataforma
  - é exibido nas telas `retro.php`, `retro-play.php` e `retro-console.php`

### SiteSetting
Representa uma configuração persistente do site.

- Relações:
  - armazena valores globais do CMS
  - influencia comportamento e identidade visual

## Relações de alto nível

- Usuários administram conteúdo por meio do painel
- Papéis controlam acesso a áreas administrativas
- Jogos são organizados por engine, plataforma e console
- O catálogo retro reutiliza a base de jogos com navegação específica
- Conteúdos institucionais reforçam a presença do estúdio
- Configurações globais ajustam aparência e comportamento do site

## Regras de domínio

- O domínio deve permanecer simples e orientado a conteúdo
- As entidades devem refletir o site do estúdio e não um CMS genérico abstrato
- O modelo deve favorecer manutenção, publicação rápida e clareza operacional