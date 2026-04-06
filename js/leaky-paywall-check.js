/**
 * Leaky Paywall Restriction Check
 *
 * Optimized replacement for leaky-paywall-cookie.js
 * Uses REST API instead of admin-ajax.php for better performance.
 *
 * @package Leaky Paywall
 * @since 4.23.0
 */

(function () {
	'use strict';

	var config = window.leaky_paywall_check_config || {};
	var STORAGE_KEY = 'lp_viewed_content';

	/**
	 * Dispatch a custom event for other plugins to listen to
	 *
	 * @param {string} eventName
	 * @param {Object} detail
	 */
	function dispatchPaywallEvent(eventName, detail) {
		var event = new CustomEvent(eventName, {
			detail: detail,
			bubbles: true,
			cancelable: true
		});

		document.dispatchEvent(event);
	}

	/**
	 * Get post ID from body classes
	 *
	 * @returns {number|null}
	 */
	function getPostIdFromBody() {
		var body = document.body;
		if (!body) {
			return null;
		}

		var classes = body.className.split(/\s+/);
		var postId = null;

		for (var i = 0; i < classes.length; i++) {
			var cls = classes[i];

			// Match postid-123 pattern
			if (cls.indexOf('postid-') === 0) {
				postId = parseInt(cls.substring(7), 10);
				if (postId > 0) {
					return postId;
				}
			}

			// Match page-id-123 pattern
			if (cls.indexOf('page-id-') === 0) {
				postId = parseInt(cls.substring(8), 10);
				if (postId > 0) {
					return postId;
				}
			}
		}

		return null;
	}

	/**
	 * Get viewed content from localStorage
	 *
	 * @returns {Object}
	 */
	function getViewedContent() {
		try {
			var stored = localStorage.getItem(STORAGE_KEY);
			if (stored) {
				return JSON.parse(stored);
			}
		} catch (e) {
			// localStorage not available or invalid JSON
		}
		return {};
	}

	/**
	 * Save viewed content to localStorage
	 *
	 * @param {Object} content
	 */
	function saveViewedContent(content) {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify(content));
		} catch (e) {
			// localStorage not available
		}
	}

	/**
	 * Update viewed content with new post
	 *
	 * @param {number} postId
	 * @param {string} postType
	 * @param {number} expiration
	 */
	function updateViewedContent(postId, postType, expiration) {
		var content = getViewedContent();

		if (!content[postType]) {
			content[postType] = {};
		}

		content[postType][postId] = expiration;
		saveViewedContent(content);
	}

	/**
	 * Clean expired entries from viewed content
	 */
	function cleanExpiredContent() {
		var content = getViewedContent();
		var now = Math.floor(Date.now() / 1000);
		var hasChanges = false;

		for (var postType in content) {
			if (content.hasOwnProperty(postType)) {
				for (var postId in content[postType]) {
					if (content[postType].hasOwnProperty(postId)) {
						if (content[postType][postId] < now) {
							delete content[postType][postId];
							hasChanges = true;
						}
					}
				}

				// Remove empty post type objects
				if (Object.keys(content[postType]).length === 0) {
					delete content[postType];
					hasChanges = true;
				}
			}
		}

		if (hasChanges) {
			saveViewedContent(content);
		}

		return content;
	}

	/**
	 * Get content containers based on post type
	 *
	 * @param {number} postId
	 * @returns {NodeList}
	 */
	function getContentContainers(postId) {
		var body = document.body;
		var containerSetting;

		// Check if this is a page or post
		if (body.classList.contains('page-id-' + postId)) {
			containerSetting = config.page_container || '';
		} else {
			containerSetting = config.post_container || '';
		}

		if (!containerSetting) {
			return [];
		}

		// Handle comma-separated selectors
		var selectors = containerSetting.split(',').map(function (s) {
			return s.trim();
		}).filter(function (s) {
			return s.length > 0;
		});

		var containers = [];
		selectors.forEach(function (selector) {
			var elements = document.querySelectorAll(selector);
			for (var i = 0; i < elements.length; i++) {
				containers.push(elements[i]);
			}
		});

		return containers;
	}

	/**
	 * Extract lead-in content from container
	 *
	 * @param {Element} container
	 * @param {number} numElements
	 * @returns {string}
	 */
	function getLeadInContent(container, numElements) {
		if (!numElements || numElements <= 0) {
			return '';
		}

		var children = container.children;
		var leadIn = '';

		for (var i = 0; i < Math.min(numElements, children.length); i++) {
			leadIn += children[i].outerHTML;
		}

		return leadIn;
	}

	/**
	 * Display the paywall content
	 *
	 * @param {string} nagContent
	 * @param {number} postId
	 */
	function displayPaywall(nagContent, postId) {
		var containers = getContentContainers(postId);
		var leadInElements = parseInt(config.lead_in_elements, 10) || 0;

		containers.forEach(function (container, index) {
			var leadIn = getLeadInContent(container, leadInElements);

			if (containers.length > 1 && index > 0) {
				// For multiple containers, clear all but the first
				container.innerHTML = '';
			} else {
				container.innerHTML = leadIn + nagContent;
			}
			container.style.visibility = 'visible';
		});
	}

	/**
	 * Show the content (no paywall needed)
	 *
	 * @param {number} postId
	 */
	function showContent(postId) {
		var containers = getContentContainers(postId);

		containers.forEach(function (container) {
			container.style.visibility = 'visible';
		});
	}

	/**
	 * Check restrictions via REST API
	 *
	 * @param {number} postId
	 */
	function checkRestrictions(postId) {
		// Clean expired content first
		var viewedContent = cleanExpiredContent();

		var restUrl = config.rest_url || '/wp-json/leaky-paywall/v1/check-restrictions';

		// Build request
		var requestBody = {
			post_id: postId,
			viewed_content: viewedContent
		};

		var headers = {
			'Content-Type': 'application/json'
		};

		// Only send the nonce for logged-in users. For anonymous visitors,
		// omitting the header avoids 403 errors when a page cache serves a stale nonce.
		if ( config.nonce && document.body.classList.contains('logged-in') ) {
			headers['X-WP-Nonce'] = config.nonce;
		}

		fetch(restUrl, {
			method: 'POST',
			headers: headers,
			body: JSON.stringify(requestBody),
			credentials: 'same-origin'
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Network response was not ok');
				}
				return response.json();
			})
			.then(function (data) {
				if (data.show_paywall && data.nag_content) {
					// If List Builder is active, skip the in-content nag —
					// List Builder's slider will handle the display via the event.
					if (window.LP_LIST_BUILDER) {
						showContent(postId);
					} else {
						displayPaywall(data.nag_content, postId);
					}

					// Dispatch event for other plugins to hook into
					dispatchPaywallEvent('leaky_paywall_shown', {
						postId: postId,
						response: data
					});
				} else {
					showContent(postId);

					// Dispatch event for content access
					dispatchPaywallEvent('leaky_paywall_access_granted', {
						postId: postId,
						response: data
					});
				}

				// Update viewed content if provided in response
				if (data.viewed_content) {
					saveViewedContent(data.viewed_content);
				}
			})
			.catch(function (error) {
				// On error, show content to avoid blocking users
				console.error('Leaky Paywall check failed:', error);
				showContent(postId);
			});
	}

	/**
	 * Initialize
	 */
	function init() {
		var postId = getPostIdFromBody();

		if (!postId) {
			return;
		}

		checkRestrictions(postId);
	}

	// Run on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Expose for external use if needed
	window.LeakyPaywallCheck = {
		checkRestrictions: checkRestrictions,
		getViewedContent: getViewedContent,
		updateViewedContent: updateViewedContent,
		cleanExpiredContent: cleanExpiredContent
	};

})();
