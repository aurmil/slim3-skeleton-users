:warning: __WORK IN PROGRESS__ :construction:

# Slim 3 Skeleton with users authentication

## What's included?

* My own [Slim 3 app skeleton](https://github.com/aurmil/slim3-skeleton)
* [Cartalyst Sentinel v2](https://github.com/cartalyst/sentinel) + [illuminate/database v5](https://github.com/illuminate/database) + [illuminate/events v5](https://github.com/illuminate/events) + [symfony/http-foundation v3](https://github.com/symfony/http-foundation)
* [Respect Validation v1](https://github.com/respect/validation) + [egulias/email-validator v2](https://github.com/egulias/EmailValidator)

## Installation and usage

I invite you to read first the short documentation I wrote for my [app skeleton](https://github.com/aurmil/slim3-skeleton).

Required: PHP 7 and [Composer](https://getcomposer.org/doc/00-intro.md)

Run the following command, replacing `[your-project-name]` with the name of the folder you want to create.

```sh
composer create-project aurmil/slim3-skeleton-users [your-project-name]
```

`.htaccess` file, Web server choice, virtual host, `AllowOverride All`, `var` folder permissions... please refer to the main documentation.

Database:

* Create a MySQL database, a user with permissions on it and put these informations in `db.yaml` configuration file
* Execute the correct MySQL schema creation file that is in the `vendor/cartalyst/sentinel/schema` according to your MySQL version
* Execute the SQL files that are in the `sql` folder

## SwiftMailer and emails

Unlike in the main app skeleton, SwiftMailer usage is required and must be configured in `swiftmailer.yaml` configuration file.

Emails sent to users for account activation when creating a new account (if this option is active) or for password reset are HTML emails. HTML content of these emails is in `templates/emails` folder.

## Users authentication

In `security.yaml` configuration file, you can enable or disable 2 options:

* "remember me" checkbox on login form
* email confirmation sending when creating a new account

Users access to routes is controlled by middlewares `AllowOnlyGuests` and `AllowOnlyLoggedUsers` applied to routes in `routes.php`.

## To do

* Unit tests (need help on this)
    * See [this question](https://github.com/cartalyst/sentinel/issues/46)
* Password guidelines (length, strength, ...) => only length ? mb_strlen
    * Must be configurable
    * Use on signup, reset-password and change-password
    * See [this repo](https://github.com/ircmaxell/password-policy) or [this one](https://github.com/joshralph93/password-policy)
    * JS: https://css-tricks.com/password-strength-meter/
* UUID
    * Add UUID to users
    * Use them in URL like activate account and reset password
    * See [this issue](https://github.com/cartalyst/sentinel/issues/289) and [the wiki](https://github.com/cartalyst/sentinel/wiki/Extending-Sentinel)
* Back office
    * admin group, admin users
    * allow admin users to log in and manage groups + users
    * possible to log in on front office and back office separately at the same time?

## License

The MIT License (MIT). Please see [License File](https://github.com/aurmil/slim3-skeleton-users/blob/master/LICENSE.md) for more information.
