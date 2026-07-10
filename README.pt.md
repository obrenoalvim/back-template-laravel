[English](README.md) | Português

# back-template-laravel

Template base pronto pra produção pra novos projetos de backend: Laravel 13 (PHP 8.4), PostgreSQL + Eloquent, auth por token Sanctum com verificação de email/reset de senha/rate limiting, email transacional com fallback em console, logging estruturado, um recurso CRUD de exemplo completo (`notes`), e Docker + CI ligados ponta a ponta.

## Stack

- Laravel 13 — PHP 8.4, `strict_types` em todo lugar
- PostgreSQL + Eloquent (migrations versionadas em `database/migrations`)
- Laravel Sanctum — personal access tokens (Bearer, não modo cookie SPA — agnóstico de frontend)
- Laravel Mail — driver `log` em dev (escreve em `storage/logs` em vez de exigir credenciais SMTP reais)
- Monolog — logs legíveis em dev, JSON pro stdout em prod, nível via `LOG_LEVEL`
- Form Requests — validação de schema em todo endpoint que recebe dado do cliente
- Pest (unit, sem banco) + Pest (feature/integração, Postgres real, sem mock)
- Pint (format) + Larastan/PHPStan (lint) + hook de pre-commit em shell puro (sem Node/Husky)
- Docker (multi-stage, runtime FrankenPHP, non-root, healthcheck) + docker-compose
- GitHub Actions (build+lint+unit, e2e contra Postgres real, build+smoke-test de Docker) + Dependabot

## Começando (Docker — recomendado)

```bash
cp .env.example .env
php -r "file_put_contents('.env', str_replace('APP_KEY=', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), file_get_contents('.env')));"

composer docker:up          # builda e sobe db + app
```

App: http://localhost:8080/api. Postgres exposto na porta `5457` do host por padrão — muda `POSTGRES_PORT` no `.env` se colidir localmente.

## Começando (sem Docker)

Precisa de uma instância Postgres acessível (`docker compose up -d db` sobe só isso) e PHP 8.4 com `pdo_pgsql`/`pgsql` habilitado.

```bash
cp .env.example .env
php -r "file_put_contents('.env', str_replace('APP_KEY=', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), file_get_contents('.env')));"
composer install
composer db:migrate
composer dev
```

## Variáveis de ambiente

Ver `.env.example` pra lista completa e comentada. `config/env.php` + `app/Providers/EnvValidationServiceProvider` validam as obrigatórias no boot — uma var ausente/vazia falha rápido com mensagem legível, com ou sem `php artisan config:cache` rodado (ver Notas de design).

## Auth

