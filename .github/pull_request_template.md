## What does this PR do?

<!-- A short description of the change and why it's needed. Link any related issue. -->

## Checklist

- [ ] `vendor/bin/phpunit` passes
- [ ] `vendor/bin/pint` reports no style issues
- [ ] Behaviour changes are covered by a test
- [ ] `CHANGELOG.md` updated under the unreleased section (for user-facing changes)

## Adding a DVLA value?

<!-- Delete this section if it doesn't apply. -->

- [ ] Kept the DVLA spelling verbatim
- [ ] Added the enum case + `lang/en/enums.php` key + `label()` case (or the string in `KnownMake::MAKES`)
- [ ] Noted how the value was observed (real registration response, DVLA's published list, etc.)
