# Anti-padrões a Evitar

- Não introduzir Composer
- Não introduzir framework PHP
- Não adicionar npm, Vite, Webpack ou qualquer build step
- Não transformar o projeto em SPA
- Não criar camada de API sem necessidade real
- Não adicionar JavaScript framework para resolver fluxo simples de interface
- Não introduzir microserviços para um escopo que é monolítico e leve
- Não substituir templates inline por uma arquitetura de views complexa sem justificativa forte
- Não espalhar lógica de negócio em locais não documentados
- Não criar dependências persistentes fora de `.vibecoding/`
- Não adicionar abstrações excessivas para tarefas simples
- Não tratar o CMS como se fosse uma plataforma genérica de aplicação, ignorando o contexto de site de estúdio de games