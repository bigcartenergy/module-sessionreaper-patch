# Changelog

All notable changes to `bigcartenergy/module-sessionreaper-patch` by Big Cart Energy.

## 1.0.0 - 2026-11-07

### Added

- A `before` plugin on `Magento\Framework\Webapi\ServiceInputProcessor::convertValue()` that removes from the request data every key naming a constructor parameter of the target class whose type is neither a simple type nor an `Api\Data` type, the allow-list of Adobe's fix mirrored character for character. The official fix skips such a parameter and leaves the key for the setters; this module removes the key, its one deliberate difference. Array types are handled item by item, the SOAP `item` wrapper included. Simple types, `mixed`, the unstructured-array pass-through and non-array data are never touched.
- The same allow-list for an object-typed custom attribute value, which the processor builds without `convertValue()`: the custom attributes container is recognised exactly as the processor recognises it (every key whose `SimpleDataObjectConverter::snakeCaseToUpperCamelCase()` form is `CustomAttributes`, not a fixed pair of spellings), the attribute's type is resolved as the processor resolves it (the entity type of the owning object, then the custom attribute type locator) and the value, or each item of an array-typed value, loses every key that names a constructor parameter not on the allow-list, logged under the attribute's path. Simple and `mixed` attributes, unknown codes, scalar values, owners without an entity type and a locator failure are left to the processor.
- One log line per conversion with removed keys in `var/log/bigcartenergy_cve-2025-54236.log`: UTC time, client IP address, request method and path, the type and the removed parameter names with their types. Never the request body. The `enableLog` argument of the plugin in `di.xml` turns it off.
- Unit tests with harmless fixture classes, runnable standalone or through Magento's `dev/tests/unit`.
- README: the log switch is a small module of the store's own whose `module.xml` declares a `<sequence>` on this module, so its `enableLog` override loads after this module's value and wins the configuration merge; the README gives the three files and the check that confirms the switch took.

### Tested

- Magento Open Source 2.4.9 (PHP 8.5, MySQL 8.4), 2.4.8 (PHP 8.3, MySQL 8.4), 2.4.7 (PHP 8.2, MySQL 8.0), 2.4.6 (PHP 8.1, MySQL 8.0), 2.4.3-p3 (PHP 7.4, MySQL 8.0), 2.3.7-p4 (PHP 7.4, MySQL 5.7), 2.3.5-p2 (PHP 7.3, MySQL 5.7) and 2.3.0 (PHP 7.2, MySQL 5.7): install and compile, the REST checkout, customer, search and product-save flows (Magento's own object-typed custom attribute included), one SOAP call with search criteria, the removal of a constructor parameter outside `Api\Data` over REST and SOAP with its log line, on a service parameter and inside an object-typed custom attribute value, a legitimate custom attribute value saved the same with and without the module, identical behaviour of every Magento API flow with and without the module, removal, and the unit suite on PHPUnit 6.5, 9, 10 and 12. Alone and together with `bigcartenergy/module-cosmicsting-patch`. The README's table gives the details per release.
