# What?

Making testing the new react admin UI as simple as possible.

# Requirements
* PHP 5.5.9 or greater
* PHP's pdo_sqlite extension installed. You can use `php -m` to check.

# Installation

```sh
# This command will take some time.
composer create-project jsdrupal/test-project -s dev --prefer-dist
```

# Usage
```sh
composer run dev-site
```

Visit the url displayed in the message on the command line. For example:
http://localhost:62665/vfancy

To stop the webserver quit the process.

# Updating
```sh
# All users can update the project dependencies using composer.
composer udpate
```
