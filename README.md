# SessionReaper patch for Magento Open Source and Mage-OS (CVE-2025-54236)

`bigcartenergy/module-sessionreaper-patch` is a free security patch for Magento Open Source and Mage-OS by Big Cart Energy. It closes CVE-2025-54236 ("SessionReaper") on the Magento releases that never received Adobe's fix, as a composer module: one plugin on the Web API input processor that applies the same allow-list as the official fix. No core file is modified, nothing is added to the database or the admin, and `composer remove` takes it out without a trace.

## What it fixes

CVE-2025-54236 (CVSS 3.1 score 9.1, critical; CWE-20, improper input validation) lives in `Magento\Framework\Webapi\ServiceInputProcessor`, the class that turns a REST or SOAP request body into the objects a service method expects. When it builds an object, it fills the object's constructor parameters from the request data by name, and it accepted parameters of any type. Adobe's fix, shipped in 2.4.9 and in the October 2025 patch releases, allows a constructor parameter only when its type is a simple type or an `Api\Data` type, and skips every other one.

This module applies the same allow-list, mirrored character for character, from a `before` plugin on `convertValue()`, the processor's one public entry point, which the processor also passes through for every nested value. Where the official fix skips a parameter and leaves its key in the data for the processor's setter fallback, this module removes the key, so it reaches neither the constructor nor a setter; that is its one deliberate difference, and it is the stricter of the two. One value the processor builds without that entry point is an object-typed custom attribute; for those the module resolves the attribute's type the way the processor does (the entity type of the owning object, then Magento's custom attribute type locator) and applies the allow-list to the attribute's value, or to each item of an array-typed value, so that object's constructor is covered on an unpatched core as it is by Adobe's fix. Everything a Magento store legitimately sends over its API passes exactly as on a patched core: custom attributes, extension attributes, search criteria and every `Api\Data` type, yours included. Simple types, `mixed`, the processor's unstructured-array pass-through and non-array data are never touched. A request from which nothing was removed is handed on untouched.

## Who needs it

We read Adobe's fix in the Magento Open Source code of every release below, so the table says what the code says.

| Your Magento Open Source release | Official fix | This module |
| --- | --- | --- |
| 2.3.0 to 2.3.7-p4 | None | Yes |
| 2.4.0 to 2.4.3-p3 | None | Yes |
| 2.4.4 to 2.4.4-p13 (every release of the line) | None that Adobe names: no patch release of the line carries the fix, and Adobe's hotfix article lists Magento Open Source only from 2.4.5 | Yes |
| 2.4.5 to 2.4.5-p14 (every release of the line) | Adobe's hotfix VULN-32437 only; no patch release of the line carries the fix | Yes, unless the hotfix is applied |
| 2.4.6 to 2.4.6-p12, 2.4.7 to 2.4.7-p7, 2.4.8 to 2.4.8-p2 | The hotfix, or the patch release of your line below | Yes, if you have applied neither |
| 2.4.6-p13 and later, 2.4.7-p8 and later, 2.4.8-p3 and later, 2.4.9 | Included | Not needed. Installed anyway, it is redundant and harmless: the processor applies the same allow-list a moment later |

Mage-OS ships the same Web API code as the Magento Open Source release it is based on, so that release's row applies to it; see "Tested versions" for what this version of the module was actually run on.

Adobe's bulletin for this vulnerability is APSB25-88. It lists Magento Open Source 2.4.5 to 2.4.9-alpha2 as affected; the code the fix changes is also present in 2.3.0 to 2.4.4, which Adobe does not list and no longer patches (2.3.0's version of it differs in one respect, which the 2.3.0 row below explains).

## Tested versions

A version that is not in this table has not been tested. Use at your own risk and test on staging first.

| Magento Open Source | PHP | Database | Result |
| --- | --- | --- | --- |
| 2.4.9 | 8.5 | MySQL 8.4 | Pass: install and `setup:di:compile`; REST guest checkout to a placed order, customer account create, product search with filters and sort orders, admin product save with custom and extension attributes and with Magento's own object-typed custom attribute; one SOAP call with search criteria; the guard removes a constructor parameter outside `Api\Data` over REST (single and list) and over SOAP (both `item` wrapper shapes) and logs it, on a service parameter and inside an object-typed custom attribute value of a product save (REST and SOAP); a legitimate custom attribute value saves the same with and without the module; every Magento API call behaves the same with and without the module (Adobe's fix is already in this release); removal with a REST pass afterwards; unit tests in place. Alone and together with the CosmicSting patch |
| 2.4.8 | 8.3 | MySQL 8.4 | Pass: the same checks; on this release the module is the only guard |
| 2.4.7 | 8.2 | MySQL 8.0 | Pass: the same checks; on this release the module is the only guard |
| 2.4.6 | 8.1 | MySQL 8.0 | Pass: the same checks; on this release the module is the only guard |
| 2.4.3-p3 | 7.4 | MySQL 8.0 | Pass: the same checks; on this release the module is the only guard |
| 2.3.7-p4 | 7.4 | MySQL 5.7 | Pass: the same checks; on this release the module is the only guard |
| 2.3.5-p2 ¹ | 7.3 | MySQL 5.7 | Pass: the same checks; on this release the module is the only guard |
| 2.3.0 ¹ | 7.2 | MySQL 5.7 | Pass: the same checks; on this release the module is the only guard. 2.3.0's processor copies nested constructor data without converting it, and the module's removal of the keys that fail the allow-list is what closes that path there; a nested `Api\Data` object cannot be built from a request on this release at all, with or without the module |

