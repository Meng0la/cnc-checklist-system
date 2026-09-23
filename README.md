# AeroCheck — Checklist de Limpeza e Ferramentas para CNCs

> **Nota de portfólio:** este é um projeto real, em produção numa oficina de usinagem CNC,
> republicado aqui como amostra de código — com o nome da empresa, dados de funcionários,
> máquinas e credenciais removidos/substituídos por exemplos fictícios. A estrutura, a
> lógica de negócio e o código em si são os mesmos do sistema em uso.

Sistema web em PHP + MySQL (sem framework), feito para rodar no XAMPP, para checklist de
limpeza/vistoria e conferência de ferramentas no início e fim de turno de máquinas CNC.
Controla usuários por matrícula (sem e-mail/senha corporativa), perfis de acesso por papel,
histórico de checklists, relatório de pendências por turno e alertas automáticos por e-mail.

**Principais funcionalidades:**
- Login por matrícula com 5 perfis de acesso (operador, administrador, supervisor de
  produção, gerente, manutenção) e troca de senha obrigatória no primeiro acesso.
- Checklist de início/fim de turno com duas seções — limpeza e conferência de ferramentas
  (quantidade padrão por item) — e observação obrigatória em caso de não conformidade.
- Dashboard de conformidade, histórico filtrável, log de auditoria e exportação CSV.
- Alerta automático por e-mail quando um checklist vem "Não Conforme", com confirmação
  ("Visto") e escalonamento se ninguém confirmar a tempo.
- Alerta automático por e-mail quando uma máquina fica sem checklist de início/fim num
  turno (dias úteis obrigatórios, fins de semana opcionais) — script CLI agendado.
- Backup automático do banco de dados via tarefa agendada do Windows.
- Cliente SMTP próprio (socket puro, sem PHPMailer/Composer) e front-end em Tailwind CSS
  compilado offline — o servidor de produção não precisa de acesso à internet.

## 1. Estrutura

```
config/      configurações e conexão com o banco
includes/    autenticação, funções auxiliares, layout (header/footer), log de auditoria
assets/      css compilado (Tailwind), js e imagens (logo)
tailwind/    fonte do CSS (Tailwind) e CLI standalone — só usado para gerar assets/css/style.css
sql/         schema.sql e scripts de importação (importar no MySQL)
scripts/     backup_db.ps1 (backup automático) e verificar_alertas.php (alerta de não conformidade sem confirmação) — ambos via Agendador de Tarefas
admin/       cadastro de usuários, máquinas, itens do checklist e log de auditoria (adm/supervisor)
checklist/   preenchimento, histórico e exportação (CSV) de checklists
relatorios/  pendências e dashboard de conformidade (conforme x não conforme)
install.php  cria o primeiro usuário administrador (rodar 1 vez, depois apagar)
```

## 2. Perfis de acesso (roles)

| Perfil | Preenche checklist | Vê histórico completo | Vê pendências | Cadastra usuários/máquinas | Reseta senha |
|---|---|---|---|---|---|
| Operador | Sim (qualquer máquina ativa) | Só o próprio | Não | Não | Não |
| Administrador | Não | Sim | Sim | Sim | Sim |
| Supervisor de Produção | Não | Sim | Sim | Sim | Sim |
| Gerente | Não | Sim | Sim | Não | Não |
| Manutenção | Não | Sim | Sim | Não | Não |

Login é feito só por **matrícula + senha** (sem e-mail). Todo usuário novo, ou que teve a
senha resetada, entra com a senha padrão `Aero@2026` e é obrigado a trocá-la no primeiro
acesso antes de usar qualquer outra tela.

## 3. Instalação no servidor XAMPP (a máquina acessada via AnyDesk)

> **Exemplo de ambiente** (ajuste IP/caminhos pro seu servidor): XAMPP instalado em
> `D:\xampp` (pode ser `C:\xampp`, tanto faz). Se a porta 80 já estiver ocupada por outro
> serviço (IIS, por exemplo), configure o Apache do XAMPP pra rodar em outra porta (ex.:
> 8080) e ajuste as URLs deste guia de acordo. O MySQL do XAMPP roda por padrão na porta
> 3306, sem senha de root — as credenciais padrão em `config/config.php` (`DB_USER=root`,
> `DB_PASS=''`) já funcionam nesse caso.

