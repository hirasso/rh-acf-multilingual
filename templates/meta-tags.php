<?php foreach($value->languages as $language): ?>
<?php if($language->is_default): ?>
<link hreflang="x-default" href="<?= $language->url ?>" rel="alternate" />
<?php endif; ?>
<link hreflang="<?= $language->iso ?>" href="<?= $language->url ?>" rel="alternate" />
<?php endforeach; ?>