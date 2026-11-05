# Changelog

All notable changes to `bigcartenergy/module-sessionreaper-patch` by Big Cart Energy.

## 1.0.0 - Unreleased

### Added

- A `before` plugin on `Magento\Framework\Webapi\ServiceInputProcessor::convertValue()` that removes from the request data every key naming a constructor parameter of the target class whose type is neither a simple type nor an `Api\Data` type, the allow-list of Adobe's fix mirrored character for character. The official fix skips such a parameter and leaves the key for the setters; this module removes the key, its one deliberate difference. Array types are handled item by item, the SOAP `item` wrapper included. Simple types, `mixed`, the unstructured-array pass-through and non-array data are never touched.
- One log line per conversion with removed keys in `var/log/bigcartenergy_cve-2025-54236.log`: UTC time, client IP address, request method and path, the type and the removed parameter names with their types. Never the request body. The `enableLog` argument of the plugin in `di.xml` turns it off.
- Unit tests with harmless fixture classes, runnable standalone or through Magento's `dev/tests/unit`.

### Tested

- Magento Open Source 2.4.9 (PHP 8.5, MySQL 8.4), 2.4.8 (PHP 8.3, MySQL 8.4) and 2.3.7-p4 (PHP 7.4, MySQL 5.7): install and compile, the REST checkout, customer, search and product-save flows, one SOAP call with search criteria, the removal of a constructor parameter outside `Api\Data` over REST and SOAP with its log line, identical behaviour of every Magento API flow with and without the module, removal, and the unit suite on PHPUnit 9, 10 and 12. Alone and together with `bigcartenergy/module-cosmicsting-patch`.
