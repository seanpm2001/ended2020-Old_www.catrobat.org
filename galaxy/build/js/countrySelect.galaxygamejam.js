!function(a){"function"==typeof define&&define.amd?define(["jquery"],function(b){a(b,window,document)}):a(jQuery,window,document)}(function(a,b,c,d){"use strict";function j(b,c){this.element=b,this.options=a.extend({},g,c),this._defaults=g,this.ns="."+e+f++,this._name=e,this.init()}var e="countrySelect",f=1,g={defaultCountry:"",defaultStyling:"inside",onlyCountries:[],preferredCountries:["us","gb"]},h={UP:38,DOWN:40,ENTER:13,ESC:27,PLUS:43,A:65,Z:90},i=!1;a(b).load(function(){i=!0}),j.prototype={init:function(){this._processCountryData(),this._generateMarkup(),this._setInitialState(),this._initListeners()},_processCountryData:function(){this._setInstanceCountryData(),this._setPreferredCountries()},_setInstanceCountryData:function(){var b=this;if(this.options.onlyCountries.length){var c=[];a.each(this.options.onlyCountries,function(a,d){var e=b._getCountryData(d,!0);e&&c.push(e)}),this.countries=c}else this.countries=k},_setPreferredCountries:function(){var b=this;this.preferredCountries=[],a.each(this.options.preferredCountries,function(a,c){var d=b._getCountryData(c,!1);d&&b.preferredCountries.push(d)})},_generateMarkup:function(){this.countryInput=a(this.element);var b="country-select";this.options.defaultStyling&&(b+=" "+this.options.defaultStyling),this.countryInput.wrap(a("<div>",{"class":b}));var c=a("<div>",{"class":"flag-dropdown"}).insertAfter(this.countryInput),d=a("<div>",{"class":"selected-flag"}).appendTo(c);this.selectedFlagInner=a("<div>",{"class":"flag"}).appendTo(d),a("<div>",{"class":"arrow"}).appendTo(this.selectedFlagInner),this.countryList=a("<ul>",{"class":"country-list v-hide"}).appendTo(c),this.preferredCountries.length&&(this._appendListItems(this.preferredCountries,"preferred"),a("<li>",{"class":"divider"}).appendTo(this.countryList)),this._appendListItems(this.countries,""),this.countryCodeInput=a("#"+this.countryInput.attr("id")+"_code"),this.countryCodeInput||(this.countryCodeInput=a('<input type="hidden" id="'+this.countryInput.attr("id")+'_code" name="'+this.countryInput.attr("name")+'_code" value="" />'),this.countryCodeInput.insertAfter(this.countryInput)),this.dropdownHeight=this.countryList.outerHeight(),this.countryList.removeClass("v-hide").addClass("hide"),this.countryListItems=this.countryList.children(".country")},_appendListItems:function(b,c){var d="";a.each(b,function(a,b){d+='<li class="country '+c+'" data-country-code="'+b.iso2+'">',d+='<div class="flag '+b.iso2+'"></div>',d+='<span class="country-name">'+b.name+"</span>",d+="</li>"}),this.countryList.append(d)},_setInitialState:function(){var a=!1;this.countryInput.val()&&(a=this._updateFlagFromInputVal());var b=this.countryCodeInput.val();if(b&&this.selectCountry(b),!a){var c;this.options.defaultCountry?(c=this._getCountryData(this.options.defaultCountry,!1),c||(c=this.preferredCountries.length?this.preferredCountries[0]:this.countries[0])):c=this.preferredCountries.length?this.preferredCountries[0]:this.countries[0],this.selectCountry(c.iso2)}},_initListeners:function(){var a=this;this.countryInput.on("keyup"+this.ns,function(){a._updateFlagFromInputVal()});var b=this.selectedFlagInner.parent();b.on("click"+this.ns,function(b){a.countryList.hasClass("hide")&&!a.countryInput.prop("disabled")&&a._showDropdown()}),this.countryInput.on("blur"+this.ns,function(){a.countryInput.val()!=a.getSelectedCountryData().name&&a.setCountry(a.countryInput.val()),a.countryInput.val(a.getSelectedCountryData().name)})},_focus:function(){this.countryInput.focus();var a=this.countryInput[0];if(a.setSelectionRange){var b=this.countryInput.val().length;a.setSelectionRange(b,b)}},_showDropdown:function(){this._setDropdownPosition();var a=this.countryList.children(".active");this._highlightListItem(a),this.countryList.removeClass("hide"),this._scrollTo(a),this._bindDropdownListeners(),this.selectedFlagInner.children(".arrow").addClass("up")},_setDropdownPosition:function(){var c=this.countryInput.offset().top,d=a(b).scrollTop(),e=c+this.countryInput.outerHeight()+this.dropdownHeight<d+a(b).height(),f=c-this.dropdownHeight>d,g=!e&&f?"-"+(this.dropdownHeight-1)+"px":"";this.countryList.css("top",g)},_bindDropdownListeners:function(){var b=this;this.countryList.on("mouseover"+this.ns,".country",function(c){b._highlightListItem(a(this))}),this.countryList.on("click"+this.ns,".country",function(c){b._selectListItem(a(this))});var d=!0;a("html").on("click"+this.ns,function(a){d||b._closeDropdown(),d=!1}),a(c).on("keydown"+this.ns,function(a){a.preventDefault(),a.which==h.UP||a.which==h.DOWN?b._handleUpDownKey(a.which):a.which==h.ENTER?b._handleEnterKey():a.which==h.ESC?b._closeDropdown():a.which>=h.A&&a.which<=h.Z&&b._handleLetterKey(a.which)})},_handleUpDownKey:function(a){var b=this.countryList.children(".highlight").first(),c=a==h.UP?b.prev():b.next();c.length&&(c.hasClass("divider")&&(c=a==h.UP?c.prev():c.next()),this._highlightListItem(c),this._scrollTo(c))},_handleEnterKey:function(){var a=this.countryList.children(".highlight").first();a.length&&this._selectListItem(a)},_handleLetterKey:function(b){var c=String.fromCharCode(b),d=this.countryListItems.filter(function(){return a(this).text().charAt(0)==c&&!a(this).hasClass("preferred")});if(d.length){var f,e=d.filter(".highlight").first();f=e&&e.next()&&e.next().text().charAt(0)==c?e.next():d.first(),this._highlightListItem(f),this._scrollTo(f)}},_updateFlagFromInputVal:function(){var b=this,c=this.countryInput.val().replace(/(?=[() ])/g,"\\");if(c){for(var d=[],e=new RegExp("^"+c,"i"),f=0;f<this.countries.length;f++)this.countries[f].name.match(e)&&d.push(this.countries[f].iso2);var g=!1;return a.each(d,function(a,c){b.selectedFlagInner.hasClass(c)&&(g=!0)}),g||(this._selectFlag(d[0]),this.countryCodeInput.val(d[0]).trigger("change")),!0}return!1},_highlightListItem:function(a){this.countryListItems.removeClass("highlight"),a.addClass("highlight")},_getCountryData:function(a,b){for(var c=b?k:this.countries,d=0;d<c.length;d++)if(c[d].iso2==a)return c[d];return null},_selectFlag:function(a){if(!a)return!1;this.selectedFlagInner.attr("class","flag "+a);var b=this._getCountryData(a);this.selectedFlagInner.parent().attr("title",b.name);var c=this.countryListItems.children(".flag."+a).first().parent();this.countryListItems.removeClass("active"),c.addClass("active")},_selectListItem:function(a){var b=a.attr("data-country-code");this._selectFlag(b),this._closeDropdown(),this._updateName(b),this.countryInput.trigger("change"),this.countryCodeInput.trigger("change"),this._focus()},_closeDropdown:function(){this.countryList.addClass("hide"),this.selectedFlagInner.children(".arrow").removeClass("up"),a(c).off("keydown"+this.ns),a("html").off("click"+this.ns),this.countryList.off(this.ns)},_scrollTo:function(a){if(a&&a.offset()){var b=this.countryList,c=b.height(),d=b.offset().top,e=d+c,f=a.outerHeight(),g=a.offset().top,h=g+f,i=g-d+b.scrollTop();if(d>g)b.scrollTop(i);else if(h>e){var j=c-f;b.scrollTop(i-j)}}},_updateName:function(a){this.countryCodeInput.val(a).trigger("change"),this.countryInput.val(this._getCountryData(a).name)},getSelectedCountryData:function(){var a=this.selectedFlagInner.attr("class").split(" ")[1];return this._getCountryData(a)},selectCountry:function(a){a=a.toLowerCase(),this.selectedFlagInner.hasClass(a)||(this._selectFlag(a),this._updateName(a))},setCountry:function(a){this.countryInput.val(a),this._updateFlagFromInputVal()},destroy:function(){this.countryInput.off(this.ns),this.selectedFlagInner.parent().off(this.ns);var a=this.countryInput.parent();a.before(this.countryInput).remove()}},a.fn[e]=function(b){var c=arguments;if(b===d||"object"==typeof b)return this.each(function(){a.data(this,"plugin_"+e)||a.data(this,"plugin_"+e,new j(this,b))});if("string"==typeof b&&"_"!==b[0]&&"init"!==b){var f;return this.each(function(){var d=a.data(this,"plugin_"+e);d instanceof j&&"function"==typeof d[b]&&(f=d[b].apply(d,Array.prototype.slice.call(c,1))),"destroy"===b&&a.data(this,"plugin_"+e,null)}),f!==d?f:this}},a.fn[e].getCountryData=function(){return k},a.fn[e].setCountryData=function(a){k=a};var k=a.each([{n:"\xd6sterreich (Austria)",i:"at"},{n:"Deutschland (Germany)",i:"de"},{n:"India",i:"in"},{n:"\u65e5\u672c (Japan)",i:"jp"},{n:"Espa\xf1a (Spain)",i:"es"},{n:"United States",i:"us"},{n:"United Kingdom",i:"gb"},{n:"\ub300\ud55c\ubbfc\uad6d (South Korea)",i:"kr"},{n:"Italia (Italy)",i:"it"},{n:"\u0e44\u0e17\u0e22 (Thailand)",i:"th"},{n:"Other",i:"other"}],function(a,b){b.name=b.n,b.iso2=b.i,delete b.n,delete b.i})});