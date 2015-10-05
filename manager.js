/**/


//	Onload
//==========================================================
$(function() {
	
	//Only run this stuff if we're at the console and not the login page
	if (!$('#pane_library').length) return;
	//TODO: consider moving init junk into a dedicated function
	
	//Init JQueryUI elements
	$('#date_start').datepicker({
		dateFormat	: 'DD, d MM, yy',
		altField	: '#datestamp_start',
		altFormat	: 'yy-mm-dd'
	});
	$('#date_end').datepicker({
		dateFormat	: 'DD, d MM, yy',
		altField	: '#datestamp_end',
		altFormat	: 'yy-mm-dd'
	});
	$('#notice').dialog({
		'autoOpen'	:	false,
		'closeText'	:	'OK',
		'modal'		:	true,
		'hide'		:	true,
		'show'		:	true,
		'width'		:	'500px',
		'buttons'	:	[
			{
				text: 'OK',
				click: function() {
					$( this ).dialog( 'close' );
				}
			}
		]
	});
	
	$('#library_toggle_filter').button( {
		'label'	:	'Show only my content'
	} ).bind('click', toggleContentVisibility ).data('visible', 'all');
	
	//Set up content [select] and [delete] buttons in content library
	$('#library_content tr').each(function(index) {
		//jquerifyElement( $(this) );
		
		var content = $(this).data();
		
		$('button[name=content_select]', $(this) ).button( {
			'icons'	:	{
				'primary'	:	'ui-icon-calendar'
			},
			'text'	:	false
		}).bind('click', content, selectContentHandler);
		
		$('button[name=content_delete]', $(this) ).button( {
			'icons'	:	{
				'primary'	:	'ui-icon-trash'
			},
			'text'	:	false
		}).bind('click', content, deleteContentHandler);
	});
	
	//Request selected schedule content via AJAX
	requestSchedule();
	
	
	//Bind events
	$('#select_player').bind('change', function() {
		requestSchedule();
	});

	$('#schedule_submit').bind('click', function() {
		insertDuration();
	});
	
	$('#logout').bind('click', function() {
		var request = {
			'action'	:	'logout'
		};
		$.post('?', request, function(response) {
			var response;
			try {
					response = $.parseJSON(response);
				} catch (e) {
					//alert(e + "\n" + response);
					return;
				}
			if (response['refresh'] == true) window.location.href = window.location.pathname;
			//location.reload(true);
		});
	}).css('cursor', 'pointer');

	$('#info_alpha').bind('click', function() {
		notice('<p>This system is currently in active development. The interface is not final, and there may be occasional software bugs or missing core features. We thank you in advance for your understanding. If you encounter an error or would like to request a feature, please visit the feedback page <a href="feedback/" target="_blank">here.</a></p>');
	}).css('cursor', 'pointer');
});



//	UI Setup
//==========================================================

function jquerifyElement(element) {
	if (!element.jquery) element = $(element);
	element.addClass('ui-jquerified ui-state-default ui-corner-all');
	element.hover( function () {element.addClass('ui-state-hover'); }, function () {element.removeClass('ui-state-hover'); });
	element.focus( function () {element.addClass('ui-state-active'); } );
	element.blur( function () {element.removeClass('ui-state-active'); } );
	element.css('cursor', 'pointer');
}



//	Fetches
//==========================================================

function requestSchedule() {
	$('#scheduler_player_name').html( 'selected' );
	var request = {
		'action'	:	'fetch',
		'fetch'		: {
			'what'	:	'durations',
			'where'	:	{
				'player_id'	:	$('#select_player').val()
			}
		}
	}
	$.post('?', request, receiveSchedule);
}
function receiveSchedule(response) {
	response = json_safeParse(response);
	
	if (response['error'] == false) {
		$('#schedule_content').html(response['html']);
		$('#schedule_content tr').each(function(index) {
			var duration = $(this).data();
			$('button[name=duration_delete]', $(this) ).button( {
				'icons'	:	{
					'primary'	:	'ui-icon-trash'
				},
				'text'	:	false
			}).bind('click', duration, deleteDurationHandler);
		});
	} else {
		notice(response['error_message']);
		$('#schedule_content').empty();
		return;
	}
}



//	Inserts
//==========================================================

function insertDuration() {
	var request = {
		'action'	:	'insert',
		'insert'	:	{
			'what'	:	'duration',
			'values':	{
				'content_id'	:	$('#scheduler_content_id').val(),
				'date_start'	:	$('#datestamp_start').val(),
				'date_end'		:	$('#datestamp_end').val(),
				'player_id'		:	$('#select_player').val(),	//TODO: show this value within the schedule form
				'duration_name'	:	$('#scheduler_duration_name').val()
			}
		}
	};
	//TODO: perform basic client side validation
	
	//Verify content is selected
	//Just grabs whatever's been set in the form; if ajax powered content deletion happens later on, make sure it unsets itself from the form
	if (request['insert']['values']['content_id'] == -1) {
		notice('You must choose content to display before scheduling an appearance / display period / recurrence');
	}
	if (request['insert']['values']['datestamp_end'] == -1 || request['insert']['values']['datestamp_start'] == -1) {
		notice('You must specify a date range for your duration');
		//TODO: Ensure START is before END
	}
	
	
	$.post('?', request, receiveInsertResults);
}
function receiveInsertResults(response) {
	response = json_safeParse(response);
	
	if (response['error'] == true) {
		notice(response['error_message']);
		return;
	}
	notice('Success! Your duration has been scheduled.');
	requestSchedule();
}



