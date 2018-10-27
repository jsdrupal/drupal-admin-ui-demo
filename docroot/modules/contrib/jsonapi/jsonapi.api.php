<?php

/**
 * @file
 * Documentation related to JSON API.
 */

/**
 * @defgroup jsonapi_normalizer_architecture JSON API Normalizer Architecture
 * @{
 *
 * @section overview Overview
 * The JSON API module is a Drupal-centric implementation of the JSON API
 * specification. By its own definition, the JSON API specification is "is a
 * specification for how a client should request that resources be fetched or
 * modified, and how a server should respond to those requests. [It] is designed
 * to minimize both the number of requests and the amount of data transmitted
 * between clients and servers. This efficiency is achieved without compromising
 * readability, flexibility, or discoverability."
 *
 * While "Drupal-centric", the JSON API module is committed to strict compliance
 * with the specification. Wherever possible, the module attempts to implement
 * the specification in a way which is compatible and familiar with the patterns
 * and concepts inherent to Drupal. However, when "Drupalisms" cannot be
 * reconciled with the specification, the module will always choose the
 * implementation most faithful to the specification.
 *
 * @see http://jsonapi.org/
 *
 *
 * @section resources Resources
 * Every unit of data in the specification is a "resource". The specification
 * defines how a client should interact with a server to fetch and manipulate
 * these resources.
 *
 * The JSON API module maps every entity type + bundle to a resource type.
 * Since the specification does not have a concept of resource type inheritance
 * or composition, the JSON API module implements different bundles of the same
 * entity type as *distinct* resource types.
 *
 * While it is theoretically possible to expose arbitrary data as resources, the
 * JSON API module only exposes resources from (config and content) entities.
 * This eliminates the need for another abstraction layer in order implement
 * certain features of the specification.
 *
 *
 * @section relationships Relationships
 * The specification defines semantics for the "relationships" between
 * resources. Since the JSON API module defines every entity type + bundle as a
 * resource type and does not allow non-entity resources, it is able to use
 * entity references to automatically define and represent the relationships
 * between all resources.
 *
 *
 * @section normalizers Normalizers
 * The JSON API module reuses as many of Drupal core's Serialization module's
 * normalizers as possible.
 *
 * The JSON API specification requires special handling for resources
 * (entities), relationships between those resources (entity references) and
 * resource IDs (entity UUIDs), it must override some of the Serialization
 * module's normalizers for entities and fields (most notably, entity
 * reference fields).
 *
 * This means that modules which provide additional field types must implement
 * normalizers at the "DataType" plugin level. This is a level below "FieldType"
 * plugins. Normalizers which are not implemented at this level will not be used
 * by the JSON API module.
 *
 * A benefit of implementing normalizers at this lower level is that they will
 * work automatically for both the JSON API module and core's REST module.
 *
 *
 * @section api API
 * The JSON API module provides an HTTP API that adheres to the JSON API
 * specification.
 *
 * The JSON API module provides *no PHP API to modify its behavior.* It is
 * designed to have zero configuration.
 *
 * - Adding new resources/resource types is unsupported: all entities/entity
 *   types are exposed automatically. If you want to expose more data via the
 *   JSON API module, the data must be defined as entity. See the "Resources"
 *   section.
 * - Custom field normalization is not supported; only normalizers at the
 *   "DataType" plugin level are supported (these are a level below field
 *   types).
 * - All available authentication mechanisms are allowed.
 *
 * The JSON API module does provide a PHP API to generate a JSON API
 * representation of entities:
 *
 * @code
 * \Drupal::service('jsonapi.entity.to_jsonapi')->serialize($entity)
 * @endcode
 *
 *
 * @section tests Test Coverage
 * The JSON API module comes with extensive unit and kernel tests. But most
 * importantly for end users, it also has comprehensive integration tests. These
 * integration tests are designed to:
 *
 * - ensure a great DX (Developer Experience)
 * - detect regressions and normalization changes before shipping a release
 * - guarantee 100% of Drupal core's entity types work as expected
 *
 * The integration tests test the same common cases and edge cases using
 * @code \Drupal\Tests\jsonapi\Functional\ResourceTestBase @endcode, which is a
 * base class subclassed for every entity type that Drupal core ships with. It
 * is ensured that 100% of Drupal core's entity types are tested thanks to
 * @code \Drupal\Tests\jsonapi\Functional\TestCoverageTest @endcode.
 *
 * Custom entity type developers can get the same assurances by subclassing it
 * for their entity types.
 *
 *
 * @section bc Backwards Compatibility
 * PHP API: there is no PHP API. This means that this module's implementation
 * details are entirely free to change at any time.
 *
 * Please note, *normalizers are internal implementation details.* While
 * normalizers are services, they are *not* to be used directly. This is due to
 * the design of the Symfony Serialization component, not because the JSON API
 * module wanted to publicly expose services.
 *
 * HTTP API: URLs and JSON response structures are considered part of this
 * module's public API. However, inconsistencies with the JSON API specification
 * will be considered bugs. Fixes which bring the module into compliance with
 * the specification are *not* guaranteed to be backwards compatible.
 *
 * What this means for developing consumers of the HTTP API is that *clients
 * should be implemented from the specification first and foremost.* This should
 * mitigate implicit dependencies on implementation details or inconsistencies
 * with the specification that are specific to this module.
 *
 * To help develop compatible clients, every response indicates the version of
 * the JSON API specification used under its "jsonapi" key. Future releases
 * *may* increment the minor version number if the module implements features of
 * a later specification. Remember that he specification stipulates that future
 * versions *will* remain backwards compatible as only additions may be
 * released.
 *
 * @see http://jsonapi.org/faq/#what-is-the-meaning-of-json-apis-version
 *
 * Tests: subclasses of base test classes may contain BC breaks between minor
 * releases, to allow minor releases to A) comply better with the JSON API spec,
 * B) guarantee that all resource types (and therefore entity types) function as
 * expected, C) update to future versions of the JSON API spec.
 *
 * @}
 */
