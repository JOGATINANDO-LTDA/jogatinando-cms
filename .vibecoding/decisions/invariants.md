# Invariantes do Sistema

## Instalação e configuração

- `config.local.php` é o marcador oficial de instalação concluída
- Se `config.local.php` não existir, o sistema deve tratar a instalação como pendente
- A configuração local não deve ser substituída por valores fixos no código-fonte

## Banco de dados

- `getDB()` deve permanecer como singleton de acesso ao banco
- A camada de banco deve continuar abstraindo SQLite e MySQL/MariaDB por PDO
- Migrações automáticas devem preservar dados existentes sempre que possível

## Segurança

- Toda página administrativa deve exigir `requireLogin()`
- Todo formulário sensível deve usar CSRF
- A autenticação deve continuar baseada em sessão

## Idioma e encoding

- O sistema deve operar em **pt-BR**
- O conjunto de caracteres deve ser **UTF-8** em todas as camadas possíveis
- Texto, conteúdo e mensagens do sistema devem manter consistência linguística

## Interface e operação

- O painel deve preservar a identidade visual escura com acentos dourados
- O frontend deve manter a estética cósmica/medieval do projeto
- O sistema não deve introduzir dependências que exijam build para funcionar

## Contexto do projeto

- As regras de decisão e arquitetura devem permanecer registradas em `.vibecoding/`
- Mudanças estruturais devem ser refletidas nos arquivos de contexto antes de se tornarem padrão de uso