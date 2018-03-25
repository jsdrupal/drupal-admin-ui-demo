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
cd drupal-admin-ui-demo
composer run dev-site
```

Drupal will be opened up in your default browser.
Note: you cannot yet access from Drupal
To stop the webserver quit the process.

# Updating
```sh
# All users can update the project dependencies using composer.
composer update
```
