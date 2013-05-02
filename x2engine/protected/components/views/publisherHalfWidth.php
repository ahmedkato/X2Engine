<?php
/*****************************************************************************************
 * X2CRM Open Source Edition is a customer relationship management program developed by
 * X2Engine, Inc. Copyright (C) 2011-2013 X2Engine Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY X2ENGINE, X2ENGINE DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact X2Engine, Inc. P.O. Box 66752, Scotts Valley,
 * California 95067, USA. or at email address contact@x2engine.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * X2Engine" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by X2Engine".
 *****************************************************************************************/
?>

<?php $users = User::getNames(); ?>
<?php $form = $this->beginWidget('CActiveForm', array('id'=>'publisher-form')); ?>

<div id="tabs">
	<ul>
		<?php if($showLogACall) { ?><li><a href="#log-a-call"><?php echo Yii::t('actions','Log A Call'); ?></a></li><?php } ?>
		<?php if($showNewAction) { ?><li><a href="#new-action"><b>+</b><?php echo Yii::t('actions','Action'); ?></a></li><?php } ?>
		<?php if($showNewComment) { ?><li><a href="#new-comment"><b>+</b><?php echo Yii::t('actions','Comment'); ?></a></li><?php } ?>
		<?php if($showNewEvent) { ?><li><a href="#new-event"><b>+</b><?php echo Yii::t('actions','Event'); ?></a></li><?php } ?>
	</ul>
	<div class="form">
        <div class="row">
            <?php echo CHtml::label('Quick Note','quickNote',array('style'=>'display:inline-block;')); ?>
            <?php echo CHtml::dropDownList('quickNote','',array_merge(array(''=>'-'),Dropdowns::getItems(117)));?>
        </div>
		<div class="row">
			<?php echo CHtml::ajaxSubmitButton(Yii::t('app','Save'),
				array('/actions/PublisherCreate'),
				array(
					'beforeSend'=>"function() {
						if($('#Actions_actionDescription').val() == '') {
							alert('". addslashes(Yii::t('actions', 'Please enter a description.')) ."');
							return false;
						} else {
							// show saving... icon
							\$('.publisher-text').animate({opacity: 0.0});
							\$('#publisher-saving-icon').animate({opacity: 1.0});
						}

						return true; // form is sane: submit!
					 }",
					 'success'=>"function() { publisherUpdates(); resetPublisher();
							\$('.publisher-text').animate({opacity: 1.0});
							\$('#publisher-saving-icon').animate({opacity: 0.0}); }",
					'type'=>'POST',
				),
				array('id'=>'save-publisher', 'class'=>'x2-button','tabindex'=>21));
			?>
			<div class="text-area-wrapper" style="margin-right:75px;">
				<?php echo $form->textArea($model,'actionDescription',array('rows'=>3, 'cols'=>40,'tabindex'=>20)); ?>
			</div>
		</div>
		<?php echo CHtml::hiddenField('SelectedTab', ''); // currently selected tab ?>

		<?php echo $form->hiddenField($model,'associationType'); ?>
		<?php echo $form->hiddenField($model,'associationId'); ?>

		<div id="action-event-panel">
			<div class="row">
				<div class="cell">
					<?php echo $form->label($model,'dueDate', array('id'=>'due-date-label')); ?>

					<?php // label for New Event ?>
					<?php echo CHtml::label(Yii::t('actions', 'Start Date'), 'Actions_dueDate', array('id'=>'start-date-label', 'style'=>'display: none;')); ?>

					<?php
					Yii::import('application.extensions.CJuiDateTimePicker.CJuiDateTimePicker');
					$this->widget('CJuiDateTimePicker',array(
						'model'=>$model, //Model object
						'attribute'=>'dueDate', //attribute name
						'mode'=>'datetime', //use "time","date" or "datetime" (default)
						'options'=>array(
							'dateFormat'=> Formatter::formatDatePicker('medium'),
							'timeFormat'=> Formatter::formatTimePicker(),
							'ampm'=> Formatter::formatAMPM(),
							'changeMonth'=>true,
							'changeYear'=>true
						), // jquery plugin options
						'language' => (Yii::app()->language == 'en')? '':Yii::app()->getLanguage(),
						'htmlOptions'=>array('onClick'=>"$('#ui-datepicker-div').css('z-index', '20');"), // fix datepicker so it's always on top
					));

					echo CHtml::label(Yii::t('actions', 'End Date'), 'Actions_completeDate', array('id'=>'end-date-label', 'style'=>'display: none;'));

					$model->dueDate = Formatter::formatDateTime(time());
					Yii::import('application.extensions.CJuiDateTimePicker.CJuiDateTimePicker');
					$this->widget('CJuiDateTimePicker', array(
						'model'=>$model, //Model object
						'attribute'=>'completeDate', //attribute name
						'mode'=>'datetime', //use "time","date" or "datetime" (default)
						'options'=>array(
							'dateFormat'=> Formatter::formatDatePicker('medium'),
							'timeFormat'=> Formatter::formatTimePicker(),
							'ampm'=> Formatter::formatAMPM(),
							'changeMonth'=>true,
							'changeYear'=>true,
						), // jquery plugin options
						'language' => (Yii::app()->language == 'en')? '':Yii::app()->getLanguage(),
						'htmlOptions'=>array(
							'onClick'=>"$('#ui-datepicker-div').css('z-index', '20');", // fix datepicker so it's always on top
							'style'=>'display: none;',
							'id'=>'end-date-input',
						),
					));
					?>
				</div>
				<div class="cell">
					<?php echo $form->label($model,'priority'); ?>
					<?php echo $form->dropDownList($model, 'priority', array(
						'1'=>Yii::t('actions','Low'),
						'2'=>Yii::t('actions','Medium'),
						'3'=>Yii::t('actions','High')));
					?>
				</div>
				<?php /* Assinged To */ ?>
				<div class="cell">

					<?php /* Users */ ?>
					<?php echo $form->label($model,'assignedTo'); ?>
					<?php echo $form->dropDownList($model,'assignedTo',$users,array('id'=>'actionsAssignedToDropdown')); ?>

					<?php /* Groups */
						echo "<br />";
						$url=$this->controller->createUrl('/groups/getGroups');
						echo "<label>".Yii::t('app','Group?')."</label>";
						echo CHtml::checkBox('group','',array(
							'id'=>'groupCheckbox',
							'ajax'=>array(
								'type'=>'POST', //request type
								'url'=>$url, //url to call.
								//Style: CController::createUrl('currentController/methodToCall')
								'update'=>'#actionsAssignedToDropdown', //selector to update
								'data'=>'js:{checked: $(this).attr("checked")=="checked"}',
								'complete'=>'function(){
									if($("#groupCheckbox").attr("checked")!="checked"){
										$("#groupCheckbox").attr("checked","checked");
										$("#Actions_visibility option[value=\'2\']").remove();
									} else {
										$("#groupCheckbox").removeAttr("checked");
										$("#Actions_visibility").append(
											$("<option></option>").val("2").html("User\'s Groups")
										);

									}
								}'
							)
						));
					?>
				</div>
				<div class="cell">
					<?php echo $form->label($model,'visibility'); ?>
					<?php $model->visibility = 1; // default visibility = public ?>
					<?php echo $form->dropDownList($model,'visibility',array(0=>Yii::t('actions','Private'), 1=>Yii::t('actions','Public'), 2=>Yii::t('actions', "User's Group"))); ?>
				</div>
			</div>
		</div>
		<div id="log-a-call">
		</div>
		<div id="new-action">
		</div>
		<div id="new-comment">
		</div>
		<div id="new-event">
		</div>
	</div>
