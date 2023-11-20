<!-- /reloadAnyReponse/views/settings.php @version 5.9.2-beta2 -->
<div class="row">
    <div class="col-lg-12 content-right">
      <?php echo CHtml::beginForm($form['action']);?>
      <h3 class="clearfix"><?php echo $title ?>
        <div class='pull-right hidden-xs'>
          <?php
          if(Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
            echo " ";
          }
          echo CHtml::link(
            gT('Close'),
            $form['close'],
            array('class'=>'btn btn-danger')
          );
          ?>
        </div>
      </h3>
        <?php if($warningString) {
            echo CHtml::tag("p",array('class'=>'alert alert-warning'),$warningString);
        } ?>
        <?php tracevar($aSettings) ?>
        <?php foreach($aSettings as $legend => $settings) {
          $this->widget('ext.SettingsWidget.SettingsWidget', array(
                'title' => $legend,
                'form' => false,
                'prefix' => $pluginClass,
                'formHtmlOptions'=>array(
                    'class'=>'form-core',
                ),
                'labelWidth'=>6,
                'controlWidth'=>6,
                'settings' => $settings,
            ));
        } ?>
        <div class='row'>
          <div class='col-md-offset-6 submit-buttons'>
            <?php
              if(Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
                echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
                echo " ";
              }
              echo CHtml::link(
                gT('Close'),
                $form['close'],
                array('class'=>'btn btn-danger')
              );
            ?>
          </div>
        </div>
        <?php echo CHtml::endForm();?>
    </div>
</div>
