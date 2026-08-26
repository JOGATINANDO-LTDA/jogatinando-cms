# Mapa do Sistema

## Raiz do projeto

- `index.php` — página inicial pública do CMS
- `blog.php` — blog público (listagem paginada e post individual com gate premium)
- `subscribe.php` — endpoint AJAX de inscrição na newsletter
- `game.php` — exibição e execução de jogos
- `catalogo.php` — catálogo público de jogos e conteúdos
- `retro.php` — catálogo retro
- `retro-play.php` — execução de jogo retro
- `retro-console.php` — navegação por console retro
- `router.php` — suporte ao roteamento de URLs amigáveis
- `install.php` — instalação e reset do sistema
- `config.php` — configuração principal do projeto
- `AGENTS.md` — guia de agentes do repositório
- `README.md` — visão geral do projeto
- `README-CMS.md` — documentação focada no CMS
- `DEPLOY.md` — instruções de publicação
- `plan.md` — plano geral do projeto, quando aplicável

## Diretório `admin/`

- `admin/index.php` — painel inicial administrativo
- `admin/login.php` — autenticação
- `admin/logout.php` — encerramento de sessão
- `admin/users.php` — gestão de usuários
- `admin/roles.php` — gestão de papéis
- `admin/settings.php` — configurações do site
- `admin/games.php` — gestão de jogos (com geração de descrição via IA)
- `admin/banners.php` — gestão de banners
- `admin/blog.php` — gestão de posts do blog (com geração de conteúdo via IA)
- `admin/testimonials.php` — gestão de depoimentos
- `admin/faq.php` — gestão de FAQ
- `admin/team.php` — gestão de equipe
- `admin/platforms.php` — gestão de plataformas unificadas
- `admin/engines.php` — gestão de engines
- `admin/consoles.php` — gestão de consoles
- `admin/retro-games.php` — gestão do catálogo retro
- `admin/social-links.php` — gestão de redes sociais
- `admin/ads.php` — gestão de slots de publicidade
- `admin/distribution.php` — hub de distribuição com métricas Chart.js
- `admin/newsletter.php` — gestão de inscritos da newsletter
- `admin/newsletter-campaigns.php` — campanhas de e-mail (envio e teste)
- `admin/donations.php` — configuração de doações (PIX/PayPal/tiers)
- `admin/ai-settings.php` — provedores de IA BYOK + dashboard de uso/custo
- `admin/bucket-sync.php` — sincronização com S3/R2
- `admin/repair.php` — diagnóstico e reparo do sistema
- `admin/setup-password.php` — fluxo de definição de senha inicial

## Diretório `includes/`

- `includes/db.php` — conexão, consultas, migrações e helpers de banco
- `includes/auth.php` — autenticação e CSRF
- `includes/functions.php` — helpers gerais, formatação e utilidades
- `includes/markdown.php` — parser Markdown para conteúdo editorial
- `includes/header.php` — cabeçalho padrão do painel
- `includes/footer.php` — rodapé padrão do painel
- `includes/footer-front.php` — rodapé público (newsletter, doações, PIX modal)
- `includes/migrations.php` — migrações do schema
- `includes/storage.php` — uploads, extração e operações de arquivo
- `includes/optimizer.php` — otimizações e rotinas de manutenção
- `includes/s3.php` — cliente S3/R2 para sync de bucket
- `includes/ai/` — clientes multi-provider de IA (OpenAI-compat, Ollama, LM Studio, Zen) com rastreamento de uso e custo

## Diretório `scripts/`

- `scripts/smoke_test.php` — suíte de testes ponta a ponta (39 verificações)
- `scripts/optimize.php` — otimização via CLI

## Diretório `assets/`

- `assets/css/style.css` — estilos do frontend
- `assets/css/admin.css` — estilos do painel administrativo
- `assets/js/main.js` — comportamento geral do frontend
- `assets/js/admin.js` — comportamento do painel
- `assets/svg/logo.svg` — marca visual do projeto

## Diretório `docker/`

- `docker/docker-compose.yml` — orquestração local
- `docker/Dockerfile` — imagem do ambiente
- `docker/apache.conf` — configuração do Apache
- `docker/mysql-init.sql` — bootstrap do MySQL no ambiente local
- `docker/README.md` — notas do ambiente Docker

## Diretório `data/`

- `data/` — armazenamento local de banco SQLite e dados persistentes relacionados
- `data/.gitkeep` — preserva o diretório no repositório

## Diretório `uploads/`

- `uploads/` — arquivos enviados pelo CMS
- subpastas internas para banners, jogos, thumbnails, avatares e blog
- `uploads/.gitkeep` — preserva o diretório no repositório

## Diretório `.vibecoding/`

- `intent/` — visão e escopo do produto
- `decisions/` — decisões e invariantes técnicas
- `architecture/` — arquitetura e mapa do sistema
- `context/` — dependências e domínio
- `learn/` — registros e aprendizado contínuo
- `memory/` — armazenamento persistente auxiliar do sistema
- `plan/` — planos executáveis para tarefas futuras