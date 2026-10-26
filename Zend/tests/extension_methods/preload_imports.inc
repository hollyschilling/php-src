<?php
/* Only the consumer is preloaded. The extension itself is declared at
 * request time: registration of extensions from preloaded files is a
 * known open issue of the base RFC's registry (its function pointers do
 * not survive preload persistence), independent of import visibility. */
require __DIR__ . '/preload_consumer.inc';
