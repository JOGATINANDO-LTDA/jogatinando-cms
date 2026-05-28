# Dependências do Projeto

## Dependências de runtime

- **PHP 8.2**
- **PDO**
- Driver **PDO SQLite**
- Driver **PDO MySQL/MariaDB**
- **Apache** com `mod_rewrite`

## Dependências de ambiente

- **Docker** para desenvolvimento local
- Container com **MySQL 8.0** para o cenário MySQL do ambiente de desenvolvimento
- Sistema de arquivos com suporte a escrita para uploads e banco SQLite local

## Extensões PHP esperadas

- `pdo`
- `pdo_sqlite`
- `pdo_mysql`
- `mbstring`
- `json`
- `openssl`
- `fileinfo`
- `zip`
- `session`
- `gd` quando processamento de imagem for necessário

## Dependências de infraestrutura

- Regras de reescrita do Apache para URLs amigáveis
- Permissão de escrita em `data/`, `uploads/` e diretórios de cache/extração quando aplicável
- Ambiente com codificação UTF-8 consistente

## Observação operacional

O projeto não depende de Composer, npm ou ferramentas de build para funcionar. A infraestrutura necessária deve permanecer mínima e alinhada ao modelo flat-file do CMS.