# Maintenance

This documents the activities for maintenance of the Schemata project.

## Resources

* Use Drupal.org for project homepage, documentation, canonical code hosting,
  support requests, and security.
* Use Github for development via Pull Requests and CI processes.

## Routine Activities

* Use `composer outdated --direct` and `composer update <project>`
  to ensure dependencies are up-to-date.
    * Update the composer require --dev line in .travis.yml so the Travis CI
      testing pulls the updated library versions.
* Use `composer run-script phpcbf` to fix many classes of phpcs violation.

## Policy/Guidelines

* Treat the README as both a deeper orientation of the project than is provided
  by the Drupal.org project page, as well as an initial entrypoint to the project
  for Github explorers.
* Thank code contributors for any contribution first, and again after committing
  or merging the contribution.
* If tests fail do not merge.
* Do not commit composer.lock
