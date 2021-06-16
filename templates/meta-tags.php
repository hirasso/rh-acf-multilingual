<?php foreach($value->urls as $lang => $url): ?>
<?php if($lang === $value->default_language): ?>
<link hreflang="x-default" href="<?= $url ?>" rel="alternate" />
<?php endif; ?>
<link hreflang="<?= $lang ?>" href="<?= $url ?>" rel="alternate" />
<?php endforeach; ?>