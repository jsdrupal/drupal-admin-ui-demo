# JSON API
The jsonapi module exposes a [JSON API](http://jsonapi.org/) implementation for data stored in Drupal.

The JSON API specification supports [_extensions_](http://jsonapi.org/extensions/). The following extensions are
supported in this JSON API implementation:

1. [Partial Success](https://gist.github.com/e0ipso/732712c3e573a6af1d83b25b9f0269c8), for when a resource collection is retrieved and only a subset of resources is accessible.
2. [Fancy Filters](https://gist.github.com/e0ipso/efcc4e96ca2aed58e32948e4f70c2460), to specify the filter strategy exposed by this module.

## Installation

Install the module as every other module.

## Compatibility

This module is compatible with Drupal core 8.3.x and higher.

## Configuration

Unlike the core REST module JSON API doesn't really require any kind of configuration by default.

## Usage

The jsonapi module exposes both config and content entity resources. On top of that it exposes one resource per bundle per entity. The default format appears like: `/jsonapi/{entity_type}/{bundle}/{uuid}?_format=api_json`

The list of endpoints then looks like the following:
* `/jsonapi/node/article`: Exposes a collection of article content
* `/jsonapi/node/article/{UUID}`: Exposes an individual article
* `/jsonapi/block`: Exposes a collection of blocks
* `/jsonapi/block/{block}`: Exposes an individual block

## Development usage

It is also possible to obtain the JSON API representation of a supported entity:

  ```
  // For a given $entity object.
  $nested_array = \Drupal::service('jsonapi.entity.to_jsonapi')->normalize($entity);
  ```

Should it be needed, the raw string itself can be obtained:

  ```
  // For a given $entity object.
  $json_string = \Drupal::service('jsonapi.entity.to_jsonapi')->serialize($entity);
  ```
