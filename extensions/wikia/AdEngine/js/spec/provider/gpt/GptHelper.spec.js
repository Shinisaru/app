/*global describe, it, expect, modules, spyOn*/
describe('ext.wikia.adEngine.provider.gptHelper', function () {
	'use strict';

	function noop() {}

	var AdElement,
		callbacks = [],
		mocks = {
			log: noop,
			googleTag: {
				isInitialized: function () {
					return true;
				},
				init: noop,
				registerCallback: function (id, callback) {
					callbacks.push(callback);
				},
				push: function (callback) {
					callback();
				},
				addSlot: noop,
				flush: noop
			},
			adDetect: {},
			slotTweaker: {
				show: noop,
				hide: noop
			},
			sraHelper: {
				shouldFlush: function () {
					return true;
				}
			}
		};

	function getModule() {
		return modules['ext.wikia.adEngine.provider.gptHelper'](
			mocks.log,
			mocks.googleTag,
			mocks.adDetect,
			AdElement,
			mocks.slotTweaker,
			mocks.sraHelper
		);
	}

	beforeEach(function() {
		AdElement = noop;

		AdElement.prototype.getId = function () {
			return 'TOP_LEADERBOARD';
		};

		AdElement.prototype.getNode = function () {
			return {};
		};

		AdElement.prototype.setResponseLevelParams = noop;

		spyOn(document, 'getElementById').and.returnValue({
			appendChild: noop
		});

		callbacks = [];
	});

	it('Initialize googletag when module is not initialized yet', function () {
		spyOn(mocks.googleTag, 'isInitialized').and.returnValue(false);
		spyOn(mocks.googleTag, 'init');

		getModule().pushAd('TOP_LEADERBOARD', '/foo/slot/path', {}, {});

		expect(mocks.googleTag.init).toHaveBeenCalled();
	});

	it('Prevent initializing googletag if module is already initialized', function () {
		spyOn(mocks.googleTag, 'init');

		getModule().pushAd('TOP_LEADERBOARD', '/foo/slot/path', {}, {});

		expect(mocks.googleTag.init).not.toHaveBeenCalled();
	});

	it('Push and flush ATF slot when SRA is not enabled', function () {
		spyOn(mocks.googleTag, 'push');
		spyOn(mocks.googleTag, 'flush');

		getModule().pushAd('TOP_LEADERBOARD', '/foo/slot/path', {}, {});

		expect(mocks.googleTag.push).toHaveBeenCalled();
		expect(mocks.googleTag.flush).toHaveBeenCalled();
	});

	it('Only push ATF slot when SRA is enabled', function () {
		spyOn(mocks.googleTag, 'push');
		spyOn(mocks.googleTag, 'flush');
		spyOn(mocks.sraHelper, 'shouldFlush').and.returnValue(false);

		getModule().pushAd('TOP_LEADERBOARD', '/foo/slot/path', {}, { sraEnabled: true });

		expect(mocks.googleTag.push).toHaveBeenCalled();
		expect(mocks.googleTag.flush).not.toHaveBeenCalled();
	});

	it('Always push and flush BTF slot even if SRA is enabled', function () {
		spyOn(mocks.googleTag, 'push');
		spyOn(mocks.googleTag, 'flush');

		getModule().pushAd('TOP_RIGHT_BOXAD', '/foo/slot/path', {}, { sraEnabled: true });

		expect(mocks.googleTag.push).toHaveBeenCalled();
		expect(mocks.googleTag.flush).toHaveBeenCalled();
	});

	it('Prevent push when given slot is flushOnly', function () {
		spyOn(mocks.googleTag, 'push');
		spyOn(mocks.googleTag, 'flush');

		getModule().pushAd('GPT_FLUSH', '/foo/slot/path', { flushOnly: true }, {});

		expect(mocks.googleTag.push).not.toHaveBeenCalled();
		expect(mocks.googleTag.flush).toHaveBeenCalled();
	});

	it('Add and show slot on push', function () {
		spyOn(mocks.googleTag, 'addSlot');
		spyOn(mocks.slotTweaker, 'show');

		getModule().pushAd('TOP_RIGHT_BOXAD', '/foo/slot/path', {}, {});

		expect(mocks.googleTag.addSlot).toHaveBeenCalled();
		expect(mocks.slotTweaker.show).toHaveBeenCalled();
	});

	it('Register slot callback on push', function () {
		getModule().pushAd('TOP_RIGHT_BOXAD', '/foo/slot/path', {}, {});

		expect(callbacks.length).toEqual(1);
	});
});
