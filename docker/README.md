# Docker — Credenciais Padrão

## MySQL (serviço `db`)

| Variável | Valor |
|---|---|
| Root password | `root_cms2024` |
| Database | `cms_db` |
| User | `cms_user` |
| Password | `cms_pass2024` |
| Port (host) | `3307` → `3306` (container) |

## Configuração local (produção)

Crie `config.local.php` na raiz do projeto:

```php
<?php

if (!defined('DB_TYPE')) {
    define('DB_TYPE', 'mysql');
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'cms_db');
    define('DB_USER', 'cms_user');
    define('DB_PASS', 'cms_pass2024');
}
```

> `config.local.php` é gitignored — não sobe para o repositório.
