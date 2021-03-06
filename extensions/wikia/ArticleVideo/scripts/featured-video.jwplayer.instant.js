require([
	'wikia.window',
	'ext.wikia.adEngine.adContext',
	'wikia.articleVideo.featuredVideo.jwplayer.instance',
	'wikia.articleVideo.featuredVideo.data',
	'wikia.articleVideo.featuredVideo.ads',
	'wikia.articleVideo.featuredVideo.autoplay',
	'wikia.articleVideo.featuredVideo.moatTracking',
	'wikia.articleVideo.featuredVideo.tracking',
	'wikia.articleVideo.featuredVideo.jwplayer.icons',
	'wikia.articleVideo.featuredVideo.events',
	'wikia.articleVideo.featuredVideo.jwplayer.logger',
	'wikia.articleVideo.featuredVideo.jwplayer.plugin.settings',
	require.optional('ext.wikia.adEngine.lookup.a9')
], function (
	win,
	adContext,
	playerInstance,
	videoDetails,
	featuredVideoAds,
	featuredVideoAutoplay,
	featuredVideoMoatTracking,
	featuredVideoTracking,
	playerIcons,
	featuredVideoEvents,
	logger,
	wikiaJWSettings,
	a9
) {
	if (!videoDetails) {
		return;
	}

	var videoId = videoDetails.mediaId,
		inNextVideoAutoplayCountries = featuredVideoAutoplay.inNextVideoAutoplayCountries,
		//Fallback to the generic playlist when no recommended videos playlist is set for the wiki
		recommendedPlaylist = videoDetails.recommendedVideoPlaylist || 'Y2RWCKuS',
		willAutoplay = featuredVideoAutoplay.willAutoplay,
		pausedOnRelated = false;

	function handleTabNotActive(willAutoplay) {
		document.addEventListener('visibilitychange', function () {
			if (canPlayVideo(willAutoplay)) {
				playerInstance.play(true);
				pausedOnRelated = false;
			}
		}, false);

		playerInstance.on('relatedVideoPlay', function () {
			if (document.hidden) {
				playerInstance.pause();
				pausedOnRelated = true;
			}
		});
	}

	function canPlayVideo(willAutoplay) {
		return !document.hidden && willAutoplay && (['playing', 'paused', 'complete'].indexOf(playerInstance.getState()) === -1 || pausedOnRelated);
	}

	function setupPlayer(bidParams) {
		logger.info('jwplayer setupPlayer');
		wikiaJWSettings();
		playerInstance.setup({
			advertising: {
				autoplayadsmuted: willAutoplay,
				client: 'googima',
				vpaidcontrols: true
			},
			autostart: willAutoplay && !document.hidden,
			description: videoDetails.description,
			image: '//content.jwplatform.com/thumbs/' + videoId + '-640.jpg',
			mute: willAutoplay,
			playlist: videoDetails.playlist,
			related: {
				autoplaytimer: 3,
				file: 'https://cdn.jwplayer.com/v2/playlists/' + recommendedPlaylist + '?related_media_id=' + videoId,
				oncomplete: inNextVideoAutoplayCountries ? 'autoplay' : 'show'
			},
			title: videoDetails.title,
			plugins: {
				wikiaSettings: null
			}

		});
		logger.info('jwplayer after setup');

		logger.subscribeToPlayerErrors(playerInstance);
		featuredVideoAds(playerInstance, bidParams);
		featuredVideoEvents(playerInstance, willAutoplay);
		featuredVideoTracking(playerInstance, willAutoplay);
		featuredVideoMoatTracking(playerInstance);
		handleTabNotActive(willAutoplay);
		playerIcons(document.querySelector('.featured-video'), playerInstance);
	}

	if (a9 && adContext.get('bidders.a9Video')) {
		a9.waitForResponse()
			.then(function () {
				return a9.getSlotParams('FEATURED');
			})
			.catch(function () {
				return {};
			})
			.then(function (bidParams) {
				setupPlayer(bidParams);
			});
	} else {
		setupPlayer();
	}

	// XW-4157 PageFair causes pausing the video, as a workaround we play video again when it's paused
	win.addEventListener('wikia.blocking', function () {
		if (playerInstance) {
			if (playerInstance.getState() === 'paused') {
				playerInstance.play();
			} else {
				playerInstance.once('pause', function (event) {
					// when video is paused because of PageFair pauseReason is undefined,
					// otherwise it's set to `interaction` when paused by user or `external` when paused by pause() function
					if (!event.pauseReason) {
						playerInstance.play();
					}
				});
			}
		}
	});
});
