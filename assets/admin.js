(function () {function d(a){return a&&a.__esModule?{d:a.default}:{d:a}}var o=this;var g={};var h={};function p(a,c){if(!(a instanceof c))throw new TypeError("Cannot call a class as a function")}h=p;var j={};function k(e,r){for(var $=0;$<r.length;$++){var a=r[$];a.enumerable=a.enumerable||!1,a.configurable=!0,"value"in a&&(a.writable=!0),Object.defineProperty(e,a.key,a)}}function q(e,r,$){return r&&k(e.prototype,r),$&&k(e,$),e}j=q;var a=window.jQuery,f=function(){function e(){var $fcMS$$interop$default=d(h);$fcMS$$interop$default.d(this,e)}var $P8NW$$interop$default=d(j);return $P8NW$$interop$default.d(e,null,[{key:"get",value:function(e){var a=arguments.length>1&&void 0!==arguments[1]?arguments[1]:null,t=document.cookie.match("(^|;) ?"+e+"=([^;]*)(;|$)");return t?t[2]:a}},{key:"set",value:function(e,a){var t=arguments.length>2&&void 0!==arguments[2]?arguments[2]:1e4,r=new Date;r.setTime(r.getTime()+864e5*t),document.cookie=e+"="+a+";path=/;expires="+r.toGMTString()}},{key:"delete",value:function(a){e.set(a,"",0)}}]),e}();window.rahCookie=f;var b=o.jQuery,m=function(){function a(){var $fcMS$$interop$default=d(h);$fcMS$$interop$default.d(this,a),this.initMultilingualWysiwyg(),this.initMultilingualPostTitle(),this.initMultilingualTermName(),this.initLanguageTabs(),this.initValidationHandling()}var $P8NW$$interop$default=d(j);return $P8NW$$interop$default.d(a,[{key:"initLanguageTabs",value:function(){var a=this;acf.addAction("acfml/switch_language",function(t,e){t.data("acfml-ui-listen-to")||a.switchLanguage(b("[data-acfml-ui-listen-to=\"".concat(t.data("name"),"\"]")),e)}),b(document).on("click",".acfml-tab",function(t){t.preventDefault();var e=b(t.target);e.blur();var i=e.attr("data-language");a.switchLanguage(e.parents(".acfml-multilingual-field:first"),i)}),b(document).on("dblclick",".acfml-tab",function(t){t.preventDefault();var e=b(t.target).attr("data-language");a.switchLanguage(b(".acfml-multilingual-field:not([data-acfml-ui-listen-to])"),e)}),b("form#post").one("submit",function(t){return a.beforeSubmitPostForm(t)}),this.removeFromStore("acfml_language_tabs")}},{key:"switchLanguage",value:function(a,t){a.each(function(a,e){var i=b(e),l=i.find(".acf-input:first").find(".acfml-field"),n=i.find(".acfml-tab");n.removeClass("is-active"),n.filter("[data-language=".concat(t,"]")).addClass("is-active"),l.removeClass("is-visible"),l.filter("[data-name=".concat(t,"]")).addClass("is-visible"),i.attr("data-acfml-language",t),acf.doAction("acfml/switch_language",i,t)})}},{key:"initMultilingualWysiwyg",value:function(){acf.addFilter("wysiwyg_tinymce_settings",function(a,t,e){var i=e.$el.parents(".acfml-multilingual-field");if(!i.length)return a;var l=i.attr("data-name").split("_").join("-");return a.body_class+=" acf-wysiwyg--".concat(l),a})}},{key:"initMultilingualPostTitle",value:function(){acf.addAction("ready_field/name=acfml_post_title",function(a){b("#titlediv").remove(),b("[data-setting=\"title\"]").remove()}),acf.addAction("ready_field/key=field_acfml_post_title_".concat(acfml.defaultLanguage),function(a){acf.unload.stopListening(),acfml.isMobile||a.val()||a.$input().focus(),acf.unload.startListening()}),acf.addAction("ready_field/key=field_acfml_slug",function(a){})}},{key:"initMultilingualTermName",value:function(){acf.addAction("ready_field/key=field_acfml_term_name",function(a){b(".form-field.term-name-wrap").remove()})}},{key:"beforeSubmitPostForm",value:function(a){var t={};b(".acfml-multilingual-field").each(function(a,e){var i=b(e).attr("data-key"),l=b(e).find(".acfml-field.is-visible").attr("data-name");t[i]=l}),this.addToStore("acfml_language_tabs",t)}},{key:"initValidationHandling",value:function(){var a=this;acf.addAction("invalid_field",function(t){t.data.required&&t.$el.hasClass("acfml-field")&&!t.val()&&a.switchLanguage(t.$el.parents(".acfml-multilingual-field"),t.data.name)})}},{key:"addToStore",value:function(a,t){f.set(this.getStorageKey(a),JSON.stringify(t),1)}},{key:"removeFromStore",value:function(a){f.delete(this.getStorageKey(a))}},{key:"getFromStore",value:function(a){var t=f.get(this.getStorageKey(a));return t?JSON.parse(t):t}},{key:"getStorageKey",value:function(a){return"".concat(a,"_").concat(acfml.cookieHashForCurrentUri)}}]),a}();g.default=m,new m;if(typeof exports==="object"&&typeof module!=="undefined"){module.exports=g}else if(typeof define==="function"&&define.amd){define(function(){return g})}})();