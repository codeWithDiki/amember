/*
* Youtube Embed Plugin
*
* @author Jonnas Fonini <jonnasfonini@gmail.com>
* @version 2.1.14
*/
(function () {
	CKEDITOR.plugins.add('youtube', {
		lang: [ 'en', 'bg', 'pt', 'pt-br', 'ja', 'hu', 'it', 'fr', 'tr', 'ru', 'de', 'ar', 'nl', 'pl', 'vi', 'zh', 'el', 'he', 'es', 'nb', 'nn', 'fi', 'et', 'sk', 'cs', 'ko', 'eu', 'uk'],
		init: function (editor) {
			editor.addCommand('youtube', new CKEDITOR.dialogCommand('youtube', {
				allowedContent: 'div{*}(*); iframe{*}[!width,!height,!src,!frameborder,!allowfullscreen,!allow]; object param[*]; a[*]; img[*]'
			}));

			editor.ui.addButton('Youtube', {
				label : editor.lang.youtube.button,
				toolbar : 'insert',
				command : 'youtube',
				icon : this.path + 'images/icon.png'
			});

			CKEDITOR.dialog.add('youtube', function (instance) {
				var video,
					disabled = editor.config.youtube_disabled_fields || [];

				return {
					title : editor.lang.youtube.title,
					minWidth : 510,
					minHeight : 200,
					onShow: function () {
						for (var i = 0; i < disabled.length; i++) {
							this.getContentElement('youtubePlugin', disabled[i]).disable();
						}
					},
					contents :
						[{
							id : 'youtubePlugin',
							expand : true,
							elements :
								[{
									id : 'txtEmbed',
									type : 'textarea',
									label : editor.lang.youtube.txtEmbed,
									onChange : function (api) {
										handleEmbedChange(this, api);
									},
									onKeyUp : function (api) {
										handleEmbedChange(this, api);
									},
									validate : function () {
										if (this.isEnabled()) {
											if (!this.getValue()) {
												alert(editor.lang.youtube.noCode);
												return false;
											}
											else
											if (this.getValue().length === 0 || this.getValue().indexOf('//') === -1) {
												alert(editor.lang.youtube.invalidEmbed);
												return false;
											}
										}
									}
								},
								{
									type : 'html',
									html : editor.lang.youtube.or + '<hr>'
								},
								{
									type : 'hbox',
									widths : [ '70%', '15%', '15%' ],
									children :
									[
										{
											id : 'txtUrl',
											type : 'text',
											label : editor.lang.youtube.txtUrl,
											onChange : function (api) {
												handleLinkChange(this, api);
											},
											onKeyUp : function (api) {
												handleLinkChange(this, api);
											},
											validate : function () {
												if (this.isEnabled()) {
													if (!this.getValue()) {
														alert(editor.lang.youtube.noCode);
														return false;
													}
													else{
														video = ytVidId(this.getValue());

														if (this.getValue().length === 0 ||  video === false)
														{
															alert(editor.lang.youtube.invalidUrl);
															return false;
														}
													}
												}
											}
										},
										{
											type : 'text',
											id : 'txtWidth',
											width : '60px',
											label : editor.lang.youtube.txtWidth,
											'default' : editor.config.youtube_width != null ? editor.config.youtube_width : '640',
											validate : function () {
												if (this.getValue()) {
													var width = parseInt (this.getValue()) || 0;

													if (width === 0) {
														alert(editor.lang.youtube.invalidWidth);
														return false;
													}
												}
												else {
													alert(editor.lang.youtube.noWidth);
													return false;
												}
											}
										},
										{
											type : 'text',
											id : 'txtHeight',
											width : '60px',
											label : editor.lang.youtube.txtHeight,
											'default' : editor.config.youtube_height != null ? editor.config.youtube_height : '360',
											validate : function () {
												if (this.getValue()) {
													var height = parseInt(this.getValue()) || 0;

													if (height === 0) {
														alert(editor.lang.youtube.invalidHeight);
														return false;
													}
												}
												else {
													alert(editor.lang.youtube.noHeight);
													return false;
												}
											}
										}
									]
								},
								{
									type : 'hbox',
									widths : [ '55%', '45%' ],
									children :
										[
											{
												id : 'chkResponsive',
												type : 'checkbox',
												label : editor.lang.youtube.txtResponsive,
												'default' : editor.config.youtube_responsive != null ? editor.config.youtube_responsive : false
											},
											{
												id : 'chkNoEmbed',
												type : 'checkbox',
												label : editor.lang.youtube.txtNoEmbed,
												'default' : editor.config.youtube_noembed != null ? editor.config.youtube_noembed : false
											}
										]
								},
								{
									type : 'hbox',
									widths : [ '55%', '45%' ],
									children :
									[
										{
											id : 'chkRelated',
											type : 'checkbox',
											'default' : editor.config.youtube_related != null ? editor.config.youtube_related : true,
											label : editor.lang.youtube.chkRelated
										},
										{
											id : 'chkOlderCode',
											type : 'checkbox',
											'default' : editor.config.youtube_older != null ? editor.config.youtube_older : false,
											label : editor.lang.youtube.chkOlderCode
										}
									]
								},
								{
									type : 'hbox',
									widths : [ '55%', '45%' ],
									children :
									[
										{
											id : 'chkPrivacy',
											type : 'checkbox',
											label : editor.lang.youtube.chkPrivacy,
											'default' : editor.config.youtube_privacy != null ? editor.config.youtube_privacy : false
										},
										{
											id : 'chkAutoplay',
											type : 'checkbox',
											'default' : editor.config.youtube_autoplay != null ? editor.config.youtube_autoplay : false,
											label : editor.lang.youtube.chkAutoplay
										}
									]
								},
								{
									type : 'hbox',
									widths : [ '55%', '45%'],
									children :
									[
										{
											id : 'txtStartAt',
											type : 'text',
											label : editor.lang.youtube.txtStartAt,
											validate : function () {
												if (this.getValue()) {
													var str = this.getValue();

													if (!/^(?:(?:([01]?\d|2[0-3]):)?([0-5]?\d):)?([0-5]?\d)$/i.test(str)) {
														alert(editor.lang.youtube.invalidTime);
														return false;
													}
												}
											}
										},
										{
											id : 'chkControls',
											type : 'checkbox',
											'default' : editor.config.youtube_controls != null ? editor.config.youtube_controls : true,
											label : editor.lang.youtube.chkControls
										}
									]
								}
							]
						}
					],
					onOk: function()
					{
						var content = '';
						var responsiveStyle = '';

						if (this.getContentElement('youtubePlugin', 'txtEmbed').isEnabled()) {
							content = this.getValueOf('youtubePlugin', 'txtEmbed');
						}
						else {
							var url = 'https://', params = [], startSecs, paramAutoplay='';
							var width = this.getValueOf('youtubePlugin', 'txtWidth');
							var height = this.getValueOf('youtubePlugin', 'txtHeight');

							if (this.getContentElement('youtubePlugin', 'chkPrivacy').getValue() === true) {
								url += 'www.youtube-nocookie.com/';
							}
							else {
								url += 'www.youtube.com/';
							}

							url += 'embed/' + video;

							if (this.getContentElement('youtubePlugin', 'chkRelated').getValue() === false) {
								params.push('rel=0');
							}

							if (this.getContentElement('youtubePlugin', 'chkAutoplay').getValue() === true) {
								params.push('autoplay=1');
								paramAutoplay='autoplay';
							}

							if (this.getContentElement('youtubePlugin', 'chkControls').getValue() === false) {
								params.push('controls=0');
							}

							startSecs = this.getValueOf('youtubePlugin', 'txtStartAt');

							if (startSecs) {
								var seconds = hmsToSeconds(startSecs);

								params.push('start=' + seconds);
							}

							if (params.length > 0) {
								url = url + '?' + params.join('&');
							}

							if (this.getContentElement('youtubePlugin', 'chkResponsive').getValue() === true) {
								content += '<div class="youtube-embed-wrapper" style="position:relative;padding-bottom:56.25%;padding-top:30px;height:0;overflow:hidden">';
								responsiveStyle = 'style="position:absolute;top:0;left:0;width:100%;height:100%"';
							}

							if (this.getContentElement('youtubePlugin', 'chkOlderCode').getValue() === true) {
								url = url.replace('embed/', 'v/');
								url = url.replace(/&/g, '&amp;');

								if (url.indexOf('?') === -1) {
									url += '?';
								}
								else {
									url += '&amp;';
								}
								url += 'hl=' + (this.getParentEditor().config.language ? this.getParentEditor().config.language : 'en') + '&amp;version=3';

								content += '<object width="' + width + '" height="' + height + '" ' + responsiveStyle + '>';
								content += '<param name="movie" value="' + url + '"></param>';
								content += '<param name="allowFullScreen" value="true"></param>';
								content += '<param name="allowscriptaccess" value="always"></param>';
								content += '<embed src="' + url + '" type="application/x-shockwave-flash" ';
								content += 'width="' + width + '" height="' + height + '" '+ responsiveStyle + ' allowscriptaccess="always" ';
								content += 'allowfullscreen="true"></embed>';
								content += '</object>';
							}
							else
							if (this.getContentElement('youtubePlugin', 'chkNoEmbed').getValue() === true) {
								var imgSrc = 'https://img.youtube.com/vi/' + video + '/sddefault.jpg';
								content += '<a href="' + url + '" ><img width="' + width + '" height="' + height + '" src="' + imgSrc + '" '  + responsiveStyle + '/></a>';
							}
							else {
								content += '<iframe ' + (paramAutoplay ? 'allow="' + paramAutoplay + ';" ' : '') + 'width="' + width + '" height="' + height + '" src="' + url + '" ' + responsiveStyle;
								content += 'frameborder="0" allowfullscreen></iframe>';
							}

							if (this.getContentElement('youtubePlugin', 'chkResponsive').getValue() === true) {
								content += '</div>';
							}
						}

						var element = CKEDITOR.dom.element.createFromHtml(content);
						var instance = this.getParentEditor();
						instance.insertElement(element);
					}
				};
			});
		}
	});
})();

