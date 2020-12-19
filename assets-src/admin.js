
const $ = global.jQuery;

import Cookie from './js/cookie';
import './scss/admin.scss';

export default class ACFML {

  constructor() {
    this.initTranslatableWysiwyg();
    this.initTranslatablePostTitle();
    this.initTranslatableTermName();
    this.initLanguageTabs();
    // Cookie.set('rh-acfml-admin-language', 'en');
  }

  /**
   * Setup language switchers for multilingual acf-fields
   */
  initLanguageTabs() {
    $(document).on('click', '.acfml-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      $el.blur();
      const language = $el.attr('data-language');
      this.switchLanguage($el.parents('.acfml-multilingual-field:first'), language);
    });
    $(document).on('dblclick', '.acfml-tab', e => {
      e.preventDefault();
      const $el = $(e.target);
      const language = $el.attr('data-language');
      this.switchLanguage($('.acfml-multilingual-field'), language);
    })
  }

  /**
   * Switches the language for an .acfml-multilingual-field
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
   * Prepare multilingual WYSIWYG fields
   */
  initTranslatableWysiwyg() {
    acf.addFilter('wysiwyg_tinymce_settings', (init, id, field) => {
      const $parent = field.$el.parents('.acfml-multilingual-field');
      if( !$parent.length ) return init;
      const fieldNameClass = $parent.attr('data-name').split('_').join('-');
      init.body_class += ` acf-wysiwyg--${fieldNameClass}`;
      return init;
    })
  }

  /**
   * Translatable Post Titles
   */
  initTranslatablePostTitle() {
    acf.addAction(`ready_field/key=field_acfml_post_title_${acfml.defaultLanguage}`, $field => {
      $('#titlediv').remove();
      // $field.$input().attr('id', 'title');
      if( !acfml.isMobile && !$field.val() ) $field.$input().focus();
    });
    acf.addAction(`ready_field/key=field_acfml_slug`, $field => {
      // $('.postbox#slugdiv').remove();
    });
  }

  /**
   * Translatable Term Names
   */
  initTranslatableTermName() {
    acf.addAction('ready_field/key=field_acfml_term_name', $field => {
      $('.form-field.term-name-wrap').remove();
    })
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