/* This file is part of DBSR.
 *
 * DBSR is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * DBSR is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DBSR.  If not, see <http://www.gnu.org/licenses/>.
 */
jQuery(function($) {
	// Do the initalization AJAX request
	$.ajax({
		type: 'POST',
		url: '?ajax&initialize',
		dataType: 'json',
		success: function(response, status, xhr) {
			// Update the forms using response
			for(i in response.data) {
				$('.data-' + i).each(function() {
					// Input-elements can't use the .text() method
					if($(this).is('input')) {
						// And checkboxes need setting the "checked" attribute instead of their value
						if($(this).is(':checkbox')) {
							$(this).prop('checked', response.data[i]);
						} else {
							$(this).val(response.data[i]);
						}
					} else {
						if(response.data[i] === null) response.data[i] = '';
						$(this).text(response.data[i]);
					}
				});
			}

			// Fix port number display
			$('span.prefix-colon:not(:empty)').each(function() {
				if($(this).text().substring(0, 1) != ':') $(this).text(':' + $(this).text());
			});

			// Self-destruct authorized?
			if(response.selfdestruct) {
				$('#selfdestruct').prop('disabled', false);
				$('#selfdestruct').change(function() {
					$('#container .slide:last-child > div form input:submit').val($(this).is(':checked') ? 'Self-destruct' : 'Restart');
				});
			} else {
				$('#selfdestruct, label[for="selfdestruct"]').remove();
			}
		}
	});

	// Save the window width for responsive optimalisation
	var windowWidth = $('body').width();

	// Options for the liteAccordion
	var liteAccordionOptions = {
		containerWidth: windowWidth,
		enumerateSlides: true,
		linkable: false
	};

	// Build the liteAccordion
	$('#container').liteAccordion(liteAccordionOptions);

	// Bind resize event to make it responsive
	$(window).on('resize orientationChanged', function() {
		// Get the current width
		var currentWidth = $('body').width();

		// Only rebuild if the width changed (height is all CSS)
		if(windowWidth != currentWidth) {
			// Rebuild the liteAccordion with the new width
			$('#container').liteAccordion('destroy').liteAccordion($.extend(liteAccordionOptions, {
				containerWidth: currentWidth
			}));

			// Save the new width
			windowWidth = currentWidth;
		}
	});

	// Remove default styling, use our own one from the CSS instead
	$.blockUI.defaults.css = {};
	$.blockUI.defaults.overlayCSS = {};
	$.blockUI.defaults.growlCSS = {};

	// Block all steps but the first
	$('#container .slide:not(:first-child) > div > div').block({
		message: null,
		overlayCSS: {
			cursor: 'default'
		}
	});

	// Value display
	$('#container .values-switch a').click(function(e) {
		// Show the correct set
		$(this).parents('table').find('tbody').not('.' + $(this).attr('class').replace(/\s/, '.')).css('display', 'none');
		$(this).parents('table').find('tbody.' + $(this).attr('class').replace(/\s/, '.')).css('display', 'table-row-group');

		// Style the link
		$(this).siblings().css('font-weight', 'normal');
		$(this).css('font-weight', 'bolder');
		return false;
	});
	$('#container .values-switch a.values-raw').click();

	// Add / remove value buttons
	$('#addField').click(function() {
		var i = $('#srFields').children().length;
		$('#srFields').append('<tr><td><textarea name="search[' + i + ']" rows="10" cols="50"></textarea></td><td><textarea name="replace[' + i + ']" rows="10" cols="50"></textarea></td></tr>')

		if($('#srFields').children().length > 1) {
			$('#removeField').prop('disabled', false);
		}
	});
	$('#removeField').click(function() {
		if($('#srFields').children().length > 1) {
			$('#srFields').children(':last-child').remove();
		}
		if($('#srFields').children().length == 1) {
			$(this).prop('disabled', true);
		}
	});

	// Previous / next buttons
	$('#container .slide > div form input.prev').click(function(e) {
		$(this).parents('.slide').prev('.slide').children('h2').click();
		return false;
	});
	$('#container .slide:not(:last-child) > div form').submit(function(e) {
		// Save $(this)
		var $this = $(this);
		var $next = $this.parents('.slide').next('.slide').children('div').children('div');
		var $prev = $this.parents('.slide').prev('.slide').children('div').children('div');
		var $nexts = $this.parents('.slide').siblings('.slide').slice($this.parents('.slide').index()).children('div').children('div');
		var $prevs = $this.parents('.slide').siblings('.slide').slice(0, $this.parents('.slide').index()).children('div').children('div');

		if($next.parents('.slide').is(':last-child') && $this.find('#confirmed').is(':not(:checked)')) {
			$this.find('.errormessage').text('Please confirm the data stated above is correct!');
			return false;
		}

		// Stepping lock
		if(window.DBSR_stepping) return false;
		window.DBSR_stepping = true;

		// Block all next steps
		$nexts.block({
			message: null,
			overlayCSS: {
				cursor: 'default'
			}
		})

		// Show loader on next step
		$next.block({message: 'Processing data from previous step...'});

		// Variable for storing (optional) slide timeouts
		var slideTimeout;

		// Check if we're proceeding to the last step
		if($next.parents('.slide').is(':last-child')) {
			$next.block({message: 'Executing search and replace...'});
			$this.parents('.slide').next('.slide').children('h2').not('.selected').click();
			$('#container .slide:not(:last-child) > div > div').block({
				message: null,
				overlayCSS: {
					cursor: 'default'
				}
			});
		} else {
			// For all other steps, wait a short time before moving along to the next slide for improved user feedback
			slideTimeout = setTimeout(function() {
				$this.parents('.slide').next('.slide').children('h2').not('.selected').click();
			}, 400);
		}

		// Do the AJAX request
		$.ajax({
			type: 'POST',
			url: '?ajax&step',
			data: $this.serialize(),
			dataType: 'json',
			success: function(response, status, xhr) {
				// Clear the sliding timeout
				clearTimeout(slideTimeout);

				// Validation successful?
				if(response.valid) {
					// Remove the last message
					$this.find('.errormessage').text('');

					// Set next error message (pretty rare)
					if(response.error) $next.find('.errormessage').text(response.error);

					// Update the forms using response
					if(response.data) for(i in response.data) {
						$('#container .data-' + i).each(function() {
							// Input-elements can't use the .text() method
							if($(this).is('input')) {
								// And checkboxes need setting the "checked" attribute instead of their value
								if($(this).is(':checkbox')) {
									$(this).prop('checked', response.data[i]);
								} else {
									$(this).val(response.data[i]);
								}
							} else {
								$(this).text(response.data[i]);
							}
						});
					}
					if(response.html) for(i in response.html) {
						$('#container .html-' + i).each(function() {
							// Set the HTML
							$(this).html(response.html[i]);
						});
					}

					// Reselect raw values
					$('#container .values-switch a.values-raw').click();

					// Remove loader
					$next.unblock();

					// Move to the next step
					$this.parents('.slide').next('.slide').children('h2').not('.selected').click();

					// Stepping lock
					window.DBSR_stepping = false;
				} else {
					// Display message and go back (if needed)
					$this.find('.errormessage').text(response.error);
					$this.parents('.slide').children('div').children('div').unblock();
					$this.parents('.slide').children('h2').not('.selected').click();

					// Block next steps
					$nexts.block({
						message: null,
						overlayCSS: {
							cursor: 'default'
						}
					});

					// Unblock previous steps
					$prevs.unblock();

					// Stepping lock
					window.DBSR_stepping = false;
				}
			},
			error: function(response, status, xhr) {
				// Show error
				$next.block({message: 'Error processing request: ' + status});
				$this.parents('.slide').next('.slide').children('h2').not('.selected').click();

				// Stepping lock
				window.DBSR_stepping = false;
			}
		});
		return false;
	});
	$('#container .slide:last-child > div form input:submit').click(function(e) {
		if($('#selfdestruct').is(':checked')) {
			$('#container .slide:last-child > div > div').block({
				message: 'Self-destruction in progress...'
			});
			$.ajax({
				type: 'POST',
				url: '?ajax&selfdestruct',
				dataType: 'json',
				success: function(response, status, xhr) {
					if(response) {
						$('#container .slide:last-child > div > div').block({
							message: 'Self-destruction succesful!',
							css: {
								cursor: 'default'
							},
							overlayCSS: {
								cursor: 'default'
							}
						});
					} else {
						$('#container .slide:last-child > div > div').block({
							message: 'Failed to self-destruct, please delete this file manually!',
							css: {
								cursor: 'default'
							},
							overlayCSS: {
								cursor: 'default'
							}
						});
					}
				},
				error: function(response, status, xhr) {
					$('#container .slide:last-child > div > div').block({
						message: 'Error while self-destructing: ' + status,
						overlayCSS: {
							cursor: 'default'
						}
					});
				}
			});
		} else {
			$('#confirmed').prop('checked', false);
			$('#container .slide:last-child > div > div').block({
				message: null,
				overlayCSS: {
					cursor: 'default'
				}
			});
			$('#container .slide:first-child > div > div').unblock();
			$('#container .slide:first-child h2').click();
		}
		return false;
	});

	// Autocompletion
	var cache = {};
	$('input.autocomplete').autocomplete({
		minLength: 1,
		source: function(request, response) {
			// Check the cache
			var spu = $('#db_user').val() + '@' + $('#db_host').val() + ':' + $('#db_port').val();
			var id = $(this.element).attr('id');
			var term = request.term;
			if(spu in cache && id in cache[spu] && term in cache[spu][id]) {
				// Cache hit
				response(cache[spu][id][term]);
				return;
			}

			// Save the id
			request.id = id;

			// AJAX request
			$.ajax({
				type: 'POST',
				url: '?ajax&autocomplete',
				data: $.param(request) + '&' + $(this.element[0].form).serialize(),
				dataType: 'json',
				success: function(data, status, xhr) {
					if(data.length > 0) {
						// Save to the cache
						if(!cache[spu]) cache[spu] = {};
						if(!cache[spu][id]) cache[spu][id] = {};
						cache[spu][id][term] = data;
					}

					// Return data
					response(data);
				}
			});
		}
	});
});
