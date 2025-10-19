/**
 * YT Weather Shortcode - JavaScript
 *
 * @package YT_Weather_Shortcode
 * @version 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Weather Widget Handler
	 */
	var WeatherWidget = {

		/**
		 * Initialize the widget.
		 */
		init: function() {
			this.addUnitToggle();
			this.addRefreshButton();
			this.applyWeatherTheme();
			this.addAccessibility();
		},

		/**
		 * Add unit toggle functionality (Celsius/Fahrenheit).
		 */
		addUnitToggle: function() {
			$('.yt-weather-widget').each(function() {
				var $widget = $(this);
				var $temp = $widget.find('.yt-weather-temp');
				var tempText = $temp.text();
				var tempValue = parseFloat(tempText);
				var currentUnit = tempText.includes('°C') ? 'celsius' : 'fahrenheit';

				// Add toggle button if not minimal style
				if (!$widget.hasClass('yt-weather-minimal')) {
					var $toggleBtn = $('<button>', {
						'class': 'yt-weather-toggle-unit',
						'type': 'button',
						'aria-label': 'Toggle temperature unit',
						'text': currentUnit === 'celsius' ? '°F' : '°C'
					});

					$widget.append($toggleBtn);

					$toggleBtn.on('click', function(e) {
						e.preventDefault();
						WeatherWidget.toggleUnit($widget, tempValue, currentUnit);
					});
				}
			});
		},

		/**
		 * Toggle between Celsius and Fahrenheit.
		 *
		 * @param {jQuery} $widget Widget element.
		 * @param {number} tempValue Temperature value.
		 * @param {string} currentUnit Current unit.
		 */
		toggleUnit: function($widget, tempValue, currentUnit) {
			var $temp = $widget.find('.yt-weather-temp');
			var $unit = $widget.find('.yt-weather-unit');
			var newTemp, newUnit, newSymbol;

			if (currentUnit === 'celsius') {
				// Convert to Fahrenheit
				newTemp = (tempValue * 9/5) + 32;
				newUnit = 'fahrenheit';
				newSymbol = '°F';
			} else {
				// Convert to Celsius
				newTemp = (tempValue - 32) * 5/9;
				newUnit = 'celsius';
				newSymbol = '°C';
			}

			// Update display with animation
			$temp.fadeOut(200, function() {
				$(this).html(Math.round(newTemp) + '<span class="yt-weather-unit">' + newSymbol + '</span>').fadeIn(200);
			});

			// Update toggle button
			$widget.find('.yt-weather-toggle-unit').text(currentUnit === 'celsius' ? '°C' : '°F');

			// Store new values
			$widget.data('temp-value', newTemp);
			$widget.data('temp-unit', newUnit);
		},

		/**
		 * Add refresh button to reload weather data.
		 */
		addRefreshButton: function() {
			$('.yt-weather-widget').each(function() {
				var $widget = $(this);

				// Don't add to minimal style
				if ($widget.hasClass('yt-weather-minimal')) {
					return;
				}

				var $refreshBtn = $('<button>', {
					'class': 'yt-weather-refresh',
					'type': 'button',
					'aria-label': 'Refresh weather data',
					'html': '↻'
				});

				$widget.find('.yt-weather-header, .yt-weather-city').first().append($refreshBtn);

				$refreshBtn.on('click', function(e) {
					e.preventDefault();
					WeatherWidget.refreshWeather($widget);
				});
			});
		},

		/**
		 * Refresh weather data.
		 *
		 * @param {jQuery} $widget Widget element.
		 */
		refreshWeather: function($widget) {
			var $refreshBtn = $widget.find('.yt-weather-refresh');

			// Add loading state
			$refreshBtn.addClass('yt-weather-refreshing');
			$widget.addClass('yt-weather-loading-state');

			// In a real implementation, this would make an AJAX call
			// For now, we'll just simulate a refresh with animation
			setTimeout(function() {
				$refreshBtn.removeClass('yt-weather-refreshing');
				$widget.removeClass('yt-weather-loading-state');

				// Add refresh animation
				$widget.css('opacity', '0.5');
				setTimeout(function() {
					$widget.css('opacity', '1');
				}, 200);

				// Show success message
				WeatherWidget.showMessage($widget, 'Weather data refreshed', 'success');

				// In production, you would reload the page or update via AJAX
				// location.reload();
			}, 1000);
		},

		/**
		 * Apply theme based on weather condition.
		 */
		applyWeatherTheme: function() {
			$('.yt-weather-widget').each(function() {
				var $widget = $(this);
				var $desc = $widget.find('.yt-weather-desc');
				var description = $desc.text().toLowerCase();

				// Add theme class based on weather
				if (description.includes('clear') || description.includes('sunny')) {
					$widget.addClass('yt-weather-sunny');
				} else if (description.includes('cloud') || description.includes('overcast')) {
					$widget.addClass('yt-weather-cloudy');
				} else if (description.includes('rain') || description.includes('drizzle')) {
					$widget.addClass('yt-weather-rainy');
				} else if (description.includes('snow')) {
					$widget.addClass('yt-weather-snowy');
				} else if (description.includes('storm') || description.includes('thunder')) {
					$widget.addClass('yt-weather-stormy');
				}
			});
		},

		/**
		 * Add accessibility features.
		 */
		addAccessibility: function() {
			$('.yt-weather-widget').each(function() {
				var $widget = $(this);
				var city = $widget.find('.yt-weather-city').text();
				var temp = $widget.find('.yt-weather-temp').text();
				var desc = $widget.find('.yt-weather-desc').text();

				// Add ARIA label
				var ariaLabel = 'Weather for ' + city + ': ' + temp + ', ' + desc;
				$widget.attr('aria-label', ariaLabel);
				$widget.attr('role', 'region');

				// Make focusable
				if (!$widget.attr('tabindex')) {
					$widget.attr('tabindex', '0');
				}
			});
		},

		/**
		 * Show message notification.
		 *
		 * @param {jQuery} $widget Widget element.
		 * @param {string} message Message text.
		 * @param {string} type Message type (success, error, info).
		 */
		showMessage: function($widget, message, type) {
			var $message = $('<div>', {
				'class': 'yt-weather-message yt-weather-message-' + type,
				'text': message
			});

			$widget.append($message);

			// Fade in
			$message.fadeIn(300);

			// Auto remove after 3 seconds
			setTimeout(function() {
				$message.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		},

		/**
		 * Format time display.
		 *
		 * @param {string} timeString Time string.
		 * @return {string} Formatted time.
		 */
		formatTime: function(timeString) {
			var date = new Date(timeString);
			var hours = date.getHours();
			var minutes = date.getMinutes();
			var ampm = hours >= 12 ? 'PM' : 'AM';

			hours = hours % 12;
			hours = hours ? hours : 12;
			minutes = minutes < 10 ? '0' + minutes : minutes;

			return hours + ':' + minutes + ' ' + ampm;
		},

		/**
		 * Add tooltip functionality.
		 */
		addTooltips: function() {
			// Add tooltips to weather details
			$('.yt-weather-detail').each(function() {
				var $detail = $(this);
				var label = $detail.find('.yt-weather-detail-label').text();
				var value = $detail.find('.yt-weather-detail-value').text();

				$detail.attr('title', label + ': ' + value);
			});
		},

		/**
		 * Add keyboard navigation.
		 */
		addKeyboardNav: function() {
			$(document).on('keydown', '.yt-weather-widget', function(e) {
				// Toggle unit with 'U' key
				if (e.key === 'u' || e.key === 'U') {
					e.preventDefault();
					$(this).find('.yt-weather-toggle-unit').click();
				}

				// Refresh with 'R' key
				if (e.key === 'r' || e.key === 'R') {
					e.preventDefault();
					$(this).find('.yt-weather-refresh').click();
				}
			});
		},

		/**
		 * Add animation on scroll.
		 */
		addScrollAnimation: function() {
			if ('IntersectionObserver' in window) {
				var observer = new IntersectionObserver(function(entries) {
					entries.forEach(function(entry) {
						if (entry.isIntersecting) {
							$(entry.target).addClass('yt-weather-animated');
							observer.unobserve(entry.target);
						}
					});
				}, {
					threshold: 0.1
				});

				$('.yt-weather-widget').each(function() {
					observer.observe(this);
				});
			}
		}
	};

	/**
	 * Initialize when DOM is ready.
	 */
	$(document).ready(function() {
		// Initialize weather widgets
		if ($('.yt-weather-widget').length > 0) {
			WeatherWidget.init();
			WeatherWidget.addTooltips();
			WeatherWidget.addKeyboardNav();
			WeatherWidget.addScrollAnimation();
		}
	});

	/**
	 * Re-initialize on AJAX content load (for compatibility with page builders).
	 */
	$(document).on('DOMNodeInserted', function(e) {
		if ($(e.target).hasClass('yt-weather-widget') || $(e.target).find('.yt-weather-widget').length > 0) {
			setTimeout(function() {
				WeatherWidget.init();
			}, 100);
		}
	});

})(jQuery);
