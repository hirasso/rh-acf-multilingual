<?php foreach($value->languages as $language): ?>
<?php if($language->is_default): ?>
<link hreflang="x-default" href="<?= $language->url ?>" rel="alternate" />
<?php endif; ?>
<link hreflang="<?= $language->slug ?>" href="<?= $language->url ?>" rel="alternate" />
<?php endforeach; ?>