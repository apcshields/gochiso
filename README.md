# gochiso
A set of scripts that tries to find article availability online.

## Setup
At a minimum, you probably want to copy `includes/config_default.inc` to `includes/config.inc` and add a `WSKEY` and `INSTITUTION_ID` for the WorldCat knowledge base API (https://www.oclc.org/developer/develop/web-services/worldcat-knowledge-base-api.en.html).

Then point a script at `discovery.php` and/or `oadoi.php` with OpenURL parameters describing the resource you want to look up.
