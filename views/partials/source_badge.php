<?php
$labels = [
    'manual' => ['Added manually', 'secondary', 'pencil'],
    'discovered' => ['Discovered from provider', 'info', 'cloud-download'],
    'created' => ['Created in panel', 'primary', 'plus-circle'],
];
[$label, $tone, $icon] = $labels[$source] ?? [ucfirst((string) $source), 'secondary', 'dot'];
?>
<span class="badge text-bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis border border-<?= $tone ?>-subtle"><i class="bi bi-<?= $icon ?> me-1"></i><?= e($label) ?></span>
