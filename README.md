# What?

Making testing the new react admin UI as simple as possible.

# Requirements
* PHP 5.5.9 or greater
* PHP's pdo_sqlite extension installed. You can use `php -m` to check.

# Installation

```sh
# This command will take some time. When it finishes it will ask you if you
# want to delete the VCS information. If you say no then updating the project
# is simple.
composer create-project jsdrupal/test-project -s dev

composer run dev-site
```

Visit the url displayed in the message on the command line. For example:
http://localhost:62665/vfancy

# Updating the project
```sh
# If you choose to keep the VCS files when running composer create-project.
git pull

# All users can update the project dependencies using composer.
composer udpate
```
