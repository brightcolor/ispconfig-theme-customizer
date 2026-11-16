# Shell tests

`run.sh` runs the shared template generator (`lib/shell.sh`) against the stock
shell templates of ISPConfig 3.3.1p1 and needs no panel and no root:

```bash
bash tests/shell/run.sh
```

## Fixtures

`fixtures/3.3.1p1/` holds `main.tpl.htm` and `main_login.tpl.htm` exactly as
ISPConfig 3.3.1p1 ships them in `interface/web/themes/default/templates/`, taken
from the tagged source:

- `https://git.ispconfig.org/ispconfig/ispconfig3/-/raw/3.3.1p1/interface/web/themes/default/templates/main.tpl.htm`
- `https://git.ispconfig.org/ispconfig/ispconfig3/-/raw/3.3.1p1/interface/web/themes/default/templates/main_login.tpl.htm`

They are ISPConfig's work, under the BSD licence in `fixtures/3.3.1p1/LICENSE`
(from the same tag).

## Expected output

`expected/classic/` is what upstream `v3.6.0`'s `generate_classic_templates()`
produced from those fixtures. `run.sh` compares the shared generator's classic
output with it byte for byte. `make_expected.sh <tag>` rebuilds the files from
that tag's own installer; regenerate only to adopt a reviewed upstream change to
classic's shell.
