# Arquitetura do Sistema

## Visão geral

O Jogatinando CMS segue uma arquitetura monolítica leve em PHP 8.2, com páginas servidas diretamente por arquivos PHP e persistência via SQLite ou MySQL/MariaDB. O sistema prioriza simplicidade, controle direto e manutenção previsível.

## Pontos de entrada

- `index.php` — página inicial pública
- `game.php` — visualização e execução de jogos
- `install.php` — assistente de instalação e reset
- `retro.php` — catálogo retro
- `retro-play.php` — tela de execução retro
- `retro-console.php` — navegação por console
- `admin/*.php` — área administrativa
- `router.php` — apoio ao roteamento interno quando necessário

## Sistema de configuração

- `config.php` define constantes, caminhos, URLs e valores base
- `config.local.php` sobrescreve configurações sensíveis e indica instalação concluída
- O restante do sistema consome a configuração centralizada sem duplicar valores críticos

## Camada de banco

- `includes/db.php` concentra o acesso ao banco por PDO
- `getDB()` fornece a conexão única do processo
- `dbInit()` cria estrutura inicial
- `dbMigrate()` aplica migrações automáticas
- `dbQuery()`, `dbQueryOne()` e `dbExec()` simplificam uso cotidiano

## Autenticação e segurança

- `includes/auth.php` concentra login, logout e verificação de sessão
- A área administrativa chama `requireLogin()` em suas páginas
- Formulários usam CSRF para reduzir risco de requisições forjadas

## Roteamento de URL

- `.htaccess` e regras do Apache mantêm URLs amigáveis
- Rotas como `/<engine-slug>/<game-slug>` são encaminhadas para `game.php`
- O restante do tráfego público cai em `index.php`

## Sistema de templates

- O projeto usa templates inline em PHP
- `includes/header.php` e `includes/footer.php` ajudam a padronizar estrutura visual
- Não existe camada formal de view separada

## Uploads e arquivos

- Uploads são armazenados em `uploads/`
- Jogos chegam em ZIP e podem ser extraídos para execução
- As rotinas de armazenamento e limpeza ficam em `includes/storage.php`
- O sistema deve preservar limites e validações para manter estabilidade

## Estilo visual

- Frontend com estética cósmica/medieval
- Tipografia baseada em **Cinzel** e **Inter**
- Painel administrativo escuro com acentos dourados

## Manutenção

- O sistema deve continuar com auto-migração
- Rotinas auxiliares de otimização ficam em `includes/optimizer.php`
- O projeto não depende de pipeline de build para operar