/**
 * Light Views Counter - Tracker Script
 *
 * Smart view counting with scroll detection and localStorage caching.
 *
 * @package Light_Views_Counter
 * @since 1.0.0
 */

/* global lightvcData */

( function() {
	'use strict';

	// Check if lightvcData is available
	if ( 'undefined' === typeof lightvcData ) {
		return;
	}

	const config = {
		postId: lightvcData.postId,
		ajaxUrl: lightvcData.ajaxUrl,
		scrollThreshold: parseInt( lightvcData.scrollThreshold, 10 ) || 50,
		timeWindow: parseInt( lightvcData.timeWindow, 10 ) || 30,
		fastMode: lightvcData.fastMode || false,
		excludeBots: lightvcData.excludeBots !== false
	};

	const STORAGE_KEY = 'lightvc_viewed_posts';
	let hasScrolled = false;
	let hasCounted = false;
	let localStorageAvailable = false;

    /**
     * Check if localStorage is available and enabled.
     *
     * @return {boolean} True if localStorage is available.
     */
    function isLocalStorageAvailable() {
        try {
            const test = '__lightvc_test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Detect if the current visitor is a bot.
     *
     * This function checks multiple factors to identify bots:
     * - User agent string patterns
     * - Web driver properties (automated browsers)
     * - Missing browser features
     * - Headless browser detection
     *
     * @return {boolean} True if visitor is likely a bot.
     */
    function isBot() {
        // If bot exclusion is disabled, always return false
        if (!config.excludeBots) {
            return false;
        }

        // Check user agent for known bot patterns
        const userAgent = navigator.userAgent.toLowerCase();
        const botPatterns = [
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'sogou',
            'exabot',
            'facebot',
            'ia_archiver',
            'crawler',
            'spider',
            'bot',
            'facebookexternalhit',
            'twitterbot',
            'rogerbot',
            'linkedinbot',
            'embedly',
            'quora link preview',
            'showyoubot',
            'outbrain',
            'pinterest',
            'developers.google.com/+/web/snippet',
            'slackbot',
            'vkshare',
            'w3c_validator',
            'redditbot',
            'applebot',
            'whatsapp',
            'flipboard',
            'tumblr',
            'bitlybot',
            'skypeuripreview',
            'nuzzel',
            'discordbot',
            'qwantify',
            'pinterestbot',
            'bitrix',
            'headlesschrome',
            'phantomjs',
            'slimerjs',
            'ahrefsbot',
            'semrushbot',
            'dotbot',
            'mj12bot',
            'petalbot',
            'screaming frog',
            'sitebulb',
            'lighthouse'
        ];

        // Check if user agent matches any bot pattern
        for (let i = 0; i < botPatterns.length; i++) {
            if (userAgent.indexOf(botPatterns[i]) !== -1) {
                return true;
            }
        }

        // Check for web driver (Selenium, Puppeteer, etc.)
        if (navigator.webdriver) {
            return true;
        }

        // Check for headless browser indicators
        if (window.navigator.plugins && window.navigator.plugins.length === 0) {
            // Most headless browsers have no plugins
            // But this alone isn't conclusive, so combine with other checks
            if (!window.chrome || typeof window.chrome.runtime === 'undefined') {
                // Not Chrome or missing Chrome runtime - might be headless
                if (userAgent.indexOf('chrome') !== -1 || userAgent.indexOf('chromium') !== -1) {
                    return true; // Claims to be Chrome but doesn't have Chrome properties
                }
            }
        }

        // Check for missing expected browser features
        if (typeof navigator.languages === 'undefined' || !navigator.languages.length) {
            return true; // Real browsers always have languages
        }

        // Check for automation tools
        if (window.document.documentElement.getAttribute('webdriver')) {
            return true;
        }

        // Check for PhantomJS
        if (window._phantom || window.callPhantom) {
            return true;
        }

        // Check for Nightmare.js
        if (window.__nightmare) {
            return true;
        }

        // Check for Selenium
        if (window.document.$cdc_asdjflasutopfhvcZLmcfl_ || window.document.documentElement.getAttribute('selenium')) {
            return true;
        }

        // Check Chrome driver
        if (window.document.documentElement.getAttribute('driver')) {
            return true;
        }

        // All checks passed - likely a real user
        return false;
    }

    /**
     * Get viewed posts from localStorage.
     *
     * @return {Object} Object containing post IDs and timestamps.
     */
    function getViewedPosts() {
        if (!localStorageAvailable) {
            return {};
        }

        try {
            const data = localStorage.getItem(STORAGE_KEY);
            return data ? JSON.parse(data) : {};
        } catch (e) {
            console.error('LVC: localStorage error', e);
            return {};
        }
    }

    /**
     * Save viewed post to localStorage.
     *
     * @param {number} postId Post ID.
     */
    function saveViewedPost(postId) {
        if (!localStorageAvailable) {
            return;
        }

        try {
            const viewedPosts = getViewedPosts();
            viewedPosts[postId] = Date.now();

            // Clean up old entries (older than 7 days)
            const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
            Object.keys(viewedPosts).forEach(function(id) {
                if (viewedPosts[id] < sevenDaysAgo) {
                    delete viewedPosts[id];
                }
            });

            localStorage.setItem(STORAGE_KEY, JSON.stringify(viewedPosts));
        } catch (e) {
            console.error('LVC: localStorage save error', e);
        }
    }

    /**
     * Check if post was recently viewed.
     *
     * @param {number} postId Post ID.
     * @return {boolean} True if recently viewed.
     */
    function wasRecentlyViewed(postId) {
        // If localStorage not available, always allow counting
        if (!localStorageAvailable) {
            return false;
        }

        const viewedPosts = getViewedPosts();
        const lastViewed = viewedPosts[postId];

        if (!lastViewed) {
            return false;
        }

        const timeWindow = config.timeWindow * 60 * 1000; // Convert minutes to milliseconds
        const timeSinceView = Date.now() - lastViewed;

        return timeSinceView < timeWindow;
    }

    /**
     * Get scroll percentage.
     *
     * Handles edge case where content is shorter than viewport.
     *
     * @return {number} Scroll percentage (0-100).
     */
    function getScrollPercentage() {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        const trackLength = documentHeight - windowHeight;

        // If content fits in viewport (no scrolling possible), return 100%
        if (trackLength <= 0) {
            return 100;
        }

        const percentScrolled = Math.floor((scrollTop / trackLength) * 100);

        return Math.min(percentScrolled, 100);
    }

    /**
     * Send view count to server.
     */
    function countView() {
        if (hasCounted) {
            return;
        }

        hasCounted = true;

        const postData = JSON.stringify({
            post_id: config.postId
        });

        // Fast mode: Use sendBeacon API (fire-and-forget, no response)
        if (config.fastMode && typeof navigator.sendBeacon !== 'undefined') {
            const blob = new Blob([postData], { type: 'application/json' });
            const sent = navigator.sendBeacon(config.ajaxUrl, blob);

            if (sent) {
                // Assume success and save to localStorage
                saveViewedPost(config.postId);

                // Trigger custom event (no view count available in fast mode)
                if (typeof CustomEvent !== 'undefined') {
                    const event = new CustomEvent('lvcViewCounted', {
                        detail: {
                            postId: config.postId,
                            fastMode: true
                        }
                    });
                    document.dispatchEvent(event);
                }
            }
            return;
        }

        // Standard mode: Use fetch API for modern browsers, fallback to XMLHttpRequest
        if (typeof fetch !== 'undefined') {
            fetch(config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: postData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    saveViewedPost(config.postId);

                    // Trigger custom event
                    if (typeof CustomEvent !== 'undefined') {
                        const event = new CustomEvent('lvcViewCounted', {
                            detail: {
                                postId: config.postId,
                                viewCount: data.view_count
                            }
                        });
                        document.dispatchEvent(event);
                    }
                }
            })
            .catch(function(error) {
                console.error('LVC: Count failed', error);
                hasCounted = false; // Allow retry
            });
        } else {
            // Fallback for older browsers
            const xhr = new XMLHttpRequest();
            xhr.open('POST', config.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json');

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 400) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            saveViewedPost(config.postId);
                        }
                    } catch (e) {
                        console.error('LVC: Parse error', e);
                    }
                }
            };

            xhr.onerror = function() {
                console.error('LVC: Request failed');
                hasCounted = false; // Allow retry
            };

            xhr.send(postData);
        }
    }

    /**
     * Handle scroll event.
     */
    function handleScroll() {
        if (hasScrolled) {
            return;
        }

        const scrollPercentage = getScrollPercentage();

        if (scrollPercentage >= config.scrollThreshold) {
            hasScrolled = true;

            // Remove scroll listener
            window.removeEventListener('scroll', handleScroll);

            // Count the view
            countView();
        }
    }

    /**
     * Check if content is short enough to count immediately.
     *
     * @return {boolean} True if content fits in viewport.
     */
    function isContentShort() {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        return documentHeight <= windowHeight;
    }

    /**
     * Initialize the tracker.
     */
    function init() {
        // Check if visitor is a bot - exit early if true
        if (isBot()) {
            return;
        }

        // Check localStorage availability first
        localStorageAvailable = isLocalStorageAvailable();

        // Check if this post was recently viewed
        if (wasRecentlyViewed(config.postId)) {
            return;
        }

        // If scroll threshold is 0, count immediately
        if (config.scrollThreshold === 0) {
            countView();
            return;
        }

        // Wait for page to fully load before checking
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initializeScrollTracking();
            });
        } else {
            initializeScrollTracking();
        }
    }

    /**
     * Initialize scroll tracking after page is ready.
     */
    function initializeScrollTracking() {
        // Check if content is too short to require scrolling
        if (isContentShort()) {
            // Content fits in viewport - count view after short delay
            // This ensures user actually sees the content
            setTimeout(function() {
                countView();
            }, 1000);
            return;
        }

        // Attach scroll listener for longer content
        window.addEventListener('scroll', handleScroll, { passive: true });

        // Check initial scroll position (in case page loads scrolled)
        setTimeout(handleScroll, 500);
    }

    // Start the tracker
    init();

})();