//	Updates
//==========================================================



//	Deletions
//==========================================================
//	TODO: Should Deletions actually just set a `disabled` field to prevent fetching and leave the data intact? Probably.

function ajax_deleteContent(content) {
	var request = {
		'action'	:	'delete',
		'delete'	:	{
			'what'	:	'content',
			'where'	:	content
		}
	}
	$.post('?', request, ajax_receiveDeletionResults);
}

function ajax_deleteDuration(duration) {
	var request = {
		'action'	:	'delete',
		'delete'	:	{
			'what'	:	'duration',
			'where'	:	duration
		}
	}
	$.post('?', request, ajax_receiveDeletionResults);
}

function ajax_receiveDeletionResults(response){
	console.log(response);
	
	try {
		response = $.parseJSON(response);
	} catch (e) {
		//alert(e + "\n" + response);
	}
	
	if (response['error'] == true) {
		notice(response['error_message']);
		//notice('A server error has occurred; try again in a few minutes.');
		return;
	}
	
	if (response['request']['what'] == 'content') {
		//Inform the user of success
		notice('"' + response['request']['where']['content_name'] + '" successfully deleted');
		
		//response['request']['where']['content_id']
		
		//Clear deleted content from staging area
		if (response['request']['where']['content_id'] == $('#scheduler_content_id').val() ) {
			$('#scheduler_preview_content').removeAttr('src');
			$('#scheduler_content_id').val(-1);
		}
		
		//Remove the content from the library
		$('#library_content tr').each(function(index) {
			if ( $(this).data('content_id') == response['request']['where']['content_id'] ) {
				$(this).remove();
			}
		});
	}
	
	
	//alert(response['request']['what']);
	if (response['request']['what'] == 'duration') {
		notice('"' + response['request']['where']['duration_name'] + '" successfully deleted');
	}
	
	//Refresh the schedule viewer
	$('#select_player').trigger('change');
	//alert('Reached the end of ajax_receiveDeletionResults');
	
	
}



//	UI Events
//==========================================================

function toggleContentVisibility() {
	var toggle = $('#library_toggle_filter');
	
	if (toggle.data('visible') == 'all') {
		toggle.button( 'option', 'label', 'Show all content');
		toggle.data('visible', 'mine');
		//Hide non-mines
		$('#library_content tr.content-others').hide('fade');
		return;
	} else {
		toggle.button( 'option', 'label', 'Show only my content');
		toggle.data('visible', 'all');
		//show all
		$('#library_content tr').show('fade');
		return;
	}

}



function selectContentHandler(event) {
	var content = event['data'];
	$('#scheduler_content_id').val(content['content_id']);
	$('#scheduler_preview_content').attr('src', content['content_src']);
}
function deleteContentHandler(event) {
	var content = event['data'];
	
	var buttons = [
		{
			'text'	:	'Delete',
			'click'	:	function() {
				//notice('Deleting ' + content['content_id']);
				ajax_deleteContent(content);
			}
		},
		{
			'text'	:	'Cancel',
			'click'	:	function() {
				$(this).dialog('close');
			}
		}
	];
	
	notice('Are you sure you want to delete "' + content['content_name'] + '"? This will remove all durations displaying this content!', buttons);
	//TODO: nice this up, add a thumbnail to the prompt, and maybe tweak notice so you can send buttons and callbacks, ideally with a default buttonset (yes/no) and callback fields
}

function deleteDurationHandler(event) {
	var duration = event['data'];
	
	//console.log('deleteDurationHandler called on');
	//console.log(duration);
	//return;
	
	var buttons = [
		{
			'text'	:	'Delete',
			'click'	:	function() {
				ajax_deleteDuration(duration);
			}
		},
		{
			'text'	:	'Cancel',
			'click'	:	function() {
				$(this).dialog('close');
			}
		}
	];
	
	notice('Are you sure you want to delete "' + duration['duration_name'] + '"?', buttons);
}




function notice(message, buttons) {
	//TODO: Always recenter the notice
	
	var notice = $('#notice');
	
	notice.html(message);
	
	if (buttons === undefined) {
		buttons = [
			{
				'text'	:	"Ok",
				'click'	:	function() {
					$( this ).dialog('close');
				}
			}
		]
	}
	notice.dialog('option', 'buttons', buttons);
	
	notice.dialog('open');
}


function json_safeParse(response) {
	try {
		response = $.parseJSON(response);
	} catch (e) {
		response = {
					'error'			:	true,
					'error_message'	:	'An AJAX error has occurred; please wait a moment and try your request again.'
		};
		console.log(e);
	}
	return response;
}




