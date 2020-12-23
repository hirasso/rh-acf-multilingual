<?php ob_start(); ?>
  <?php foreach( $value->languages as $language ) : ?>
  <li 
    class="<?= $value->element_class ?>_item <?= implode(' ', $language->element_classes) ?>"
    data-language="<?= $language->slug ?>">
    <a 
      class="<?= $value->element_class ?>_link <?= implode(' ', $language->element_classes) ?>" 
      href="<?= $language->url ?>"
      data-language="<?= $language->slug ?>">
      <?= $language->display_name ?>
    </a>
  </li>
  <?php endforeach; ?>
<?php $items = ob_get_clean(); ?>
<?php switch( $value->args->format ) : case 'list': ?>
<ul class="<?= $value->element_class ?>"><?= $items ?></ul>
<?php break; case 'list_items': ?>
<?= $items ?>
<?php break; endswitch; ?>
