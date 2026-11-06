<?php
/**
 * CliTable demo — run: php examples/table-example.php
 */

require_once __DIR__ . '/../src/CliTable.php';

use Azanumer\CliTable;

// 1. ASCII borders, right-aligned numbers.
$table = new CliTable();
$table->setHeaders(array('Plugin', 'Version', 'Active installs'));
$table->setAlignments(array('left', 'center', 'right'));
$table->addRows(array(
    array('akismet', '5.3', '6,000,000+'),
    array('woocommerce', '9.3.1', '5,000,000+'),
    array('contact-form-7', '6.0.0', '5,000,000+'),
));
echo $table->render();
echo "\n";

// 2. Unicode light borders + long-cell wrapping.
$wrapped = new CliTable();
$wrapped->setBorderStyle('light');
$wrapped->setMaxCellWidth(32);
$wrapped->setHeaders(array('Task', 'Notes'));
$wrapped->addRow(array('Nightly backup', 'mysqldump + gzip to S3, keeps 30 days, emails on failure'));
$wrapped->addRow(array('Uptime check', 'GET / every 5 minutes, alerts on two consecutive failures'));
echo $wrapped->render();
echo "\n";

// 3. No borders — plain space-separated columns for piping into other tools.
$plain = new CliTable();
$plain->setBorderStyle('none');
$plain->setHeaders(array('Domain', 'SSL days left'));
$plain->addRow(array('example.com', '41'));
$plain->addRow(array('shop.example.com', '12'));
echo $plain->render();