1. **Copiar os arquivos**: copie toda esta pasta para dentro do `htdocs` do XAMPP daquele
   servidor, em `D:\xampp\htdocs\aerocheck`. O `htdocs` é a "pasta mãe" que o Apache do
   XAMPP serve — tudo que fica dentro dela vira uma URL. Como já está em `aerocheck`,
   `config/config.php` não precisa de ajuste (`APP_BASE_URL` já é `/aerocheck`).

2. **Criar o banco de dados**: abra `http://localhost:8080/phpmyadmin` no próprio servidor
   (via AnyDesk), vá em "Importar" e selecione o arquivo `sql/schema.sql`. Isso cria o banco
   `aerocheck_db`, as tabelas e os 5 itens padrão do checklist de limpeza.

3. **Importar as máquinas (CNCs)**: ainda no phpMyAdmin, aba SQL do banco `aerocheck_db`,
   cole o conteúdo de `sql/importar_maquinas.exemplo.sql` e execute. Esse arquivo é um
   **exemplo com dados fictícios** — troque pelas suas máquinas reais (código curto usado
   no chão de fábrica, número de patrimônio, tipo, fabricante, criticidade etc.) antes de
   usar em produção. Também dá pra cadastrar máquina por máquina direto na tela "Máquinas".

3.1. **Importar os operadores**: cole o conteúdo de `sql/importar_operadores.exemplo.sql`
   e execute (também com dados fictícios — troque pelos seus colaboradores reais).
   Cadastra os usuários com role `operador` (matrícula = identificador do colaborador), com
   senha padrão. Seguro rodar de novo — não mexe em senha de quem já logou.

3.2. **Criar a tabela de auditoria**: cole o conteúdo de `sql/migracao_auditoria.sql` e
   execute. Sem isso, o menu "Auditoria" do painel fica sem dados (mas não quebra o resto
   do sistema).

3.3. **Ativar notificação por e-mail e confirmação ("Visto")**: cole o conteúdo de
   `sql/migracao_notificacoes.sql` e execute. Adiciona o campo de e-mail nos usuários e o
   controle de confirmação nos checklists — veja a seção 9 (E-mail e confirmação) mais
   abaixo para configurar o SMTP.

3.4. **Ativar o checklist de ferramentas**: cole o conteúdo de `sql/migracao_ferramentas.sql`
   e execute. Adiciona a categoria/quantidade padrão nos itens e cadastra o jogo de
   ferramentas padrão — veja a seção 7 (Checklist de ferramentas) mais abaixo.

4. **Criar o primeiro administrador**: acesse `http://192.168.1.50:8080/aerocheck/install.php`,
   preencha matrícula e nome. Depois disso, **apague ou renomeie o arquivo `install.php`**
   por segurança — ele não pede login.

5. **Testar localmente no próprio servidor** antes de liberar na rede: abra
   `http://localhost:8080/aerocheck/` no navegador do próprio servidor e confirme que a
   tela de login aparece.

6. **Vincular operadores às máquinas (opcional)**: em "Máquinas" → "Operadores", dá pra
   registrar quem costuma operar cada máquina por turno, como referência histórica. Isso é
   só informativo — não é obrigatório e não bloqueia ninguém de fazer o checklist em
   qualquer máquina ativa (o sistema não fixa operador↔máquina de propósito, por causa da
   alta rotatividade comum nesse tipo de chão de fábrica). O relatório de pendências (seção
   6) e o alerta por turno (seção 10) funcionam por máquina, não por operador vinculado.

7. **Cadastrar os demais usuários** em "Usuários" (matrícula, nome, perfil). Todos nascem
   com a senha padrão `Aero@2026` e trocam no primeiro login.

## 4. Liberando o acesso pela rede local (192.168.1.50:8080)

Isso é feito **na máquina servidor**, via AnyDesk. São duas camadas possíveis de bloqueio:
o Firewall do Windows daquela máquina, e o pfSense (se os operadores estiverem em um
segmento/VLAN diferente do servidor).

