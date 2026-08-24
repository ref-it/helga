# HELGA

This is a very simple shift planer. An admin can define a plan with shifts and users can subscribe to one or more shifts. It can help to organize parties, festivals or political events.

## About HELGA

HELGA (formerly known as Schichtplan) was originally developed by [o](https://code.immerda.ch/o) with [cakephp](https://book.cakephp.org/1.3/en/index.html) framework back in 2011. The current version uses [Laravel](https://github.com/laravel/framework), [Livewire](https://livewire.laravel.com) and [Flux UI](https://fluxui.dev) as a base and is compatible with modern PHP versions (^8.3).

## Installation

Install the PHP and JS dependencies, then build the frontend assets:
```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

Then continue with [Configure](#configure) below.

## Configure
To run HELGA you need to configure a database and a mail backend. MariaDB is the recommended database, but you can also use mysql, postgres or sqlite (see also [laravel doc](https://github.com/laravel/framework)). Add a `.env` file with your configuration and credentials - `.env.example` documents every available option.

Please change the APP_KEY. The easiest way to change the app_key is to run `php artisan key:generate`. This will set the APP_KEY in your .env file

```dotenv
APP_NAME=HELGA
APP_ENV=production
APP_KEY=base64:YOU_NEED_TO_CHANGE_ME
APP_DEBUG=false
APP_URL=https://helga.example.com
LOG_LEVEL=info
# key required to trigger scheduled jobs via the /cron endpoint, as an
# alternative to a "php artisan schedule:run" cron entry
API_KEY=the key to trigger cronjobs

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=helga
DB_USERNAME=helga
DB_PASSWORD=YOU_NEED_TO_CHANGE_ME

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"
```

Login is handled entirely via OIDC (OpenID Connect) - there is no local username/password authentication. You need an OIDC client registered with your identity provider before anyone can log in:

```dotenv
OIDC_BASE_URL=https://your-identity-provider.example
OIDC_CLIENT_ID=
OIDC_CLIENT_SECRET=
OIDC_REDIRECT_URI="${APP_URL}/auth/callback"
OIDC_SCOPES="openid email profile phone groups"
# the claim that carries the user's group memberships, used for plan
# sharing and to determine global admins below
OIDC_GROUPS_CLAIM=groups
# comma-separated group names (from OIDC_GROUPS_CLAIM) that get admin
# rights on every plan, e.g. OIDC_ADMIN_GROUPS=admin,staff
OIDC_ADMIN_GROUPS=
```

Two optional feature flags control scheduled background behavior - both default to `false` and only affect user-facing messaging/opt-ins, not the underlying commands themselves (see [Commands](#commands)):

```dotenv
# set to true once a cron entry (or an external pinger hitting /cron with
# API_KEY) actually triggers scheduled cleanup - this only controls whether
# the home page tells users plans get auto-deleted
PLAN_CLEANUP_ENABLED=false
# number of days a plan may sit without activity before it gets auto-deleted
PLAN_CLEANUP_DAYS=30
# set to true once something actually triggers scheduled jobs - otherwise
# the "notify me" checkbox on a subscription would be a false promise
REMINDERS_ENABLED=false
```

After you generated the APP_KEY and configured your database connection, you have to run the databse migrations. This will setup or migrate needed database tables.

```bash
# Install or upgrade database tables
php artisan migrate
```

You should register a cronjob to run scheduled jobs (plan cleanup, subscriber notifications). For more information see the [laravel documentation](https://laravel.com/docs/scheduling)
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Upgrade
There is no upgrade path from older versions (< 2.0), back when the project was still called Schichtplan.

## Commands
There is a command to clean up old plans. Most of the time you want to run this in a schedule and don't need to invoke it directly.
```bash
php artisan schichtplan:cleanup
```

There is a command to remove users who neither administer a plan nor are subscribed to a shift.
```bash
php artisan schichtplan:cleanup-users
```

There is a command to send notification emails to subscribers one day before the event.
```bash
php artisan schichtplan:notify-subscribers
```

## Development
If you find errors please open an issue or send a pull request!

To start devloping, clone the repo, install the dependencies and copy the `.env.example` to .env. You want to check the values in the `.env` file, before starting to develop.

You need the frontend dependencies as well.
```bash
# install frontend dependencies (Tailwind CSS/Vite)
npm install
# start the Vite dev server and watch for changes
npm run dev
```

```bash
# run dev server
php artisan serve
```

If you change the design make sure you also commit the built assets in `public/build`.
```bash
# Build production assets
npm run build
```

## Containerized development env

```bash
CR=podman
$CR run --rm --entrypoint bash -it -v .:/app docker.io/library/node -c "cd /app && npm install && npm run build"
$CR run --rm -it -v .:/app docker.io/library/composer install
$CR run --rm --env-file=.env -it -v .:/app docker.io/library/php:8 bash -c "cd /app && php artisan migrate"
$CR run --rm --env-file=.env --net=host -p 8000:8000 -it -v .:/app docker.io/library/php:8 bash -c "cd /app && php artisan serve"
```

## Copyright

HELGA is free software and under [AGPL license](https://www.gnu.org/licenses/agpl-3.0.en.html)

[Laravel](https://laravel.com) and [Livewire](https://livewire.laravel.com) are open source software under the [MIT license](https://opensource.org/licenses/MIT).

[Tailwind CSS](https://github.com/tailwindlabs/tailwindcss) is open source software under the [MIT license](https://opensource.org/licenses/MIT).

[Flux UI](https://fluxui.dev) is used under its own commercial license terms (see [fluxui.dev/pricing](https://fluxui.dev/pricing)) - it is not bundled as open source software, and a separate license is required to use it in your own deployment.