- `POST /api/auth/register`, `POST /api/auth/login` — ambos com throttle de 5 requisições/60s por IP, fixo e não configurável via env (ver Notas de design)
- `GET /api/auth/verify-email/{id}/{hash}` (URL assinada, enviada por email no registro), `POST /api/auth/email/resend`
- `POST /api/auth/forgot-password`, `POST /api/auth/reset-password`
- `PUT /api/account/password`, `DELETE /api/account` (ambos exigem Bearer token + senha atual)
- `POST /api/auth/refresh`, `POST /api/auth/logout` — ver [Sessões](#sessões) abaixo
- Trocar a senha revoga todos os *outros* tokens
- Todas as rotas protegidas usam o middleware `auth:sanctum`

## Sessões

`register`/`login`/`refresh` retornam `{ user?, accessToken, refreshToken }`. Ambos são personal access tokens de verdade do Sanctum (ver `AuthController::issueTokenPair()`) com **abilities** e TTLs diferentes — `access` (`SANCTUM_ACCESS_TTL_MINUTES`, default 60) é o que você manda como `Authorization: Bearer <accessToken>` nas requisições normais; `refresh` (`SANCTUM_REFRESH_TTL_DAYS`, default 30) só consegue autenticar `POST /api/auth/refresh`, checado via `tokenCan('refresh')` — um access token toma 403 se tentar usar ali.

`refresh` **rotaciona**: o refresh token apresentado é apagado no momento que um novo par é emitido, então um token roubado e reusado para de funcionar assim que o cliente legítimo fizer o próximo refresh. `logout` revoga o token atual e, se você mandar `refresh_token` no body, esse também — manda os dois pra não deixar um refresh token vivo pra trás.

Isso é o próprio recurso do Sanctum de tokens ability-scoped com expiração por token (sem sistema JWT paralelo grudado em cima) — ver `hasinhayder/hydra` pro mesmo padrão levado mais longe (roles como abilities também).

## Roles

Todo usuário tem uma coluna `role` (`'user'` | `'admin'`, default `'user'`), propositalmente ausente da lista `#[Fillable(...)]` do `User` pra nunca poder ser setada via input de register/update-profile. `GET /api/admin/users` (admin-only, lista todos os usuários) é a referência pra proteger uma rota: o alias de middleware `admin` (`app/Http/Middleware/EnsureUserIsAdmin.php`, registrado em `bootstrap/app.php`) lança uma `AuthorizationException` (403) pra não-admins. Sem promoção self-serve — muda a coluna direto no banco (`UPDATE users SET role = 'admin' WHERE email = '...'`) pra testar localmente.

## Email

Email de verificação e reset de senha passam pelas notifications nativas do Laravel (`VerifyEmail`/`ResetPassword`), reconfiguradas pra linkar em `FRONTEND_URL` no fluxo de reset (links de verificação vão direto pra rota assinada da própria API). Sem SMTP configurado (`MAIL_MAILER=log`), o email é escrito em `storage/logs/laravel.log` em vez de exigir credenciais reais pra testar o fluxo localmente.

## Recurso CRUD de exemplo

`app/Http/Controllers/NotesController.php` + `app/Models/Note.php` (pertence ao usuário autenticado, validado via Form Request, CRUD completo) é a implementação de referência pra copiar na tua primeira feature de verdade — apaga depois que não precisar mais da referência (remove a migration/model/controller/requests de `notes`, gera uma migration pra dropar a tabela).

## Documentação da API

Docs OpenAPI gerados a partir de atributos `zircote/swagger-php` (`#[OA\...]`) nos controllers, via [L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger). Com o app rodando, abre `http://localhost:8000/api/documentation` pro Swagger UI interativo. Usa o botão "Authorize" com um token Sanctum de `/api/auth/login` pra testar as rotas protegidas (`notes`, `account`, `auth/logout`, `auth/email/resend`).

`L5_SWAGGER_GENERATE_ALWAYS=true` (setado em `.env.example`) regenera o spec a cada request em dev; desliga (`false`) em produção e roda `php artisan l5-swagger:generate` como step de build/deploy.

## Testes

- **Unit** (`vendor/bin/pest --testsuite=Unit`): sem banco — ex: `ApiExceptionRendererTest` instancia o renderer direto.
- **Feature/integração** (`composer test:e2e`): Postgres real via `RefreshDatabase`, sem mock. Precisa de `docker compose up -d db` (ou um Postgres acessível batendo com os `DB_*` do `phpunit.xml`) primeiro.
- `composer test` roda tudo junto (o que os jobs `build`/`e2e` do CI dividem em dois jobs separados).

## Docker

- `Dockerfile` — 3 estágios (`composer:2` deps → FrankenPHP `builder` → FrankenPHP `runtime`), roda como usuário non-root na porta não-privilegiada `8000`, healthcheck bate em `/api/health` via `127.0.0.1`.
- `docker/entrypoint.sh` — roda `package:discover`/`config:cache`/`route:cache`/`event:cache` no **start** do container, não no build da imagem (ver Notas de design), depois executa o FrankenPHP.
- `docker-compose.yml` — `db` (Postgres 17, healthcheck via `pg_isready`, porta `5457` do host) e `app` (espera `db` ficar healthy, porta `8080` do host).

## Scripts

| Script | Faz |
| --- | --- |
| `composer dev` | Sobe o servidor de dev (`artisan serve`) |
| `composer build` | `composer install --no-dev --optimize-autoloader` |
| `composer start` | Cacheia config/rotas, depois serve (aproximação local do entrypoint do Docker) |
| `composer lint` | Larastan (`phpstan analyse`) |
| `composer format` | Pint (escreve) |
| `composer format:check` | Pint (só checa) |
| `composer test` | Suite Pest completa (Unit + Feature) |
| `composer test:watch` | Testes unit, roda de novo a cada 2s (Pest não tem watch mode nativo — ver Notas de design) |
| `composer test:e2e` | Só testes Feature, contra Postgres real |
| `composer db:generate` | `artisan make:migration` |
| `composer db:migrate` | `artisan migrate` |
| `composer docker:up` | `docker compose up -d` |
| `composer docker:down` | `docker compose down` |

Sem `db:studio` — Eloquent não tem navegador visual de dados embutido como Prisma/Drizzle; usa `psql`, TablePlus, ou similar direto no container Postgres.

## Usando como template

1. Clona/degita esse repo como ponto de partida do teu novo projeto
2. Atualiza `name`/`description` do `composer.json` e esse README
3. `cp .env.example .env`, gera uma `APP_KEY` real (ver Começando)
4. `composer docker:up && composer db:migrate`
5. Apaga `NotesController`/`Note` depois de copiar o padrão pra tua primeira feature

## Notas de design e armadilhas

Coisas que não foram óbvias construindo isso, guardadas aqui pra não precisar redescobrir:

- **`env()` fora de um arquivo de config quebra assim que `php artisan config:cache` roda**: o Laravel pula reparsear o `.env` inteiramente uma vez que a config tá cacheada (otimização padrão de prod), então chamadas `env()` em outro lugar leem o que estiver no ambiente OS real naquele instante — não necessariamente o que o `.env` teria fornecido. O check fail-fast do `EnvValidationServiceProvider` originalmente usava `env()` direto, o que faria reportar toda var obrigatória como "ausente" em qualquer boot com config cacheada. Corrigido mapeando a lista obrigatória de `config/env.php` pra paths de `config()` (ex: `APP_KEY` → `app.key`) em vez disso, que resolvem corretamente uma vez no momento do cache de qualquer forma.
- **O próprio exception handler do Laravel reembrulha `ModelNotFoundException`/`AuthorizationException` antes de qualquer callback `render()` customizado rodar**: `Handler::prepareException()` converte elas em `NotFoundHttpException`/`AccessDeniedHttpException` com a original como `$previous`, então `instanceof ModelNotFoundException` em `ApiExceptionRenderer` era código morto silencioso, caindo no branch genérico e vazando a mensagem crua do Eloquent ("No query results for model [App\Models\Note] 99999") em vez de um "Resource not found." limpo. Corrigido checando também `$e->getPrevious()`.
- **Uma rota protegida quebra com 500, não 401, se a request não mandar `Accept: application/json`**: o middleware `Authenticate` do Laravel chama `route('login')` pra montar o destino de redirect da exception pra clientes não-JSON (curl puro, navegadores) — e `route('login')` lança `RouteNotFoundException` imediatamente se não existir tal rota, o que essa app API-only não tinha. Corrigido com uma rota `login` dummy (`routes/web.php`) que só dá `abort(401)`; clientes JSON nunca chegam nela de verdade já que `shouldRenderJsonWhen()` renderiza o 401 antes.
- **O hook `post-autoload-dump` do `composer dump-autoload` (`artisan package:discover`) inicializa o framework** — sem nenhum env presente ainda durante um build Docker, isso batia no `EnvValidationServiceProvider` e matava o build. Corrigido com `--no-scripts` no `dump-autoload` do estágio de build; `package:discover` roda em `docker/entrypoint.sh` junto com o aquecimento de cache, uma vez que o env real de runtime existe. O mesmo bug de ordem bateu direto no CI (o próprio script `install` do composer, não só o build Docker) — corrigido gerando a `APP_KEY` com `php -r` puro (sem precisar do Laravel) *antes* do `composer install` rodar.
- **Sem env vars placeholder de build-time no Docker, de propósito**: diferente de um frontend assando `NEXT_PUBLIC_*`/`API_BASE_URL` num bundle JS no build, uma API de backend não tem essa dependência de env em build-time — então o cache de config/rota/evento acontece em `docker/entrypoint.sh` no **start** do container, usando env real de runtime, não em build time. Assar `config:cache` em build time com uma `APP_KEY` placeholder faria o Laravel continuar usando esse placeholder pra sempre depois, não importa a chave real passada em runtime, quebrando silenciosamente encriptação e URLs assinadas assim que divergissem.
- **`bootstrap/cache/*.php` tá no gitignore mas não tava no dockerignore**: um manifesto de package-discovery gerado localmente em dev (listando o provider do `laravel/pail`, um pacote dev-only ausente da imagem `--no-dev`) foi copiado pra imagem via `COPY . .` e quebrava *toda* invocação artisan no container — esse manifesto obsoleto carrega antes do `package:discover` ter chance de regenerá-lo. Corrigido adicionando `bootstrap/cache/*.php` no `.dockerignore`.
- **Essa versão do Larastan não entende o `casts()` baseado em método do Laravel 11+**: inferia silenciosamente `email_verified_at` como `string` em vez de `Carbon`, só tratando corretamente `created_at`/`updated_at` como caso especial (confirmado com `PHPStan\dumpType()`). Corrigido mantendo `User::$casts` como a propriedade clássica em vez de um método — a extensão dele parseia essa forma.
- **`composer.lock`, resolvido numa máquina PHP 8.4, já tinha puxado `symfony/clock 8.1` exigindo PHP `>=8.4.1`** — o que significa que o `"php": "^8.3"` declarado já era mentira antes do Docker sequer pegar isso (a imagem PHP 8.3 do Docker falhava no `composer dump-autoload` com erro de platform-requirements). Subimos o `composer.json` e a imagem base do Docker pra PHP 8.4 pra bater com o que já tinha sido testado o tempo todo, em vez de caçar versões de dependência compatíveis com 8.3.
- **Porta `5457` do Postgres no host, não `5432`/`5455`/`5456`**: essa máquina já tem outras instâncias Postgres locais (e os projetos irmãos `back-template-nest`/`next-template`) presas nessas. `POSTGRES_PORT` no `.env` torna isso um fix de uma linha onde quer que isso seja deployado.
- **O rate limit de register/login é `throttle:5,1` fixo no middleware da rota, não vem de env**: deliberadamente não ligado a uma env var, pra uma `.env` mal configurada não conseguir acidentalmente afrouxar a proteção nos dois endpoints que mais valem a pena proteger (mesmo raciocínio do template Nest irmão).
- **Pest não tem watch mode nativo**: `test:watch` faz polling do `artisan test --testsuite=Unit` a cada 2 segundos em vez de reagir a mudanças de arquivo — um watcher de filesystem de verdade precisaria de um plugin com dependência nativa `fswatch`/`inotify`, não vale a pena adicionar por um script de conveniência de dev. É honestamente um poll, não um watch.
- **Requests sequenciais dentro de um teste Pest podem autenticar com um token já revogado**: o `AuthManager` do Laravel cacheia instâncias de guard resolvidas (e `RequestGuard` cacheia seu usuário resolvido nele mesmo) pela vida da app — ok em produção, onde toda request real inicializa uma app nova, mas dentro de um teste fazendo várias requests pela mesma app já inicializada, um token revogado pela request *N* ainda podia "autenticar" na request *N+1*. Não é bug de produção, mas real o suficiente pra quebrar 3 testes até `forgetAuthGuards()` (`tests/Pest.php`) ser chamado depois de toda request que muda estado de auth.
