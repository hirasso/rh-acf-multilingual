(function () {var g=this;var d={};const a=window.jQuery;class c{constructor(){}static get(e,t=null){var a=document.cookie.match("(^|;) ?"+e+"=([^;]*)(;|$)");return a?a[2]:t}static set(e,t,a=1e4){var $=new Date;$.setTime($.getTime()+864e5*a),document.cookie=e+"="+t+";path=/;expires="+$.toGMTString()}static delete(e){c.set(e,"",0)}}window.rahCookie=c;const b=g.jQuery;class f{constructor(){this.initMultilingualWysiwyg(),this.initMultilingualPostTitle(),this.initMultilingualTermName(),this.initLanguageTabs(),this.initValidationHandling()}initLanguageTabs(){acf.addAction("acfml/switch_language",(a,t)=>{a.data("acfml-ui-listen-to")||this.switchLanguage(b(`[data-acfml-ui-listen-to="${a.data("name")}"]`),t)}),b(document).on("click",".acfml-tab",a=>{a.preventDefault();const t=b(a.target);t.blur();const e=t.attr("data-language");this.switchLanguage(t.parents(".acfml-ui-style--tabs:first"),e)}),b(document).on("dblclick",".acfml-tab",a=>{a.preventDefault();const t=b(a.target).attr("data-language");this.switchLanguage(b(".acfml-ui-style--tabs:not([data-acfml-ui-listen-to])"),t)}),b("form#post").one("submit",()=>this.storeActiveLanguageTabs()),window.addEventListener("beforeunload",()=>this.storeActiveLanguageTabs()),this.removeFromStore("acfml_language_tabs")}switchLanguage(a,t){a.each((a,e)=>{const i=b(e),l=i.find(".acf-input:first").find(".acfml-field"),s=i.find(".acfml-tab");s.removeClass("is-active"),s.filter(`[data-language=${t}]`).addClass("is-active"),l.removeClass("acfml-is-visible");const n=l.filter(`[data-name=${t}]:first`);n.addClass("acfml-is-visible"),n.find(".acf-editor-wrap.delay").trigger("mousedown"),i.attr("data-acfml-language",t),acf.doAction("acfml/switch_language",i,t)})}initMultilingualWysiwyg(){acf.addFilter("wysiwyg_tinymce_settings",(a,t,e)=>{const i=e.$el.parents(".acfml-multilingual-field");if(!i.length)return a;const l=i.attr("data-name").split("_").join("-");return a.body_class+=` acf-wysiwyg--${l}`,a})}initMultilingualPostTitle(){acf.addAction("ready_field/name=acfml_post_title",a=>{b("#titlediv").remove(),b("[data-setting=\"title\"]").remove()}),acf.addAction(`ready_field/key=field_acfml_post_title_${acfml.defaultLanguage}`,a=>{acfml.isMobile||a.val()||a.$input().focus()}),acf.addAction("ready_field/key=field_acfml_slug",a=>{})}initMultilingualTermName(){acf.addAction("ready_field/key=field_acfml_term_name",a=>{b(".form-field.term-name-wrap").remove()})}storeActiveLanguageTabs(){let a={};b(".acfml-multilingual-field").each((t,e)=>{const i=b(e).attr("data-key"),l=b(e).find(".acfml-field.acfml-is-visible").attr("data-name");a[i]=l}),this.addToStore("acfml_language_tabs",a)}initValidationHandling(){acf.addAction("invalid_field",a=>{a.data.required&&a.$el.hasClass("acfml-field")&&!a.val()&&this.switchLanguage(a.$el.parents(".acfml-multilingual-field"),a.data.name)})}addToStore(a,t){c.set(this.getStorageKey(a),JSON.stringify(t),1)}removeFromStore(a){c.delete(this.getStorageKey(a))}getFromStore(a){let t=c.get(this.getStorageKey(a));return t?JSON.parse(t):t}getStorageKey(a){return`${a}_${acfml.cookieHashForCurrentUri}`}}d.default=f,new f;if(typeof exports==="object"&&typeof module!=="undefined"){module.exports=d}else if(typeof define==="function"&&define.amd){define(function(){return d})}})();