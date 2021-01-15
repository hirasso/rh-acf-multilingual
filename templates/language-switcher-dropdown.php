<select class="<?= $value->element_class ?>" id="<?= $value->element_id ?>">
  <?php foreach( $value->languages as $language ) : ?>
  <option value="<?= $language->slug ?>" <?= selected($language->is_current) ?>><?= $language->display_name ?></option>
  <?php endforeach; ?>
</select>
<script type="text/javascript" id="acfml-language-switcher-script">
  document.querySelector("#<?= $value->element_id ?>").addEventListener('change', function(e) {
    var languages = <?= json_encode( $value->languages_slugs_urls ) ?>;
    location.href = languages[e.target.value];
  });
</script>
