# Decisões Técnicas

## 1. PHP flat-file em vez de framework

O projeto deve permanecer em PHP 8.2 puro, com arquivos simples e sem framework externo. A escolha prioriza controle direto, menor superfície de dependência e manutenção compatível com o escopo do Jogatinando CMS.

## 2. Suporte duplo de banco de dados

O sistema deve continuar suportando **SQLite** e **MySQL/MariaDB** via PDO. Isso permite desenvolvimento simples localmente e flexibilidade para diferentes ambientes de hospedagem.

## 3. Docker para desenvolvimento

O ambiente de desenvolvimento deve usar Docker para padronizar execução local, especialmente com Apache e MySQL 8.0. Isso reduz divergências entre máquinas e simplifica onboarding.

## 4. Autenticação por sessão

A autenticação administrativa deve permanecer baseada em sessão PHP, com proteção CSRF nos formulários. O modelo é suficiente para o tipo de aplicação e mantém a implementação enxuta.

## 5. Templates inline em PHP

As páginas devem continuar usando templates inline em PHP, sem separar uma camada de view sofisticada. Isso preserva o estilo do projeto e reduz complexidade estrutural.

## 6. Tema visual com tokens OKLCH

O painel administrativo deve manter o tema escuro com acentos dourados usando tokens de cor OKLCH em `assets/css/admin.css`, preservando consistência visual e modernidade sem depender de ferramentas extras.

## 7. Migrações automáticas

O schema do banco deve ser atualizado automaticamente por meio de migrações executadas em tempo de conexão. Isso reduz trabalho manual e melhora a estabilidade entre instalações diferentes.

## 8. Marker de instalação em `config.local.php`

A presença de `config.local.php` deve continuar sendo o indicador de instalação concluída. Sem esse arquivo, o sistema deve considerar que ainda precisa passar pelo instalador.

## 9. Estrutura orientada ao contexto

Toda decisão relevante deve ser registrada em `.vibecoding/` para manter o contexto do projeto disponível para modos futuros e evitar perda de conhecimento operacional.