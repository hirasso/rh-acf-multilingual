<select class="<?= $value->element_class ?>" id="<?= $value->element_id ?>">
  <?php foreach( $value->languages as $language ) : ?>
  <option value="<?= $language->slug ?>" <?= selected($language->is_current) ?>><?= $language->display_name ?></option>
  <?php endforeach; ?>
</select>
<script type="text/javascript" id="acfml-language-switcher-script">
  document.querySelector("#<?= $value->element_id ?>").addEventListener('change', function(e) {
    var languages = <?= json_encode( array_combine( array_column($value->languages, 'slug'), array_column($value->languages, 'url') ) ) ?>;
    location.href = languages[e.target.value];
  });
</script>
