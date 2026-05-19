# Jogatinando CMS

CMS em PHP + SQLite para gerenciar o site Jogatinando — portfólio de jogos com painel administrativo.

## Requisitos

- PHP 7.4+ com extensões: `pdo_sqlite`, `zip`, `mbstring`
- Servidor web: Apache (com mod_rewrite) ou Nginx

## Instalação

1. **Upload dos arquivos** para o servidor web
2. **Permissões**: garantir que a pasta `data/` e `uploads/` sejam graváveis:
   ```bash
   chmod 755 data uploads uploads/*
   ```
3. **Acessar o instalador**: abrir `https://seusite.com/install.php` no navegador
4. **Clicar "Instalar CMS"** — cria o banco SQLite e insere dados padrão
5. **Login no admin**: `https://seusite.com/admin/login.php`
   - Usuário: `admin`
   - Senha: `jogatinando2024`
   - **Troque a senha** no `config.php` após o primeiro login

## Estrutura

```
├── index.php              ← Site dinâmico (frontend)
├── game.php               ← Player de jogos (HTML exportado em ZIP)
├── install.php            ← Setup wizard (rodar uma vez)
├── config.php             ← Configurações + credenciais admin
├── .htaccess              ← URL rewriting (Apache)
├── admin/                 ← Painel administrativo
│   ├── index.php          ← Dashboard
│   ├── login.php          ← Login
│   ├── banners.php        ← Gerenciar banners do carousel
│   ├── games.php          ← Gerenciar jogos + upload ZIP
│   ├── blog.php           ← Gerenciar posts do blog
│   ├── testimonials.php   ← Gerenciar depoimentos
│   ├── faq.php            ← Gerenciar FAQ
│   ├── team.php           ← Gerenciar equipe
│   └── settings.php       ← Configurações do site
├── includes/              ← Helpers (DB, auth, funções)
├── assets/                ← CSS/JS do admin
├── uploads/               ← Arquivos enviados
│   ├── banners/           ← Imagens de banner
│   ├── games/             ← ZIPs dos jogos + extraídos
│   ├── thumbnails/        ← Thumbnails de jogos
│   ├── avatars/           ← Avatares de equipe/depoimentos
│   └── blog/              ← Imagens de blog
└── data/                  ← Banco SQLite (jogatinando.db)
```

## Upload de Jogos

1. No admin, vá em **Jogos → Novo Jogo**
2. Preencha título, engine e descrição
3. Faça upload do **ZIP do jogo** (export HTML da engine)
4. O ZIP deve conter um `index.html` na raiz ou em uma subpasta
5. O CMS extrai automaticamente o ZIP e serve o jogo em `/jogar/<id>`

## Trocar Senha Admin

Edite `config.php` e altere:
```php
define('ADMIN_PASSWORD_HASH', password_hash('sua-nova-senha', PASSWORD_DEFAULT));
```

## Nginx (alternativa ao .htaccess)

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location /jogar/ {
    rewrite ^/jogar/([0-9]+)$ /game.php?id=$1 last;
}
```