function handleLinkChange(el, api) {
	var video = ytVidId(el.getValue());
	var time = ytVidTime(el.getValue());

	if (el.getValue().length > 0) {
		el.getDialog().getContentElement('youtubePlugin', 'txtEmbed').disable();
	}
	else if (!disabled.length || !disabled.includes('txtEmbed')) {
		el.getDialog().getContentElement('youtubePlugin', 'txtEmbed').enable();
	}

	if (video && time) {
		var seconds = timeParamToSeconds(time);
		var hms = secondsToHms(seconds);
		el.getDialog().getContentElement('youtubePlugin', 'txtStartAt').setValue(hms);
	}
}

function handleEmbedChange(el, api) {
	if (el.getValue().length > 0) {
		el.getDialog().getContentElement('youtubePlugin', 'txtUrl').disable();
	}
	else {
		el.getDialog().getContentElement('youtubePlugin', 'txtUrl').enable();
	}
}


/**
 * JavaScript function to match (and return) the video Id
 * of any valid Youtube Url, given as input string.
 * @author: Stephan Schmitz <eyecatchup@gmail.com>
 * @url: http://stackoverflow.com/a/10315969/624466
 */
function ytVidId(url) {
	var p = /^(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/;
	return (url.match(p)) ? RegExp.$1 : false;
}

/**
 * Matches and returns time param in YouTube Urls.
 */
function ytVidTime(url) {
	var p = /t=([0-9hms]+)/;
	return (url.match(p)) ? RegExp.$1 : false;
}

/**
 * Converts time in hms format to seconds only
 */
function hmsToSeconds(time) {
	var arr = time.split(':'), s = 0, m = 1;

	while (arr.length > 0) {
		s += m * parseInt(arr.pop(), 10);
		m *= 60;
	}

	return s;
}

/**
 * Converts seconds to hms format
 */
function secondsToHms(seconds) {
	var h = Math.floor(seconds / 3600);
	var m = Math.floor((seconds / 60) % 60);
	var s = seconds % 60;

	var pad = function (n) {
		n = String(n);
		return n.length >= 2 ? n : "0" + n;
	};

	if (h > 0) {
		return pad(h) + ':' + pad(m) + ':' + pad(s);
	}
	else {
		return pad(m) + ':' + pad(s);
	}
}

/**
 * Converts time in youtube t-param format to seconds
 */
function timeParamToSeconds(param) {
	var componentValue = function (si) {
		var regex = new RegExp('(\\d+)' + si);
		return param.match(regex) ? parseInt(RegExp.$1, 10) : 0;
	};

	return componentValue('h') * 3600
		+ componentValue('m') * 60
		+ componentValue('s');
}

/**
 * Converts seconds into youtube t-param value, e.g. 1h4m30s
 */
function secondsToTimeParam(seconds) {
	var h = Math.floor(seconds / 3600);
	var m = Math.floor((seconds / 60) % 60);
	var s = seconds % 60;
	var param = '';

	if (h > 0) {
		param += h + 'h';
	}

	if (m > 0) {
		param += m + 'm';
	}

	if (s > 0) {
		param += s + 's';
	}

	return param;
}

CKEDITOR.plugins.setLang('youtube', 'ar', {
	button : 'شيفرة تضمين اليوتيوب',
	title : 'شيفرة تضمين اليوتيوب',
	txtEmbed : 'الصق شيفرة التضمين هنا',
	txtUrl : 'الصق رابط فيديو اليوتيوب',
	txtWidth : 'العرض',
	txtHeight : 'الطول',
	chkRelated : 'اظهر الفيديوهات المقترحة في نهاية الفيديو',
	txtStartAt : 'ابدأ عند (ss او mm:ss او hh:mm:ss)',
	chkPrivacy : 'تفعيل وضع تحسين الخصوصية',
	chkOlderCode : 'استخدم شيفرة التضمين القديمة',
	chkAutoplay : 'Autoplay',
	chkControls: 'إظهار عناصر التحكم بالمشغّل',
	noCode : 'يجب عليك ادخال شيفرة التضمين او الرابط',
	invalidEmbed : 'شيفرة التضمين التي قمت بإدخالها تبدو غير صحيحة',
	invalidUrl : 'الرابط الذي قمت بإدخاله يبدو غير صحيح',
	or : 'او',
	noWidth : 'يجب عليك ادخال العرض',
	invalidWidth : 'يجب عليك ادخال عرض صحيح',
	noHeight : 'يجب عليك ادخال الطول',
	invalidHeight : 'يجب عليك ادخال طول صحيح',
	invalidTime : 'يجب عليك ادخال وقت بداية صحيح',
	txtResponsive : 'Responsive video'
});
CKEDITOR.plugins.setLang('youtube', 'bg', {
	button : 'Вмъкни YouTube видео',
	title : 'Вграждане на YouTube видео',
	txtEmbed : 'Въведете кода за вграждане тук',
	txtUrl : 'Въведете YouTube видео URL',
	txtWidth : 'Ширина',
	txtHeight : 'Височина',
	chkRelated : 'Показва предложени видеоклипове в края на клипа',
	txtStartAt : 'Стартирай в (ss или mm:ss или hh:mm:ss)',
	chkPrivacy : 'Активирай режим за поверителност',
	chkOlderCode : 'Използвай стар код за вграждане',
	chkAutoplay: 'Авто стартиране',
	chkControls: 'Показва контролите на плейъра',
	noCode : 'Трябва да въведете код за вграждане или URL адрес',
	invalidEmbed : 'Кодът за вграждане, който сте въвели, не изглежда валиден',
	invalidUrl : 'Въведеният URL адрес не изглежда валиден',
	or : 'или',
	noWidth : 'Трябва да заложите ширината',
	invalidWidth : 'Заложете валидна ширина',
	noHeight : 'Трябва да заложите височина',
	invalidHeight : 'Заложете валидна височина',
	invalidTime : 'Заложете валидно време за стартиране',
	txtResponsive : 'Напасва по ширина (игнорира Ширина и Височина)',
	txtNoEmbed : 'Само видео изображение и връзка'
});
CKEDITOR.plugins.setLang('youtube', 'cs', {
	button : 'Vložit video YouTube',
	title : 'Vložit video YouTube',
	txtEmbed : 'Zde vložte kód pro vložení',
	txtUrl : 'Vložte adresu URL videa YouTube',
	txtWidth : 'Šířka',
	txtHeight : 'Výška',
	chkRelated : 'Po dohrání videa zobrazit navrhovaná videa',
	txtStartAt : 'Začít přehrávat v čase (ss nebo mm:ss nebo hh:mm:ss)',
	chkPrivacy : 'Povolit režim s rozšířeným soukromím',
	chkOlderCode : 'Použít starý kód pro vložení',
	chkAutoplay : 'Automatické spuštění přehrávání',
	chkControls : 'Zobrazit ovladače přehrávání',
	noCode : 'Musíte vložit kód pro vložení nebo adresu URL',
	invalidEmbed : 'Vložený kód pro vložení zřejmě není platný',
	invalidUrl : 'Zadaná adresa URL zřejmě není platná',
	or : 'nebo',
	noWidth : 'Musíte zadat šířku',
	invalidWidth : 'Zadejte platnou šířku',
	noHeight : 'Musíte zadat výšku',
	invalidHeight : 'Zadejte platnou výšku',
	invalidTime : 'Zadejte platný počáteční čas',
	txtResponsive : 'Responzivní design (ignorovat výšku a šířku, uzpůsobit šířce)',
	txtNoEmbed : 'Pouze obrázek videa s odkazem'
});
CKEDITOR.plugins.setLang('youtube', 'de', {
	button : 'YouTube Video einbinden',
	title : 'YouTube Video einbinden',
	txtEmbed : 'Embed Code hier einfügen',
	txtUrl : 'YouTube Video URL hier einfügen',
	txtWidth : 'Breite',
	txtHeight : 'Höhe',
	chkRelated : 'Vorschläge am Ende des Videos einblenden',
	txtStartAt : 'Start bei Position (ss oder mm:ss oder hh:mm:ss)',
	chkPrivacy : 'Erweiterten Datenschutzmodus aktivieren',
	chkOlderCode : 'Benutze alten Embed Code',
	chkAutoplay : 'Autoplay',
	chkControls : 'Player-Steuerelemente anzeigen',
	noCode : 'Sie müssen einen Embed Code oder URL angeben',
	invalidEmbed : 'Der angegebene Embed Code scheint nicht gültig zu sein.',
	invalidUrl : 'Die angegebene URL scheint nicht gültig zu sein.',
	or : 'oder',
	noWidth : 'Geben Sie eine Breite an',
	invalidWidth : 'Geben Sie eine gültige Breite an',
	noHeight : 'Geben Sie eine Höhe an',
	invalidHeight : 'Geben Sie eine gültige Höhe an',
	invalidTime : 'Geben Sie eine gültige Startzeit an',
	txtResponsive : 'Automatische Größe (ignoriert Breite und Höhe)'
});
CKEDITOR.plugins.setLang('youtube', 'el', {
	button: 'Ενσωμάτωση Youtube βίντεο',
	title: 'Ενσωμάτωση Youtube βίντεο',
	txtEmbed: 'Επικόλλησε τον κώδικα ενσωμάτωσης',
	txtUrl: 'Επικόλλησε το URL του βίντεο',
	txtWidth: 'Πλάτος',
	txtHeight: 'Ύψος',
	chkRelated: 'Εμφάνιση προτεινόμενων βίντεο μόλις ολοκληρωθεί',
	txtStartAt: 'Χρόνος εκκίνησης (ss or mm:ss or hh:mm:ss)',
	chkPrivacy: 'Ενεργοποίηση λειτουργίας ενισχυμένου απορρήτου',
	chkOlderCode: 'Χρήση παλαιού κώδικα ενσωμάτωσης',
	chkAutoplay: 'Αυτόματη εκκίνηση',
	chkControls: 'Εμφάνιση στοιχείων ελέγχου προγράμματος αναπαραγωγής',
	noCode: 'Χρειάζεται κώδικας ενσωμάτωσης ή URL',
	invalidEmbed: 'Ο κώδικας ενσωμάτωσης που εισήγατε δεν μοιάζει σωστός',
	invalidUrl: 'Το URL που εισήγατε δεν μοιάζει σωστό',
	or: 'ή',
	noWidth: 'Συμπληρώστε το πλάτος',
	invalidWidth: 'Λανθασμένο πλάτος',
	noHeight: 'Συμπληρώστε το ύψος',
	invalidHeight: 'Λανθασμένο ύψος',
	invalidTime: 'Λανθασμένος χρόνος εκκίνησης'
});
CKEDITOR.plugins.setLang('youtube', 'en', {
	button : 'Embed YouTube Video',
	title : 'Embed YouTube Video',
	txtEmbed : 'Paste Embed Code Here',
	txtUrl : 'Paste YouTube Video URL',
	txtWidth : 'Width',
	txtHeight : 'Height',
	chkRelated : 'Show suggested videos at the video\'s end',
	txtStartAt : 'Start at (ss or mm:ss or hh:mm:ss)',
	chkPrivacy : 'Enable privacy-enhanced mode',
	chkOlderCode : 'Use old embed code',
	chkAutoplay: 'Autoplay',
	chkControls: 'Show player controls',
	noCode : 'You must input an embed code or URL',
	invalidEmbed : 'The embed code you\'ve entered doesn\'t appear to be valid',
	invalidUrl : 'The URL you\'ve entered doesn\'t appear to be valid',
	or : 'or',
	noWidth : 'You must inform the width',
	invalidWidth : 'Inform a valid width',
	noHeight : 'You must inform the height',
	invalidHeight : 'Inform a valid height',
	invalidTime : 'Inform a valid start time',
	txtResponsive : 'Make Responsive (ignore width and height, fit to width)',
	txtNoEmbed : 'Video image and link only'
});
CKEDITOR.plugins.setLang('youtube', 'es', {
	button : 'Embed YouTube video',
	title : 'Embed YouTube video',
	txtEmbed : 'Pegar el código embed',
	txtUrl : 'Pegar la URL al video de Youtube',
	txtWidth : 'Anchura',
	txtHeight : 'Altura',
	chkRelated : 'Mostrar videos sugeridos al final de este video',
	txtStartAt : 'Comenzar en (ss or mm:ss or hh:mm:ss)',
	chkPrivacy : 'Habilitar el modo privacy-enhanced',
	chkOlderCode : 'Usar código embed viejo',
	chkAutoplay: 'Autoplay',
	chkControls: 'Mostrar controles del reproductor',
	noCode : 'Debes de introducir un código embed o URL',
	invalidEmbed : 'El código embed introducido parece no ser valido',
	invalidUrl : 'La URL introducida parece no ser valida',
	or : 'o',
	noWidth : 'Debes de dar la anchura',
	invalidWidth : 'Da una anchura valida',
	noHeight : 'Debes dar una altura valida',
	invalidHeight : 'Da una altura valida',
	invalidTime : 'Da un tiempo de valido',
	txtResponsive : 'Hacer responsivo (ignorar anchura y altura, ajustar a la anchura)'
});
CKEDITOR.plugins.setLang('youtube', 'et', {
	button : 'Lisa YouTube video',
	title : 'YouTube video lisamine',
	txtEmbed : 'Kleepige manustatud kood siia',
	txtUrl : 'Kleepige YouTube video veebiaadress',
	txtWidth : 'Laius',
	txtHeight : 'Kõrgus',
	chkRelated : 'Näita soovitatud videosi antud video lõppus',
	txtStartAt : 'Alguskoht: (ss või mm:ss või hh:mm:ss)',
	chkPrivacy : 'Aktiveerige privaatsust täiendav režiim',
	chkOlderCode : 'Kasutage vana manuskoodi',
	chkAutoplay: 'Automaatesitlus',
	chkControls : 'Kuva pleieri nupud',
	noCode : 'Te peate sisestama video manuskoodi või veebiaadressi',
	invalidEmbed : 'Manuskood mille sisestasite ei paista olevat korrektne',
	invalidUrl : 'Veebiaadress mille sisestasite ei paista olevat korrektne',
	or : 'või',
	noWidth : 'Te peate sisestama video laiuse',
	invalidWidth : 'Sisestage korrektne laius',
	noHeight : 'Te peate sisestama video kõrguse',
	invalidHeight : 'Sisestage korrektne kõrgus',
	invalidTime : 'Sisestage korrektne algusaeg',
	txtResponsive : 'Aktiveerige ekraani laiusega ühilduv režiim'
});
CKEDITOR.plugins.setLang('youtube', 'eu', {
	button : 'Kapsulatu YouTube-ko bideoa',
	title : 'Kapsulatu YouTube-ko bideoa',
	txtEmbed : 'Itsatsi kapsulatzeko kodea hemen',
	txtUrl : 'Itsatsi YouTube-ko bideoaren URLa',
	txtWidth : 'Zabalera',
	txtHeight : 'Altuera',
	chkRelated : 'Erakutsi gomendatutako bideoak amaieran',
	txtStartAt : 'Hasi hemendik (ss edo mm:ss edo hh:mm:ss)',
	chkPrivacy : 'Gaitu pribatutasun hobetuko modua',
	chkOlderCode : 'Erabili kapsulatzeko kode zaharra',
	chkAutoplay: 'Erreproduzitu automatikoki',
	chkControls: 'Erakutsi erreproduzigailuaren kontrolak',
	noCode : 'Kapsulatzeko kode bat edo URL bat sartu behar duzu',
	invalidEmbed : 'Sartu duzun kapsulatzeko kodea ez da baliozkoa',
	invalidUrl : 'Sartu duzun URLa ez da baliozkoa',
	or : 'edo',
	noWidth : 'Zabalera sartu behar duzu',
	invalidWidth : 'Sartu baliozko zabalera bat',
	noHeight : 'Altuera sartu behar duzu',
	invalidHeight : 'Sartu baliozko altuera bat',
	invalidTime : 'Sartu baliozko hasierako denbora bat',
	txtResponsive : 'Egin moldagarria (ez ikusia egin zabalera eta altuerari, zabalerara doitu)',
	txtNoEmbed : 'Bideoaren irudia eta esteka soilik'
});
CKEDITOR.plugins.setLang('youtube', 'fi', {
	button : 'Upota YouTube-video',
	title : 'Upota YouTube-video',
	txtEmbed : 'Syötä YouTube-videon upotuskoodi',
	txtUrl : 'Syötä YouTube-videon www-osoite',
	txtWidth : 'Leveys',
	txtHeight : 'Korkeus',
	chkRelated : 'Näytä suositukset lopussa',
	txtStartAt : 'Aloitusaika (ss tai mm:ss tai tt:mm:ss)',
	chkPrivacy : 'Aktivoi yksityisyyttä parantava tila',
	chkOlderCode : 'Käytä vanhaa upotuskoodia',
	chkAutoplay: 'Soita automaattisesti',
	chkControls : 'Näytä soittimen ohjaimet',
	noCode : 'Sinun täytyy syötää upotuskoodi tai www-osoite',
	invalidEmbed : 'Upotuskoodi on virheellinen',
	invalidUrl : 'Www-osoite on virheellinen',
	or : 'tai',
	noWidth : 'Syötä leveys',
	invalidWidth : 'Leveys on virheellinen',
	noHeight : 'Syötä korkeus',
	invalidHeight : 'Korkeus on virheellinen',
	invalidTime : 'Aloitusaika on virheellinen',
	txtResponsive : 'Responsiivinen leveys (sovita leveys)'
});
CKEDITOR.plugins.setLang('youtube', 'fr', {
	button : 'Insérer une vidéo Youtube',
	title : 'Insérer une vidéo youtube',
	txtEmbed : 'Coller le code embed ici',
	txtUrl : 'Coller l\'url de la vidéo ici',
	txtWidth : 'Largeur',
	txtHeight : 'Hauteur',
	chkRelated : 'Montrer les suggestions de vidéo à la fin',
	txtStartAt : 'Commencer à (ss ou mm:ss ou hh:mm:ss)',
	chkPrivacy : 'Activer la protection de la vie privée',
	chkOlderCode : 'Utiliser l\'ancien code embed',
	chkAutoplay : 'Autoplay',
	chkControls : 'Afficher les commandes du lecteur',
	noCode : 'Vous devez entrer un code embed ou une url',
	invalidEmbed : 'Le code embed est invalide',
	invalidUrl : 'L\'url est invalide',
	or : 'ou',
	noWidth : 'Vous devez saisir une largeur',
	invalidWidth : 'La largeur saisie est invalide',
	noHeight : 'Vous devez saisir une hauteur',
	invalidHeight : 'La hauteur saisie est invalide',
	invalidTime : 'Le temps de départ de la vidéo est invalide',
	txtResponsive : 'Responsive video',
	txtNoEmbed : 'Vidéo image et lien seulement'
});
CKEDITOR.plugins.setLang('youtube', 'he', {
	button : 'שבץ וידאו של YouTube',
	title : 'שבץ וידאו של YouTube',
	txtEmbed : 'הדבק את קוד השיבוץ כאן',
	txtUrl : 'הדבק כתובת וידאו YouTube',
	txtWidth : 'אורך',
	txtHeight : 'גובה',
	chkRelated : 'הצג סרטונים מומלצים בסוף הודיאו',
	txtStartAt : 'התחל ב (ss או mm:ss או hh:mm:ss)',
	chkPrivacy : 'הפעל מצב פרטיות המשופרת',
	chkOlderCode : 'השתמש בקוד הטמעה ישן',
	chkAutoplay: 'הפעלה אוטומטית',
	chkControls : 'הצג פקדי נגן',
	noCode : 'אתה חייב להזין קוד embed כתובת וידאו אתר',
	invalidEmbed : 'קוד ההטמעה שהוזן אינו נראה חוקי',
	invalidUrl : 'כתובת הוידאו אינה נראת חוקית',
	or : 'או',
	noWidth : 'חובה להזין אורך',
	invalidWidth : 'האורך שהוזן שגוי',
	noHeight : 'חובה להזין גובה',
	invalidHeight : 'הגובה שהוזן שגוי',
	invalidTime : 'זמן התחלה שהוזן שגוי',
	txtResponsive : 'הפוך לרספונסיבי (התעלם מרוחב וגובה, התאם לרוחב)'
});
CKEDITOR.plugins.setLang('youtube', 'hu', {
	button : 'Youtube videó beillesztése',
	title : 'Youtube videó beillesztése',
	txtEmbed : 'Illessze be a beágyazott kódot',
	txtUrl : 'Illessze be a Youtube videó URL-jét',
	txtWidth : 'Szélesség',
	txtHeight : 'Magasság',
	txtStartAt : 'Kezdő időpont (ss vagy mm:ss vagy hh:mm:ss)',
	chkRelated : 'Ajánlott videók megjelenítése, amikor a videó befejeződik',
	chkPrivacy : 'Fokozott adatvédelmi mód engedélyezése',
	chkOlderCode : 'Régi beágyazott kód használata',
	chkAutoplay : 'Automatikus lejátszás',
	chkControls : 'Lejátszásvezérlők mutatása',
	noCode : 'A beágyazott kód, vagy az URL megadása kötelező',
	invalidEmbed : 'A beágyazott kód érvénytelen',
	invalidUrl : 'A megadott URL érvénytelen',
	or : 'vagy',
	noWidth : 'A szélesség megadása kötelező',
	invalidWidth : 'Érvényes szélességet adjon meg',
	noHeight : 'A magasság megadása kötelező',
	invalidHeight : 'Érvényes magasságot adjon meg',
	invalidTime : 'Érvényes kezdő időpontot adjon meg',
	txtResponsive : 'Reszponzív videó',
	txtNoEmbed : 'Csak kép és hivatkozás jelenjen meg'
});
CKEDITOR.plugins.setLang('youtube', 'it', {
	button : 'Incorpora video Youtube',
	title : 'Incorpora video Youtube',
	txtEmbed : 'Incolla qui il codice di incorporamento',
	txtUrl : 'Incolla l\'URL del video Youtube',
	txtWidth : 'Larghezza',
	txtHeight : 'Altezza',
	chkRelated : 'Mostra i video suggeriti dopo il video',
	txtStartAt : 'Inizia a (ss o mm:ss o hh:mm:ss)',
	chkPrivacy : 'Abilita la protezione della privacy',
	chkOlderCode : 'Usa il vecchio codice di incorporamento',
	chkAutoplay : 'Autoplay',
	chkControls : 'Mostra i controlli del player',
	noCode : 'Devi inserire un codice di incorporamento o un URL',
	invalidEmbed : 'Il codice di incorporamento inserito non sembra valido',
	invalidUrl : 'L\'URL inserito non sembra valido',
	or : 'o',
	noWidth : 'Devi indicare la larghezza',
	invalidWidth : 'Indica una larghezza valida',
	noHeight : 'Devi indicare l\'altezza',
	invalidHeight : 'Indica un\'altezza valida',
	invalidTime : 'Indica un tempo di inizio valido',
	txtResponsive : 'Responsive video'
});
CKEDITOR.plugins.setLang('youtube', 'ja', {
	button : 'Youtube動画埋め込み',
	title : 'Youtube動画埋め込み',
	txtEmbed : '埋め込みコードを貼り付けてください',
	txtUrl : 'URLを貼り付けてください',
	txtWidth : '幅',
	txtHeight : '高さ',
	chkRelated : '動画が終わったら関連動画を表示する',
	txtStartAt : '開始時間（秒）',
	chkPrivacy : 'プライバシー強化モードを有効にする',
	chkOlderCode : '以前の埋め込みコードを使用する',
	chkAutoplay : '自動再生',
	chkControls: 'プレーヤーのコントロールを表示する',
	noCode : '埋め込みコードまたはURLを入力してください',
	invalidEmbed : '不適切な埋め込みコードが入力されました',
	invalidUrl : '不適切なURLが入力されました',
	or : 'または',
	noWidth : '幅を指定してください',
	invalidWidth : '幅指定に誤りがあります',
	noHeight : '高さを指定してください',
	invalidHeight : '高さ指定に誤りがあります',
	invalidTime : '開始時間を正の整数で入力してください',
	txtResponsive : 'レスポンシブ表示'
});
CKEDITOR.plugins.setLang('youtube', 'ko', {
	button : '유투브 비디오 삽입',
	title : '유투브 비디오 삽입',
	txtEmbed : '여기 embed 코드를 붙여넣으세요',
	txtUrl : '유투브 주소(URL)를 붙여넣으세요',
	txtWidth : '너비',
	txtHeight : '높이',
	chkRelated : '비디오 마지막에 추천 영상 보이기',
	txtStartAt : '시작 시점 (ss 또는 mm:ss 또는 hh:mm:ss)',
	chkPrivacy : '개인정보 보호 모드 활성화',
	chkOlderCode : '옛날 embed 코드 사용',
	chkAutoplay: '자동 재생',
	chkControls: '플레이어 컨트롤 표시',
	noCode : 'embed 코드 또는 URL을 입력해야 합니다',
	invalidEmbed : '입력하신 embed 코드가 유효하지 않습니다',
	invalidUrl : '입력하신 주소(URL)가 유효하지 않습니다',
	or : '또는',
	noWidth : '너비를 알려주세요',
	invalidWidth : '너비가 유효하지 않습니다',
	noHeight : '높이를 알려주세요',
	invalidHeight : '높이가 유효하지 않습니다',
	invalidTime : '시작 시점이 유효하지 않습니다',
	txtResponsive : '반응형 너비 (입력한 너비와 높이를 무시하고 창 너비에 맞춤)',
	txtNoEmbed : '비디오 이미지와 링크만 달기'
});
CKEDITOR.plugins.setLang('youtube', 'nb', {
	button : 'Bygg inn YouTube-video',
	title : 'Bygg inn YouTube-video',
	txtEmbed : 'Lim inn embed-kode her',
	txtUrl : 'Lim inn YouTube video-URL',
	txtWidth : 'Bredde',
	txtHeight : 'Høyde',
	chkRelated : 'Vis foreslåtte videoer når videoen er ferdig',
	txtStartAt : 'Start ved (ss eller mm:ss eller hh:mm:ss)',
	chkPrivacy : 'Bruk personverntilpasset modus',
	chkOlderCode : 'Bruk gammel embedkode',
	chkAutoplay: 'Spill automatisk',
	chkControls: 'Vis spillerkontrollene',
	noCode : 'Du må legge inn en embed-kode eller URL',
	invalidEmbed : 'Emded-koden du la inn ser ikke ut til å være gyldig',
	invalidUrl : 'URLen du la inn ser ikke ut til å være gyldig',
	or : 'eller',
	noWidth : 'Du må legge inn bredde',
	invalidWidth : 'Legg inn en gyldig bredde',
	noHeight : 'Du må legge inn høyde',
	invalidHeight : 'Legg inn en gyldig høyde',
	invalidTime : 'Legg inn gyldig starttid',
	txtResponsive : 'Gjør responsiv (ignorer bredde og høyde, tilpass bredde på sida)'
});
CKEDITOR.plugins.setLang('youtube', 'nl', {
	button : 'Youtube video insluiten',
	title : 'Youtube video insluiten',
	txtEmbed : 'Plak embedcode hier',
	txtUrl : 'Plak video URL',
	txtWidth : 'Breedte',
	txtHeight : 'Hoogte',
	chkRelated : 'Toon gesuggereerde video aan het einde van de video',
	txtStartAt : 'Starten op (ss of mm:ss of hh:mm:ss)',
	chkPrivacy : 'Privacy-enhanced mode inschakelen',
	chkOlderCode : 'Gebruik oude embedcode',
	chkAutoplay: 'Automatisch starten',
	chkControls: 'Afspeelbediening weergeven',
	noCode : 'U moet een embedcode of url ingeven',
	invalidEmbed : 'De ingegeven embedcode lijkt niet geldig',
	invalidUrl : 'De ingegeven url lijkt niet geldig',
	or : 'of',
	noWidth : 'U moet een breedte ingeven',
	invalidWidth : 'U moet een geldige breedte ingeven',
	noHeight : 'U moet een hoogte ingeven',
	invalidHeight : 'U moet een geldige starttijd ingeven',
	invalidTime : 'Inform a valid start time',
	txtResponsive : 'Responsive video',
	txtNoEmbed : 'Alleen video afbeelding en link'
});
CKEDITOR.plugins.setLang('youtube', 'nn', {
	button : 'Bygg inn YouTube-video',
	title : 'Bygg inn YouTube-video',
	txtEmbed : 'Lim inn embed-kode her',
	txtUrl : 'Lim inn YouTube video-URL',
	txtWidth : 'Breidde',
	txtHeight : 'Høgde',
	chkRelated : 'Vis foreslåtte videoar når videoen er ferdig',
	txtStartAt : 'Start ved (ss eller mm:ss eller hh:mm:ss)',
	chkPrivacy : 'Bruk personverntilpassa modus',
	chkOlderCode : 'Bruk gamal embedkode',
	chkAutoplay: 'Spel automatisk',
	chkControls: 'Vis spillerkontrollene',
	noCode : 'Du må leggja inn ein embed-kode eller URL',
	invalidEmbed : 'Emded-koden du la inn ser ikkje ut til å vera gyldig',
	invalidUrl : 'URLen du la inn ser ikkje ut til å vera gyldig',
	or : 'eller',
	noWidth : 'Du må leggja inn breidde',
	invalidWidth : 'Legg inn ei gyldig breidde',
	noHeight : 'Du må leggja inn høgde',
	invalidHeight : 'Legg inn ei gyldig høgde',
	invalidTime : 'Legg inn gyldig starttid',
	txtResponsive : 'Gjer responsiv (ignorer breidde og høgde, tilpass breidda på sida)'
});
CKEDITOR.plugins.setLang('youtube', 'pl', {
	button : 'Załącznik wideo z YouTube',
	title : 'Załącznik wideo z YouTube',
	txtEmbed : 'Wklej kod do umieszczenia',
	txtUrl : 'Wklej adres URL do wideo z YouTube',
	txtWidth : 'Szerokość',
	txtHeight : 'Wysokość',
	chkRelated : 'Pokaż sugerowane filmy po zakończeniu odtwarzania',
	txtStartAt : 'Rozpocznij od (ss lub mm:ss lub gg:mm:ss)',
	chkPrivacy : 'Włącz rozszerzony tryb prywatności',
	chkOlderCode : 'Użyj starego kodu',
	chkAutoplay: 'Autoodtwarzanie',
	chkControls: 'Pokaż elementy sterujące odtwarzacza',
	noCode : 'Musisz wprowadzić kod lub adres URL',
	invalidEmbed : 'Wprowadzony kod nie jest poprawny',
	invalidUrl : 'Wprowadzony adres URL nie jest poprawny',
	or : 'lub',
	noWidth : 'Musisz wpisać szerokość',
	invalidWidth : 'Wprowadzona szerokość nie jest poprawna',
	noHeight : 'Musisz wprowadzić wysokość',
	invalidHeight : 'Wprowadzona wysokość nie jest poprawna',
	invalidTime : 'Musisz wprowadzić poprawny czas rozpoczęcia',
	txtResponsive : 'El. responsywny (ignoruj szerokość i wysokość, dopasuj do szerokości)'
});
CKEDITOR.plugins.setLang('youtube', 'pt-br', {
	button : 'Inserir Vídeo do Youtube',
	title : 'Inserir Vídeo do Youtube',
	txtEmbed : 'Cole aqui o código embed de um vídeo do Youtube',
	txtUrl : 'Cole aqui uma URL de um vídeo do Youtube',
	txtWidth : 'Largura',
	txtHeight : 'Altura',
	chkRelated : 'Mostrar vídeos sugeridos ao final do vídeo',
	txtStartAt : 'Iniciar em (ss ou mm:ss ou hh:mm:ss)',
	chkPrivacy : 'Ativar o modo de privacidade aprimorada',
	chkOlderCode : 'Usar código de incorporação antigo',
	chkAutoplay : 'Reproduzir automaticamente',
	chkControls: 'Mostrar controles do player',
	noCode : 'Você precisa informar um código embed ou uma URL',
	invalidEmbed : 'O código informado não parece ser válido',
	invalidUrl : 'A URL informada não parece ser válida',
	or : 'ou',
	noWidth : 'Você deve informar a largura do vídeo',
	invalidWidth : 'Informe uma largura válida',
	noHeight : 'Você deve informar a altura do vídeo',
	invalidHeight : 'Informe uma altura válida',
	invalidTime : 'O tempo informado é inválido',
	txtResponsive : 'Vídeo responsivo',
	txtNoEmbed : 'Somente imagem e link para o vídeo'
});
CKEDITOR.plugins.setLang('youtube', 'pt', {
	button : 'Inserir Vídeo do Youtube',
	title : 'Inserir Vídeo do Youtube',
	txtEmbed : 'Cole aqui o código embed de um vídeo do Youtube',
	txtUrl : 'Cole aqui uma URL de um vídeo do Youtube',
	txtWidth : 'Largura',
	txtHeight : 'Altura',
	chkRelated : 'Mostrar vídeos sugeridos quando o vídeo terminar',
	txtStartAt : 'Iniciar em (ss ou mm:ss ou hh:mm:ss)',
	chkPrivacy : 'Ativar o modo de privacidade otimizada',
	chkOlderCode : 'Usar código de incorporação antigo',
	chkAutoplay : 'Reproduzir automaticamente',
	chkControls: 'Mostrar controles do player',
	noCode : 'Você precisa informar um código embed ou uma URL',
	invalidEmbed : 'O código informado não parece ser válido',
	invalidUrl : 'A URL informada não parece ser válida',
	or : 'ou',
	noWidth : 'Você deve informar a largura do vídeo',
	invalidWidth : 'Informe uma largura válida',
	noHeight : 'Você deve informar a altura do vídeo',
	invalidHeight : 'Informe uma altura válida',
	invalidTime : 'O tempo informado é inválido',
	txtResponsive : 'Vídeo responsivo',
	txtNoEmbed : 'Somente imagem e link para o vídeo'
});
CKEDITOR.plugins.setLang('youtube', 'ru', {
	button : 'Вставить YouTube видео',
	title : 'Вставить YouTube видео',
	txtEmbed : 'Вставьте HTML-код сюда',
	txtUrl : 'Вставьте адрес видео (URL)',
	txtWidth : 'Ширина',
	txtHeight : 'Высота',
	chkRelated : 'Показать похожие видео после завершения просмотра',
	txtStartAt : 'Начать с (сс или мм:сс или чч:мм:сс)',
	chkPrivacy : 'Включить режим повышенной конфиденциальности',
	chkOlderCode : 'Использовать старый код вставки',
	chkAutoplay: 'Автозапуск',
	chkControls: 'Показать панель управления',
	noCode : 'Вы должны ввести HTML-код или адрес',
	invalidEmbed : 'Ваш HTML-код не похож на правильный',
	invalidUrl : 'Ваш адрес видео не похож на правильный',
	or : 'или',
	noWidth : 'Вы должны указать ширину',
	invalidWidth : 'Укажите правильную ширину',
	noHeight : 'Вы должны указать высоту',
	invalidHeight : 'Укажите правильную высоту',
	invalidTime : 'Укажите правильное время начала',
	txtResponsive : 'Растягиваемое видео',
	txtNoEmbed : 'Не встраивать видео (обложка-ссылка на YouTube)'
});
CKEDITOR.plugins.setLang('youtube', 'sk', {
	button : 'Vložiť YouTube video',
	title : 'Vložiť YouTube video',
	txtEmbed : 'Vložiť Youtube Embed Video kódu',
	txtUrl : 'Vložiť pomocou YouTube video URL',
	txtWidth : 'Šírka',
	txtHeight : 'Výška',
	chkRelated : 'Zobraziť odporúčané videá po prehratí',
	txtStartAt : 'Začať prehrávanie videa (ss alebo mm:ss alebo hh:mm:ss)',
	chkPrivacy : 'Povoliť pokročilý mód súkromia',
	chkOlderCode : 'Použiť starú metódu vkladania',
	chkAutoplay: 'Automatické prehrávanie',
	chkControls: 'Zobraziť ovládacie prvky prehrávača',
	noCode : 'Musíte vložiť Youtube Embed kód alebo URL',
	invalidEmbed : 'Vložený kód nie je valídny',
	invalidUrl : 'Vložená URL nie je platná',
	or : 'alebo',
	noWidth : 'Prosím, zadajte šírku videa',
	invalidWidth : 'Zadajte valídnu šírku videa',
	noHeight : 'Prosím, zadajte výšku videa',
	invalidHeight : 'Zadajte valídnu výšku videa',
	invalidTime : 'Zadajte valídny formát začiatku prehrávania videa',
	txtResponsive : 'Prispôsobit rozmery videa rozmerom obrazovky (ignoruje šírku a výšku, prispôsobí sa šírke obrazovky)'
});
CKEDITOR.plugins.setLang('youtube', 'tr', {
	button : 'Youtube Video Gömün (Embed)',
	title : 'Youtube Video',
	txtEmbed : 'Youtube gömülü kodu (embed) buraya yapıştırınız',
	txtUrl : 'Youtube linkinizi buraya yapıştırınız',
	txtWidth : 'Genişlik',
	txtHeight : 'Yükseklik',
	chkRelated : 'Önerilen videoları video bitiminde göster',
	txtStartAt : 'Video başlangıç anı (ss ya da dd:ss ya da ss:dd:ss)',
	chkPrivacy : 'Gizlilik modunu etkinleştir',
	chkOlderCode : 'Eski gömülü kodu (embed) kullan',
	chkAutoplay: 'Otomatik',
	chkControls: 'Oynatıcı kontrollerini göster',
	noCode : 'Gömülü kod (embed) veya url yapıştırmak zorundasınız',
	invalidEmbed : 'Verdiğiniz gömülü kod (embed) ile video bulunamadı',
	invalidUrl : 'Verdiğiniz linkte video bulunamadı',
	or : 'ya da',
	noWidth : 'Genişliği belirtmek zorundasınız',
	invalidWidth : 'Bir genişlik belirtin',
	noHeight : 'Yükseliği belirtmek zorundasınız',
	invalidHeight : 'Yükseklik belirtin',
	invalidTime : 'Başlangıç anını doğru girin, örneğin: 13 (13. saniye) ya da 12:25 (12. dakika 25. saniye) ya da 01.25.33 (1 saat 25 dakika 33 saniye)',
	txtResponsive : 'Responsive video'
});
CKEDITOR.plugins.setLang('youtube', 'uk', {
	button : 'Вставити YouTube-відео',
	title : 'Вставити YouTube-відео',
	txtEmbed : 'Вставте HTML-код сюди',
	txtUrl : 'Вставте URL-адресу сюди',
	txtWidth : 'Ширина',
	txtHeight : 'Висота',
	chkRelated : 'Показати пропоновані відео в кінці',
	txtStartAt : 'Почати з (сс або хх:сс або гг:хх:сс)',
	chkPrivacy : 'Увімкнути режим підвищеної конфіденційності',
	chkOlderCode : 'Використовувати старий код вставки',
	chkAutoplay: 'Автовідтворення',
	chkControls: 'Показувати елементи управління плеєром',
	noCode : 'Ви повинні ввести HTML-код або URL-адресу',
	invalidEmbed : 'Код вставки, який ви додали не вірний',
	invalidUrl : 'URL-адреса, яку ви додали не вірна',
	or : 'або',
	noWidth : 'Укажіть ширину',
	invalidWidth : 'Укажіть правильну ширину',
	noHeight : 'Укажіть висоту',
	invalidHeight : 'Укажіть правильну висоту',
	invalidTime : 'Укажіть правильний час початку',
	txtResponsive : 'Адаптивне (таке, яке розтягується) відео',
	txtNoEmbed : 'Додати лише обкладинку та посилання на YouTube'
});
CKEDITOR.plugins.setLang('youtube', 'vi', {
	button : 'Embed Youtube Video',
	title : 'Nhúng Video Youtube',
	txtEmbed : 'Dãn mã nhúng Embed vào đây',
	txtUrl : 'Dãn đường dẫn video Youtube',
	txtWidth : 'Rộng',
	txtHeight : 'Cao',
	chkRelated : 'Hiển thị các video được đề xuất khi video kết thúc',
	txtStartAt : 'Bắt đầu (ss hoặc mm:ss hoặc hh:mm:ss)',
	chkPrivacy : 'Kích hoạt chế độ bảo mật nâng cao',
	chkOlderCode : 'Sử dụng mã nhúng cũ',
	chkAutoplay: 'Tự động chạy video',
	chkControls: 'Hiển thị các điều khiển trình phát',
	noCode : 'Bạn phải nhập mã nhúng hoặc URL',
	invalidEmbed : 'Mã nhúng bạn đã nhập không đúng',
	invalidUrl : 'URL bạn đã nhập không đúng',
	or : 'hoặc',
	noWidth : 'Bạn phải chiều rộng',
	invalidWidth : 'Chiều rộng hợp lệ',
	noHeight : 'Bạn phải chiều cao',
	invalidHeight : 'Chiều cao hợp lệ',
	invalidTime : 'Thời gian bắt đầu không đúng',
	txtResponsive : 'Responsive video'
});
CKEDITOR.plugins.setLang('youtube', 'zh', {
	button: '嵌入 Youtube 影片',
	title: '嵌入 Youtube 影片',
	txtEmbed: '貼上嵌入碼',
	txtUrl: '貼上 Youtube 影片 URL',
	txtWidth: '寬',
	txtHeight: '高',
	txtResponsive: '使用自適應縮放模式 (忽略設定的長寬, 以寬為基準縮放)',
	chkRelated: '影片結束時顯示建議影片',
	txtStartAt: '開始時間 (ss or mm:ss or hh:mm:ss)',
	chkPrivacy: '啟用加強隱私模式',
	chkOlderCode: '使用舊的嵌入碼',
	chkAutoplay: '自動播放',
	chkControls: '显示播放器控件',
	noCode: '必須輸入嵌入碼',
	invalidEmbed: '錯誤的嵌入碼',
	invalidUrl: '錯誤的URL',
	or: '或',
	noWidth: '必須設定寬',
	invalidWidth: '寬設定錯誤',
	noHeight: '必須設定高',
	invalidHeight: '高設定錯誤',
	invalidTime: '開始時間設定錯誤'
});