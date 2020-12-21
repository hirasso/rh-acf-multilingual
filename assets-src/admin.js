
const $ = global.jQuery;

import Cookie from './js/cookie';
import './scss/admin.scss';

export default class ACFML {

  constructor() {
    this.initMultilingualWysiwyg();
    this.initMultilingualPostTitle();
    this.initMultilingualTermName();
    this.initLanguageTabs();
    // Cookie.set('rh-acfml-admin-language', 'en');
  }

  /**
   * Setup language switchers for multilingual acf-fields
   */
  initLanguageTabs() {
    acf.addAction('acfml/switch_language', ($field, language) => {
      if( $field.hasClass('acfml-post-title') ) {
        this.switchLanguage($('.acfml-slug'), language);
      }
    })
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
      this.switchLanguage($('.acfml-multilingual-field:not(.acfml-slug)'), language);
    })
  }

  /**
   * Switches the language for an .acfml-multilingual-field
   * @param {jQuery Object} $fields 
   * @param {String} language 
   */
  switchLanguage($fields, language) {
    $fields.each((i, el) => {
      const $el = $(el);
      const $childFields = $el.find('.acf-input:first').find(".acfml-field");
      const $tabs = $el.find('.acfml-tab');
      $tabs.removeClass('is-active');
      $tabs.filter(`[data-language=${language}]`).addClass('is-active');
      $childFields.removeClass('is-visible');
      $childFields.filter(`[data-name=${language}]`).addClass('is-visible');
      acf.doAction('acfml/switch_language', $el, language);
    })
  }

  /**
   * Prepare multilingual WYSIWYG fields
   */
  initMultilingualWysiwyg() {
    acf.addFilter('wysiwyg_tinymce_settings', (init, id, field) => {
      const $parent = field.$el.parents('.acfml-multilingual-field');
      if( !$parent.length ) return init;
      const fieldNameClass = $parent.attr('data-name').split('_').join('-');
      init.body_class += ` acf-wysiwyg--${fieldNameClass}`;
      return init;
    })
  }

  /**
   * Multilingual Post Titles
   */
  initMultilingualPostTitle() {
    acf.addAction(`ready_field/key=field_acfml_post_title_${acfml.defaultLanguage}`, $field => {
      $('#titlediv').remove();
      if( !acfml.isMobile && !$field.val() ) $field.$input().focus();
    });
    acf.addAction(`ready_field/key=field_acfml_slug`, $field => {
      // $('.postbox#slugdiv').remove();
    });
  }

  /**
   * Multilingual Term Names
   */
  initMultilingualTermName() {
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