### 4.1 Abrir a porta no Firewall do Windows (na máquina servidor)

1. Painel de Controle → Firewall do Windows Defender → Configurações Avançadas.
2. Regras de Entrada (Inbound Rules) → Nova Regra.
3. Tipo: **Porta** → Próximo.
4. TCP → Porta local específica: `8080` → Próximo.
5. **Permitir a conexão** → Próximo.
6. Marque os perfis (pelo menos **Privado**; marque Domínio também se a máquina estiver
   no domínio da empresa) → Próximo.
7. Nome: "XAMPP AeroCheck HTTP" → Concluir.

Se no futuro a porta 8080 também entrar em conflito com outro sistema, rode
`netstat -ano | findstr LISTENING` no servidor para achar uma porta livre, troque `Listen 8080`
por `Listen <nova-porta>` em `D:\xampp\apache\conf\httpd.conf`, reinicie o Apache pelo XAMPP
Control Panel e repita a liberação de firewall com a nova porta. **Mudar a porta do Apache
aqui não afeta nenhum outro sistema da empresa** — porta é por máquina, não é algo global.


## 5. Visual (Tailwind CSS)

O CSS (`assets/css/style.css`) é **compilado**, não escrito à mão — a fonte fica em
`tailwind/input.css`, usando o CLI standalone do Tailwind (`tailwind/tailwindcss.exe`, não
precisa de Node/npm instalado, funciona 100% offline). O resultado final é um arquivo CSS
estático normal, servido pelo Apache como qualquer outro — o navegador do operador **não**
baixa nada de internet, nem em produção.

`tailwind/input.css` define os tokens de marca (`--color-accent`, `--color-sidebar` etc. em
`@theme`) e os componentes reutilizáveis (`.btn-primary`, `.badge-ok`, `.card`, `.nav-link`,
`.table`, `.checklist-item` etc. em `@layer components`) usados por todas as páginas PHP —
as páginas continuam usando essas mesmas classes no HTML, então adicionar uma página nova
normalmente não exige mexer no CSS.

