# What?

Making testing the new react admin UI as simple as possible.

# Requirements
* PHP 5.5.9 or greater
* PHP's pdo_sqlite extension installed. You can use `php -m` to check.

# Installation

```sh
# This command will take some time.
composer create-project jsdrupal/drupal-admin-ui-demo -s dev --prefer-dist
```

# Usage
```sh
cd drupal-admin-ui-demo/docroot
php core/scripts/drupal install
../bin/drush en -y jsonapi admin_ui_support
php core/scripts/drupal server
```

Drupal will be opened up in your default browser.
To access the new interface go to ```http://localhost:51569/vfancy```.

Example URLs to visit:
* ```/admin/people/permissions```


# Updating
```sh
# All users can update the project dependencies using composer.
composer update
```
