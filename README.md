# Jogatinando CMS

CMS em **PHP 8.2 + SQLite** para gerenciar o site do estúdio Jogatinando — portfólio de jogos com painel administrativo completo.

## Funcionalidades

- **Dashboard** com visão geral e acesso rápido
- **Banners** — carrossel hero da página principal com imagens, CTAs e tags de engine
- **Jogos** — CRUD completo com upload de ZIP (HTML exportado) e thumbnail; extração automática do ZIP para jogar no navegador via iframe
- **Blog** — posts com slug, thumbnail e suporte a URL externa
- **Depoimentos** — gerenciamento de avaliações de clientes
- **FAQ** — perguntas frequentes com ordenação
- **Equipe** — membros com bio, avatar e links sociais (YouTube, Twitch, LinkedIn)
- **Configurações** — nome do site, hero, contato, redes sociais, footer
- **Autenticação** — login com session + proteção CSRF
- **Uploads** — imagens (JPG, PNG, GIF, WebP) e ZIPs de jogos (até 100MB)

## Tecnologias

| Componente | Versão |
|---|---|
| PHP | 8.2+ |
| Banco de dados | SQLite 3 (PDO, WAL mode, foreign keys ON) |
| Servidor web | Apache 2.4 (mod_rewrite) |
| Containerização | Docker + Docker Compose |

## Estrutura do Projeto

```
jogatinando-cms/
├── config.php              ← Configurações, credenciais e auto-load
├── install.php             ← Setup wizard (cria DB + seed data)
├── game.php                ← Player de jogos (extrai ZIP e serve em iframe)
├── index.php               ← Frontend (single-page com seções)
├── .htaccess               ← URL rewriting (Apache)
├── README.md               ← Este arquivo
├── .dockerignore           ← Arquivos ignorados no build Docker
│
├── admin/                  ← Painel administrativo (requer login)
│   ├── index.php           ← Dashboard com estatísticas
│   ├── login.php           ← Tela de login
│   ├── logout.php          ← Logout
│   ├── banners.php         ← Gerenciar banners
│   ├── games.php           ← Gerenciar jogos + upload ZIP
│   ├── blog.php            ← Gerenciar posts do blog
│   ├── testimonials.php    ← Gerenciar depoimentos
│   ├── faq.php             ← Gerenciar FAQ
│   ├── team.php            ← Gerenciar equipe
│   └── settings.php        ← Configurações do site
│
├── includes/               ← Biblioteca compartilhada
│   ├── db.php              ← PDO SQLite: conexão, schema, CRUD helpers
│   ├── auth.php            ← Login, logout, CSRF, verificação de sessão
│   ├── functions.php       ← Helpers: escape, slug, upload, tempo, etc
│   ├── header.php          ← Layout admin: <head>, sidebar, header
│   └── footer.php          ← Fecha layout admin + carrega JS
│
├── assets/
│   ├── css/style.css       ← Frontend: tema cosmic/medieval, OKLCH tokens
│   ├── css/admin.css       ← Admin: tema dark + gold, design system completo
│   ├── js/main.js          ← Frontend: estrelas, partículas, carrosséis, mobile nav
│   └── js/admin.js         ← Admin: auto-hide flash, preview upload, auto-slug
│
├── docker/
│   ├── Dockerfile          ← PHP 8.2 + Apache + SQLite + ZipArchive
│   ├── docker-compose.yml  ← Orquestração com bind mount + volumes persistentes
│   └── apache.conf         ← Configuração do Apache VirtualHost
│
├── data/                   ← Banco SQLite (jogatinando.db) — auto-created
└── uploads/                ← Arquivos enviados — auto-created
    ├── banners/            ← Imagens de banner
    ├── games/              ← ZIPs dos jogos + pastas extraídas
    ├── thumbnails/         ← Thumbnails de jogos
    ├── avatars/            ← Avatares de equipe e depoimentos
    └── blog/               ← Imagens de posts
```

## Quick Start — Docker

### Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) + [Docker Compose](https://docs.docker.com/compose/install/)

### Rodar

```bash
# Build e start (com bind mount para hot-reload)
docker compose -f docker/docker-compose.yml up -d --build

# Ver logs
docker compose -f docker/docker-compose.yml logs -f

# Parar
docker compose -f docker/docker-compose.yml down
```

O CMS estará em **http://localhost:8080**.

### Primeiro Acesso

1. Acesse **http://localhost:8080/install.php** e clique em "Instalar CMS"
2. Login em **http://localhost:8080/admin/login.php**
   - Usuário: `admin`
   - Senha: `jogatinando2024`
