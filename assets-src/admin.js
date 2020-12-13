
const $ = global.jQuery;

import Cookie from './js/cookie';
import './scss/admin.scss';

export default class ACFML {

  constructor() {
    $(document).ready(() => this.onDocReady());
    this.acfWysiwyg();
    // $('#edit-slug-box').clone(true).appendTo($('.acfml-title .acf-fields .acf-input:first'));
    // Cookie.set('rh-acfml-admin-language', 'en');
  }

  /**
   * This runs on doc ready
   */
  onDocReady() {
    this.initTitleField();
    this.initLanguageTabs();
  }

  /**
   * Setup language switchers for translatable acf-fields
   */
  initLanguageTabs() {
    $(document).on('click', '.acfml-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      $el.blur();
      const language = $el.attr('data-language');
      this.switchLanguage($el.parents('.acfml-translatable-field:first'), language);
    });
    $(document).on('dblclick', '.acfml-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      const language = $el.attr('data-language');
      this.switchLanguage($('.acfml-translatable-field'), language);
    })
  }

  /**
   * Switches the language for an .acfml-translatable-field
   * @param {jQuery Object} $el 
   * @param {String} language 
   */
  switchLanguage($el, language) {
    const $fields = $el.find('.acf-input:first').find(".acfml-field");
    const $tabs = $el.find('.acfml-tab');
    $tabs.removeClass('is-active');
    $tabs.filter(`[data-language=${language}]`).addClass('is-active');
    $fields.removeClass('is-visible');
    $fields.filter(`[data-name=${language}]`).addClass('is-visible');
  }

  /**
   * Prepare translatable WYSIWYG fields
   */
  acfWysiwyg() {
    acf.addFilter('wysiwyg_tinymce_settings', (init, id, field) => {
      const $parent = field.$el.parents('.acfml-translatable-field');
      if( !$parent.length ) return init;
      const fieldNameClass = $parent.attr('data-name').split('_').join('-');
      init.body_class += ` acf-wysiwyg--${fieldNameClass}`;
      return init;
    })
  }

  initTitleField() {
    const $titleField = $('.acf-field-acfml-title');
    if( !$titleField.length ) return;
    $('.form-field.term-name-wrap').remove();
  }

  /**
   * Stores something in session storage
   * 
   * @param {string} key 
   * @param {mixed} value 
   */
  addToStore(key, value) {
    sessionStorage.setItem(this.getStorageKey(key), JSON.stringify(value));
  }

  /**
   * Removes something from session storage
   * @param {string} key 
   */
  removeFromStore(key) {
    sessionStorage.removeItem(this.getStorageKey(key));
  }

  /**
   * Gets something from store
   * @param {string} key 
   */
  getFromStore(key) {
    let value = sessionStorage.getItem(this.getStorageKey(key));
    return value ? JSON.parse(value) : value;
  }

  /**
   * Get storage key for scrollTop
   */
  getStorageKey(key) {
    var path = window.location.pathname;
    return key + ":" + path.replace(/^\/+/g, '').split('/').join('-');
  }

}

new ACFML();