/**
 * ACFE — Automatic site translation (Google Translate)
 * Source language: English. Target: visitor browser language.
 */
(function () {
	'use strict';

	var COOKIE = 'googtrans';
	var SOURCE = 'en';
	var RELOAD_FLAG = 'gc_gt_reload';

	function getCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function setCookie(name, value, days) {
		var expires = '';
		if (typeof days === 'number') {
			var d = new Date();
			d.setTime(d.getTime() + days * 864e5);
			expires = ';expires=' + d.toUTCString();
		}
		var secure = location.protocol === 'https:' ? ';Secure' : '';
		document.cookie = name + '=' + encodeURIComponent(value) + expires + ';path=/' + secure + ';SameSite=Lax';
		try {
			document.cookie = name + '=' + encodeURIComponent(value) + expires + ';path=/;domain=' + location.hostname + secure + ';SameSite=Lax';
		} catch (e) { /* ignore */ }
	}

	function browserLang() {
		var raw = (navigator.languages && navigator.languages[0]) || navigator.language || navigator.userLanguage || SOURCE;
		raw = String(raw).toLowerCase().replace('_', '-');
		var primary = raw.split('-')[0];
		if (raw === 'zh-cn' || raw === 'zh-hans') return 'zh-CN';
		if (raw === 'zh-tw' || raw === 'zh-hant') return 'zh-TW';
		if (raw.indexOf('pt-br') === 0) return 'pt';
		if (primary === 'nb' || primary === 'nn') return 'no';
		return primary || SOURCE;
	}

	function ensureAutoTranslate() {
		var lang = browserLang();
		if (!lang || lang === SOURCE) {
			return;
		}
		var current = getCookie(COOKIE);
		var wanted = '/' + SOURCE + '/' + lang;
		if (current === wanted || current.indexOf('/' + SOURCE + '/' + lang) === 0) {
			return;
		}
		setCookie(COOKIE, wanted, 365);
		// One reload so Google Translate picks up the new cookie
		try {
			if (!sessionStorage.getItem(RELOAD_FLAG)) {
				sessionStorage.setItem(RELOAD_FLAG, '1');
				location.reload();
			}
		} catch (e) {
			/* sessionStorage blocked — widget still available */
		}
	}

	ensureAutoTranslate();

	window.gcGoogleTranslateInit = function () {
		ensureAutoTranslate();
		if (typeof google === 'undefined' || !google.translate || !google.translate.TranslateElement) {
			return;
		}
		new google.translate.TranslateElement(
			{
				pageLanguage: SOURCE,
				autoDisplay: false,
				layout: google.translate.TranslateElement.InlineLayout.SIMPLE
			},
			'google_translate_element'
		);
	};
})();
