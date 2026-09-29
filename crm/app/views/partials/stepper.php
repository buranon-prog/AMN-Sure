<?php
/** @var array $steps (code => label) @var string $current @var array $stopped (terminal codes that stop the flow) */
$codes = array_keys($steps);
$idx = array_search($current, $codes, true);
$isStopped = in_array($current, $stopped ?? [], true);
?>
<div class="stepper">
  <?php foreach ($codes as $i => $code):
      $cls = '';
      if ($idx !== false) {
          if ($i < $idx) $cls = 'done';
          elseif ($i === $idx) $cls = ($i === count($codes) - 1) ? 'done' : 'current';
      }
      ?>
    <div class="step <?= $cls ?>"><span class="dot"></span><?= e($steps[$code]) ?></div>
  <?php endforeach; ?>
  <?php if ($isStopped): ?>
    <div class="step stopped"><span class="dot"></span><?= e(label('status', $current)) ?></div>
  <?php endif; ?>
</div>