3. **Troque a senha** após o primeiro login (veja [Segurança](#segurança))

## Desenvolvimento Local

O `docker-compose.yml` usa **bind mount** (`..:/var/www/html`) por padrão — qualquer alteração em `.php`, `.css` ou `.js` reflete instantaneamente sem rebuild.

Os volumes nomeados `cms-data` e `cms-uploads` persistem o banco SQLite e os uploads por cima do bind mount nos subdiretórios `data/` e `uploads/`.

### Rebuild sem cache

```bash
docker compose -p jogatinando-cms -f docker/docker-compose.yml build --no-cache
docker compose -p jogatinando-cms -f docker/docker-compose.yml up -d --force-recreate
```

## Instalação Manual

### Pré-requisitos

- PHP 8.2+ com extensões: `pdo_sqlite`, `sqlite3`, `zip`, `mbstring`
- Apache com `mod_rewrite` (ou Nginx)

### Passos

```bash
# 1. Clone ou copie os arquivos para o document root
git clone <repo> /var/www/html/jogatinando-cms

# 2. Permissões
chmod 755 /var/www/html/jogatinando-cms/data
chmod 755 /var/www/html/jogatinando-cms/uploads
chown -R www-data:www-data /var/www/html/jogatinando-cms/data
chown -R www-data:www-data /var/www/html/jogatinando-cms/uploads

# 3. Acesse o instalador
#    http://seusite.com/install.php

# 4. Login
#    http://seusite.com/admin/login.php
```

### Nginx

Se usar Nginx ao invés de Apache:

```nginx
server {
    listen 80;
    server_name seusite.com;
    root /var/www/html/jogatinando-cms;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /jogar/ {
        rewrite ^/jogar/([0-9]+)$ /game.php?id=$1 last;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Segurança

### Trocar Senha Admin

Edite `config.php` e altere o hash:

```php
define('ADMIN_PASSWORD_HASH', password_hash('sua-nova-senha', PASSWORD_DEFAULT));
```

### Variável de Ambiente

Defina `SITE_URL` no ambiente para produção:

```bash
# Docker Compose
environment:
  - SITE_URL=https://seusite.com

# Ou no .env do Docker
SITE_URL=https://seusite.com
```

### Proteger install.php

Após a instalação, remova ou restrinja o acesso:

```bash
rm install.php
# ou
chmod 000 install.php
```

> **Atenção:** `install.php` expõe um endpoint de **reset** que deleta o banco e re-seeda dados padrão.

## Upload de Jogos

1. No admin, vá em **Jogos → Novo Jogo**
2. Preencha título, engine e descrição
3. Faça upload do **ZIP do jogo** (export HTML da engine)
4. O ZIP deve conter um `index.html` na raiz ou em uma subpasta
5. O CMS extrai automaticamente o ZIP ao acessar o jogo via `/jogar/<id>`

### Engines Suportadas

GDevelop, Godot, RPG Maker, Unity, Unreal Engine, Construct, Defold, Game Maker, Ren'py, Pixel Game Maker MV, RPG Paper Maker e outras que exportem para HTML.

## Banco de Dados

SQLite em `data/jogatinando.db` com as seguintes tabelas:

| Tabela | Descrição |
|---|---|
| `users` | Usuários admin (autenticação) |
| `banners` | Slides do carrossel hero (título, imagem, CTA, engine tag) |
| `games` | Jogos (título, engine, descrição, thumbnail, ZIP, destaque) |
| `blog_posts` | Posts do blog (slug, conteúdo, thumbnail, URL externa) |
| `testimonials` | Depoimentos (nome, cargo, citação, avatar) |
| `faq_items` | Perguntas frequentes (pergunta, resposta, ordenação) |
| `team_members` | Membros da equipe (nome, cargo, bio, avatar, redes sociais) |
| `site_settings` | Configurações gerais (chave/valor) |

### Backup

```bash
# Local
cp data/jogatinando.db backup-$(date +%Y%m%d).db

# Docker
docker cp jogatinando-cms:/var/www/html/data/jogatinando.db ./backup.db
```

## Frontend

### Layout

- **Navbar fixa** com menu responsivo (hamburger no mobile)
- **Hero** com dois modos: estático (crest + texto) ou carrossel de banners
- **Portfólio** com ring carousel de jogos (5 itens desktop, 3 tablet, 3 mobile, 1 phone pequeno)
- **Blog**, **Depoimentos**, **FAQ**, **Equipe** e **Contato** em seções
- **Footer** com navegação, engines e links sociais

### Design

- **Frontend**: tema cosmic/medieval com OKLCH, Cinzel + Inter + JetBrains Mono, partículas animadas e ornamentos decorativos
- **Admin**: tema dark com gold accents, sidebar colapsável, design system completo com tokens CSS

### URLs

- `.htaccess` rewrites `/jogar/<id>` → `game.php?id=$1`
- Front controller: tudo que não é arquivo/diretório → `index.php`

## Licença

Uso interno — Jogatinando Estúdio.
