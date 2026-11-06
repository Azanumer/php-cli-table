# PHP CLI Table

A dependency-free ASCII/Unicode table renderer for CLI tools — build readable `wp-cli`-style output without pulling in a console library.

## Usage

```php
require_once 'src/CliTable.php';

use Azanumer\CliTable;

$table = new CliTable();
$table->setHeaders(array('Plugin', 'Version', 'Active installs'));
$table->setAlignments(array('left', 'center', 'right'));
$table->addRow(array('akismet', '5.3', '6,000,000+'));
$table->addRow(array('woocommerce', '9.3.1', '5,000,000+'));

echo $table->render();
```

Output:

```
+-------------+---------+-----------------+
| Plugin      | Version | Active installs |
+-------------+---------+-----------------+
| akismet     |   5.3   |      6,000,000+ |
| woocommerce |  9.3.1  |      5,000,000+ |
+-------------+---------+-----------------+
```

## Features

- **Border styles**: `ascii` (`+ - |`), `light` (Unicode `┌ ─ │`), or `none` (space-separated columns, handy for piping).
- **Per-column alignment**: left, right, or center.
- **Cell wrapping**: `setMaxCellWidth()` wraps long cells onto extra lines (word-aware; over-long words are hard-split so nothing is ever lost).
- **Multibyte-aware**: column widths use `mb_strwidth()` when mbstring is available, so CJK and accented text line up.
- **Multi-line cells**: embedded newlines split into extra lines automatically.
- **Fluent API**: every setter returns `$this`.

Run the demo:

```bash
php examples/table-example.php
```

## Requirements

- PHP 7.4+ (mbstring recommended, not required)

## License

MIT — see [LICENSE](LICENSE).