> **Neste repositório o binário `tailwind/tailwindcss.exe` não está incluído** (é um
> executável de ~100MB, não faz sentido versionar). Baixe o CLI standalone da sua
> plataforma em [github.com/tailwindlabs/tailwindcss/releases](https://github.com/tailwindlabs/tailwindcss/releases)
> e coloque em `tailwind/tailwindcss.exe` — o `assets/css/style.css` já compilado está
> incluído, então isso só é necessário se você for alterar o CSS.

**Se você alterar `tailwind/input.css`** (ou adicionar uma classe Tailwind nova direto em
algum `.php`), recompile rodando isto na pasta do projeto:

```bash
tailwind\tailwindcss.exe -i tailwind\input.css -o assets\css\style.css --minify
```

A pasta `tailwind/` (fonte + binário do CLI) só é necessária durante o desenvolvimento —
não precisa existir no servidor de produção, só o `assets/css/style.css` já compilado. Por
segurança ela tem um `.htaccess` bloqueando acesso direto, caso seja copiada junto.

## 6. Exportação e auditoria

- **Exportar CSV**: nas telas "Histórico" e "Conformidade", o botão "Exportar CSV" baixa
  exatamente os checklists filtrados na tela (mesmo período/máquina/status), com colunas
  Data, Máquina, Tipo, Turno, Operador, Matrícula, Status e o detalhe de cada não
  conformidade. O arquivo abre certo no Excel em português (separador `;`, com BOM UTF-8
  para os acentos aparecerem corretos).
- **Log de auditoria** (menu "Auditoria", adm/supervisor): registra quem criou, editou,
  resetou senha ou ativou/desativou usuários, máquinas e itens do checklist, e quando. Não
  registra os checklists em si (esses já têm o próprio histórico) — é sobre ações
  administrativas. Filtra por usuário, área e período.

## 7. Checklist de ferramentas

Além dos itens de limpeza, o checklist tem uma segunda seção, **Ferramentas**, pra
conferir se o jogo de ferramentas que acompanha a máquina está completo (martelo,
paquímetro, jogo de chaves Alen, etc.), cada um com uma quantidade padrão de referência
mostrada ao lado da descrição (ex.: "Ponteiras (Qtde padrão: 3)"). Funciona exatamente
como os itens de limpeza: o operador marca Conforme ou Não Conforme, e em caso de Não
Conforme descreve o que falta ou está errado (ex.: "Faltando 1 chave Alen M6").

- **Instalação nova**: os itens de ferramenta já vêm no `sql/schema.sql`, nada a fazer.
- **Instalação existente**: rode `sql/migracao_ferramentas.sql` no phpMyAdmin (banco
  `aerocheck_db` → aba SQL → colar o arquivo → Executar). Seguro rodar mais de uma vez.
- **Editar a lista** (adicionar/remover ferramenta, trocar quantidade padrão): menu
  "Itens do Checklist" (Administrador/Supervisor), seção "Ferramentas".

## 8. Backup automático do banco

O script `scripts/backup_db.ps1` roda `mysqldump`, compacta o resultado em `.zip` e apaga
backups com mais de 30 dias (ajustável no topo do script). **O destino fica fora do
`htdocs`** (`D:\xampp\backups\aerocheck`) de propósito — um dump do banco dentro da pasta
servida pelo Apache é um risco, mesmo com `.htaccess`.

**Testar manualmente primeiro** (Prompt de Comando ou PowerShell, no servidor):

```powershell
powershell -ExecutionPolicy Bypass -File D:\xampp\htdocs\aerocheck\scripts\backup_db.ps1
```

Confira se apareceu um `.zip` em `D:\xampp\backups\aerocheck` e leia `backup.log` na mesma
pasta — todo run (com sucesso ou erro) fica registrado ali.

**Agendar para rodar sozinho todo dia**, via Prompt de Comando **como administrador**:

```bash
schtasks /create /tn "AeroCheck - Backup Diario" /tr "powershell.exe -ExecutionPolicy Bypass -File D:\xampp\htdocs\aerocheck\scripts\backup_db.ps1" /sc daily /st 23:30 /ru SYSTEM
```

Isso roda todo dia às 23:30. Pra conferir se a tarefa foi criada: abra o **Agendador de
Tarefas** do Windows (`taskschd.msc`) e procure "AeroCheck - Backup Diario" na lista.

Se o MySQL desse servidor ganhar senha de root no futuro, edite `$DbPass` no topo do
`scripts\backup_db.ps1` (mesma senha que for colocada em `config/config.php`).

## 9. E-mail e confirmação ("Visto") de não conformidades

**Como funciona:**
- Quando um checklist é salvo como **Não Conforme**, o sistema manda um e-mail na hora
  para todo usuário de perfil Administrador, Supervisor, Gerente ou Manutenção que tiver
  e-mail cadastrado (campo opcional em "Usuários").
- Qualquer um desses perfis pode abrir o checklist e clicar em **"Marcar como visto"** —
  fica registrado quem confirmou e quando. Aparece como badge "Visto" ou "Não confirmado"
  no Histórico, no Painel e no e-mail.
- Se ninguém confirmar dentro de um tempo configurável (padrão: 45 minutos, ajustável em
  `config/config.php` → `ALERTA_ESCALACAO_MINUTOS`), o script `scripts/verificar_alertas.php`
  dispara um segundo e-mail de cobrança. Esse e-mail só é enviado uma vez por checklist.

Isso **não** é um sistema de gestão de manutenção — não tem fluxo de "em tratativa",
peças, ordem de serviço, etc. (vocês já têm um CMMS pra isso). É só: alguém foi avisado,
e alguém confirmou que viu.

### 9.1 Configurar o SMTP

Em `config/config.php`, ajuste (já vem pré-preenchido com o que você passou, falta só a senha):

```php
define('SMTP_HOST', 'smtp.suaempresa.com.br');
define('SMTP_PORT', 587);           // 587 = STARTTLS, ou 465 = SSL implícito
define('SMTP_SECURE', 'tls');       // 'tls' (porta 587) ou 'ssl' (porta 465)
define('SMTP_USER', 'sistema@suaempresa.com.br');
define('SMTP_PASS', '');            // <-- preencher a senha da caixa aqui, no servidor
define('SMTP_FROM_EMAIL', 'sistema@suaempresa.com.br');
define('SMTP_FROM_NAME', 'AeroCheck');
```

Se preferir a porta 465 em vez da 587, troque `SMTP_PORT` para `465` e `SMTP_SECURE` para `'ssl'`.

Deixar `SMTP_HOST` vazio (`''`) desliga o envio de e-mails sem quebrar nada — o sistema
só deixa de mandar e-mail, tudo o resto (checklist, "Visto" manual, histórico) continua
funcionando normalmente.

**Cadastrar os e-mails de quem deve receber**: em "Usuários", edite cada Administrador,
Supervisor, Gerente ou Manutenção que deve ser notificado e preencha o campo "E-mail".
Quem não tiver e-mail cadastrado simplesmente não recebe (não dá erro).

**Testar**: preencha um checklist como Não Conforme (ou peça pra um operador testar) e
confira se o e-mail chega nos endereços cadastrados. Se não chegar, veja o log de erros
do PHP/Apache no servidor — falha de e-mail nunca impede o checklist de ser salvo, então
o erro só aparece no log, não na tela.

### 9.2 Agendar o script de alerta (Agendador de Tarefas)

Duas coisas diferentes, não confundir:
- **`ALERTA_ESCALACAO_MINUTOS`** (`config/config.php`) — quanto tempo uma não conformidade
  fica sem "Visto" até o e-mail de cobrança disparar. Hoje **5 horas**. O e-mail só sai
  **uma vez** por checklist, não importa de quanto em quanto tempo a tarefa abaixo roda.
- **Intervalo da tarefa agendada** — só controla a rapidez com que o sistema *percebe*
  que o prazo estourou. Rodar com mais frequência não gera e-mail a mais.

Testar manualmente primeiro:

```powershell
D:\xampp\php\php.exe D:\xampp\htdocs\aerocheck\scripts\verificar_alertas.php
```

Deve responder algo como `0 checklist(s) pendente(s) de confirmação.` sem erro. Depois,
agendar pra rodar sozinho de hora em hora (Prompt de Comando **como administrador**) — de
sobra pra um prazo de 5 horas, sem ficar checando à toa:

```bash
schtasks /create /tn "AeroCheck - Verificar Alertas" /tr "D:\xampp\php\php.exe D:\xampp\htdocs\aerocheck\scripts\verificar_alertas.php" /sc hourly /mo 1 /ru SYSTEM
```

Se quiser mudar o prazo de 5 horas pra outro valor, edite `ALERTA_ESCALACAO_MINUTOS` em
`config/config.php` (é só a quantidade de minutos — ex.: `3 * 60` pra 3 horas).

## 10. Alerta de máquina sem checklist de Início/Fim por turno

Além do alerta de "Não Conforme sem confirmação" (seção 9), existe um segundo alerta,
independente: se **nenhum operador** registrou o checklist de Início ou de Fim de um
turno numa máquina ativa, a gestão recebe um e-mail avisando quais máquinas ficaram sem
aquele checklist. Exemplo: ninguém fez o checklist de Fim do turno da Noite na Torno-05 —
chega um e-mail listando essa máquina.

**Como funciona:**
- Este exemplo cobra 2 turnos: **Manhã** e **Noite** (o turno da Noite cruza a meia-noite —
  no exemplo abaixo, começa às 16h30 e termina às 02h30). O turno Tarde existe no sistema
  mas não é cobrado por este alerta — ajuste `scripts/verificar_pendencias_turno.php`
  conforme os turnos reais da sua fábrica.
- São 4 checagens por dia, uma para cada combinação Turno × Início/Fim, cada uma rodando
  pouco depois do horário em que aquele checklist deveria ter sido feito (pra dar tempo do
  operador preencher antes de soar o alerta).
- **Sábado e domingo são opcionais** — o script não dispara alerta nesses dias (vale o dia
  em que o turno *começou*: um turno da Noite que começa sábado e termina domingo de
  madrugada continua opcional).
- Cada checagem manda **um e-mail só**, listando todas as máquinas pendentes daquela
  checagem — não é um e-mail por máquina.
- Usa a mesma lista de destinatários da seção 9 (Administrador, Supervisor, Gerente,
  Manutenção com e-mail cadastrado em "Usuários").

**Testar manualmente** (troque `manha`/`noite` e `inicio`/`fim` pra testar cada uma):

```powershell
D:\xampp\php\php.exe D:\xampp\htdocs\aerocheck\scripts\verificar_pendencias_turno.php manha inicio
```

**Agendar as 4 checagens** (Prompt de Comando **como administrador**) — os horários abaixo
são sugeridos com ~30 min de tolerância após o início/fim de cada turno; ajuste se o
horário real de fábrica mudar:

```bash
schtasks /create /tn "AeroCheck - Pendencia Manha Inicio" /tr "D:\xampp\php\php.exe D:\xampp\htdocs\aerocheck\scripts\verificar_pendencias_turno.php manha inicio" /sc daily /st 07:30 /ru SYSTEM
schtasks /create /tn "AeroCheck - Pendencia Manha Fim" /tr "D:\xampp\php\php.exe D:\xampp\htdocs\aerocheck\scripts\verificar_pendencias_turno.php manha fim" /sc daily /st 16:30 /ru SYSTEM
schtasks /create /tn "AeroCheck - Pendencia Noite Inicio" /tr "D:\xampp\php\php.exe D:\xampp\htdocs\aerocheck\scripts\verificar_pendencias_turno.php noite inicio" /sc daily /st 17:20 /ru SYSTEM
schtasks /create /tn "AeroCheck - Pendencia Noite Fim" /tr "D:\xampp\php\php.exe D:\xampp\htdocs\aerocheck\scripts\verificar_pendencias_turno.php noite fim" /sc daily /st 03:10 /ru SYSTEM
```

Pra conferir se as 4 tarefas foram criadas: `taskschd.msc` → procure pelo nome "AeroCheck -
Pendencia...". Se o horário real de início/fim de algum turno mudar, é só recriar a tarefa
com `/st` diferente (ou `schtasks /change /tn "<nome>" /st HH:MM`).

## 11. Segurança — pontos já cobertos

- Senhas com hash bcrypt (`password_hash`), nunca em texto puro.
- Troca de senha obrigatória no primeiro login e após reset.
- Bloqueio de 15 minutos após 5 tentativas de login incorretas.
- Proteção CSRF em todos os formulários.
- Reset de senha só disponível para Administrador e Supervisor de Produção.
- Só um Administrador pode criar, editar, resetar senha ou ativar/desativar outra conta
  Administrador — Supervisor de Produção não tem esse poder sobre Admin.
- `config/`, `includes/`, `sql/`, `tailwind/` e `scripts/` bloqueados contra acesso direto
  via navegador (`.htaccess`). O backup do banco fica fora do `htdocs` completamente.
- Todas as consultas ao banco usam parâmetros preparados (proteção contra SQL injection).
- Log de auditoria das ações administrativas (quem fez o quê e quando).
- **Logout automático por inatividade** — 10 minutos sem clique/toque/tecla desloga
  sozinho (checado tanto no navegador quanto no servidor, então funciona mesmo se alguém
  desativar o JavaScript). Importante em dispositivo compartilhado no chão de fábrica: se
  um operador esquecer de sair, a sessão não fica aberta indefinidamente para o próximo
  que pegar o aparelho. Ajustável em `config/config.php` → `SESSAO_IDLE_SEGUNDOS`.
  (Obs.: não existe forma confiável de detectar "fechou a aba" via navegador sem também
  disparar em toda navegação normal dentro do sistema — por isso a proteção real é o
  tempo de inatividade curto, não uma tentativa de pegar o fechamento da aba em si.)

Pontos que dependem de vocês:
- Apagar/renomear `install.php` depois do primeiro uso.
- Se o root do MySQL desse servidor ganhar uma senha no futuro, atualizar `DB_PASS` em
  `config/config.php` (hoje está sem senha, confirmado em produção).
- Colocar as imagens do logo em `assets/img/` (veja `assets/img/LEIA-ME.txt`).