</div>

<?php

$this->endWidget();

// save default values of fields for when the publisher is submitted and then reset
Yii::app()->clientScript->registerScript('defaultValues',"
$(function() {

	// turn on jquery taps for the publisher
	$('#tabs').tabs({
		activate: function(event, ui) { tabSelected(event, ui); },
	});
    $(document).on('change','#quickNote',function(){
        $('#Actions_actionDescription').val($(this).val());
    });

	if($('#tabs .ui-state-active').length !== 0) { // if publisher is present (prevents a javascript error if publisher is not present)
		var selected = $('#tabs .ui-state-active').attr('aria-controls');
		$('#SelectedTab').val(selected); // save the selected tab as POST data
		if(selected == 'log-a-call' || selected == 'new-comment') {
			$('#action-event-panel').css('display', 'none');
		}
	}

	$('#publisher-form select, #publisher-form input[type=text], #publisher-form textarea').each(function(i) {
		$(this).data('defaultValue', $(this).val());
	});

	$('#publisher-form input[type=checkbox]').each(function(i) {
		$(this).data('defaultValue', $(this).is(':checked'));
	});

	// highlight save button when something is edited in the publisher
	$('#publisher-form input, #publisher-form select, #publisher-form textarea').focus(function(){
		$('#save-publisher').addClass('highlight');
		// $('#publisher-form textarea').animate({'height':80},300);
		$('#publisher-form textarea').height(80);


		$(document).unbind('click.publisher').bind('click.publisher',function(e) {
			if(!$(e.target).parents().is('#publisher-form, .ui-datepicker')
				&& $('#publisher-form textarea').val()=='') {
				$('#save-publisher').removeClass('highlight');
				$('#publisher-form textarea').animate({'height':22},300);
			}
		});

	});


	// highlight save button when user starts typing in Description
	$('#Actions_actionDescription').keydown(function() {
		$('#save-publisher').addClass('highlight');
	});

	// position the saving icon for the publisher (which starts invisible)
	// var publisherLabelCenter = parseInt($('.publisher-label').css('width'), 10)/2;
	// var halfIconWidth = parseInt($('#publisher-saving-icon').css('width'), 10)/2;
	// var iconLeft = publisherLabelCenter - halfIconWidth;
	// $('#publisher-saving-icon').css('left', iconLeft + 'px');

});");


 ?>