¹ Installed from Adobe's public release tag of that version rather than as a composer project (the public package mirror serves no 2.3 release before 2.3.7-p2); the code under test is the release's own.

Mage-OS: not tested with this version of the module. Whether a Mage-OS release already carries Adobe's fix follows the Magento Open Source release it is based on.

## Install

```
composer require bigcartenergy/module-sessionreaper-patch
bin/magento module:enable BigCartEnergy_SessionReaperPatch
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

`setup:di:compile` is needed in production mode and does no harm in developer mode. In production mode `setup:upgrade` also empties the deployed static files unless it is run with `--keep-generated`, so run `bin/magento setup:static-content:deploy` after the compile, as after any module install, or pass `--keep-generated` (the module ships no static content) and let `setup:di:compile` rebuild the generated code. The module creates no table, setting, menu item, cron job or storefront output. It is a module, not a `.patch` file, so nothing is applied to the Magento source and no patching tool is involved. A store that cannot run composer can download the release archive from the repository, which unpacks into one folder named after the repository and the release, copy that folder's contents (`registration.php` included) to `app/code/BigCartEnergy/SessionReaperPatch` and run the same `bin/magento` commands.

The module declares no dependency on any other Big Cart Energy package. It can be installed together with `bigcartenergy/module-cosmicsting-patch`; the two run in a fixed order and do not interfere.

## Verify

```
bin/magento module:status BigCartEnergy_SessionReaperPatch
```

prints "Module is enabled". Each conversion from which the module removed a key writes one line to `var/log/bigcartenergy_cve-2025-54236.log` with the UTC time, the client IP address, the request method and path, the type being built and the removed parameter names with their types:

```
bigcartenergy_sessionreaper.WARNING: time=2026-01-31T09:15:02Z ip=203.0.113.5 method=POST path=/rest/V1/example stripped type=Vendor\Module\Api\Data\ExampleInterface parameters=helper:Vendor\Module\Model\Helper
```

Magento's log handler puts its own timestamp in front of the line. The request body is never logged. A store that never receives such a request never gets the file; that is the expected state.

## Remove

```
bin/magento module:disable BigCartEnergy_SessionReaperPatch
composer remove bigcartenergy/module-sessionreaper-patch
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

Nothing is left behind except the log file, which you can delete. The production-mode note under Install applies to this `setup:upgrade` as well.

## Logging and IP addresses

Logging is on by default. IP addresses are personal data in the EU, so if your store has no basis to keep them, turn the log off with this override inside the `<config>` root of the `etc/di.xml` of a module of your own, then run `bin/magento setup:di:compile` and `bin/magento cache:clean`. Do not put it in Magento's `app/etc/di.xml`: that file belongs to the `magento/magento2-base` package and is written over when that package updates, which would switch the log back on without notice.

```xml
<type name="BigCartEnergy\SessionReaperPatch\Plugin\Webapi\ServiceInputProcessorPlugin">
    <arguments>
        <argument name="enableLog" xsi:type="boolean">false</argument>
    </arguments>
</type>
```

The guard works the same with the log off.

## Not a substitute for upgrading

This module closes one hole in a release that Adobe no longer patches; it does not fix the others, and it never will. The lasting fix is a supported release of Magento Open Source. Install the module today, plan the upgrade, and if you want help with either: stuck on an old version? We'll patch you now and upgrade you when you're ready. [bigcartenergy.com](https://bigcartenergy.com)

## After any suspected exposure

If your store ran an exposed release while the vulnerability was public, installing a fix is not the end of it: rotate the store's encryption keys, invalidate customer and admin sessions, and review the admin users, integrations and API tokens. This module does none of that for you.

## Licence

Open Software License 3.0 (OSL-3.0), the same licence as Magento Open Source. See [LICENSE.txt](https://github.com/bigcartenergy/module-sessionreaper-patch/blob/main/LICENSE.txt). The validation rule mirrors the fix in Magento Open Source (Copyright Adobe, OSL-3.0), re-implemented as a plugin; see NOTICE.

Repository: https://github.com/bigcartenergy/module-sessionreaper-patch · Issues: https://github.com/bigcartenergy/module-sessionreaper-patch/issues

Use at your own risk. Test on staging before production.

Copyright (c) Big Cart Energy. https://bigcartenergy.com
