<?php

/**
 * arrayTextAdapt : a LimeSurvey plugin to update array text question with some dropdpown
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016-2026 Denis Chenu <http://www.sondages.pro>
 * @copyright 2016-2022 Comité Régional du Tourisme de Bretagne <http://www.tourismebretagne.com>
 * @license AGPL v3
 * @version 5.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class arrayTextAdapt extends PluginBase
{
    protected $storage = 'DbStorage';

    protected static $name = 'arrayTextAdapt';
    protected static $description = 'Use array text question type to show multiple dropdown to your users';

    /** @inheritdoc, this plugin allow this public method */
    public $allowedPublicMethods = array(
        'actionSettings',
        'actionSaveSettings',
    );

    /** @inheritdoc */
    public function init()
    {
        $this->subscribe('beforeActivate');
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeQuestionRender');
    }

    /**
     * @inheritdoc
     * Show an alert if toolsSmartDomDocument is not here
     */
    public function beforeActivate()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $oToolsSmartDomDocument = Plugin::model()->find("name=:name", array(":name" => 'toolsDomDocument'));
        if (!$oToolsSmartDomDocument) {
            $this->getEvent()->set('message', gT("You must download toolsSmartDomDocument plugin"));
            $this->getEvent()->set('success', false);
        } elseif (!$oToolsSmartDomDocument->active) {
            $this->getEvent()->set('message', gT("You must activate toolsSmartDomDocument plugin"));
            $this->getEvent()->set('success', false);
        }
    }

    public function beforeSurveySettings()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->event;
        $aSettings = array();
        $oSurvey = Survey::model()->findByPk($event->get('survey'));
        if (!Yii::getPathOfAlias('toolsDomDocument')) {
            $event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => array(
                    'infoDisable' => array(
                        'type' => 'info',
                        'content' => gT("You must download and activate toolsSmartDomDocument plugin")
                    ),
                ),
            ));
            return;
        }
        $aoQuestionArrayText = Question::model()->with('group')->with('questionl10ns')->findAll(array(
            'condition' => "t.sid=:sid and type=:type and parent_qid=0 and questionl10ns.language = :language",
            'order' => 'group_order ASC, question_order ASC',
            'params' => array(':sid' => $oSurvey->sid, ':language' => $oSurvey->language, ':type' => ';')
        ));
        foreach ($aoQuestionArrayText as $oQuestionArrayText) {
            $questiontext = viewHelper::flatEllipsizeText($oQuestionArrayText->questionl10ns[$oSurvey->language]->question, true, 80);
            $settingUrl = App()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionSettings',
                    'surveyId' => $oSurvey->sid,
                    'qid' => $oQuestionArrayText->qid,
                )
            );
            $urltext = sprintf($this->gT("Settings for %s"), "<span class='label label-primary'>{$oQuestionArrayText->title}</span> {$questiontext}");
            $aSettings["info-{$oQuestionArrayText->qid}"] = array(
                'type' => 'info',
                'content' => "<a href='{$settingUrl}' class='btn btn-link'>{$urltext}</a>",
                'class' => 'questiontitle'
            );
        }
        if (!empty($aSettings)) {
            $event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => $aSettings,
            ));
        }
    }

    /**
     * Main function to replace question Setting
     * @param int $surveyId Survey id
     * @param int $qid question id
     * @return string
     */
    public function actionSettings($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, gT("This survey does not seem to exist."));
        }
        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'read')) {
            throw new CHttpException(403);
        }
        $qid = App()->getRequest()->getParam('qid', 0);
        $oQuestion = Question::model()->with('questionl10ns')->find(
            "sid = :sid and t.qid = :qid and questionl10ns.language = :language",
            [":sid" => $surveyId, ":qid" => $qid, ":language" => $oSurvey->language]
        );
        if (!$oQuestion) {
            throw new CHttpException(404, gT("This question does not seem to exist."));
        }
        $aDropDownType = $this->getDropdownType();

        $aoSubQuestionY = Question::model()->with('questionl10ns')->findAll(array(
            'condition' => "parent_qid=:parent_qid and language=:language and scale_id=1",
            'order' => 'question_order ASC',
            'params' => array(":parent_qid" => $qid,':language' => $oSurvey->language)
        ));
        $aSubqSetting = array();
        foreach ($aoSubQuestionY as $oSubQuestionY) {
            $questiontext = viewHelper::flatEllipsizeText($oSubQuestionY->questionl10ns[$oSurvey->language]->question, true, 80);
            $aSubqSetting["question-{$oSubQuestionY->qid}"] = array(
                'type' => 'select',
                'label' => "<span class='label label-info'>{$oSubQuestionY->title}</span> {$questiontext}",
                'options' => $aDropDownType,
                'htmlOptions' => array(
                    'empty' => gT('None'),
                ),
                'current' => $this->getActualValue($oSubQuestionY->qid),
            );
        }
        $aSettings[$this->gT('Choice for question')] = $aSubqSetting;
        $aData['pluginClass'] = get_class($this);
        $aData['surveyId'] = $surveyId;
        $aData['gid'] = $oQuestion->gid;
        $aData['qid'] = $oQuestion->qid;
        $aData['title'] = $this->gT("Array text adapt settings");
        $aData['warningString'] = null;
        $aData['aSettings'] = $aSettings;
        $aData['form'] = array(
            'action' => App()->createUrl('admin/pluginhelper/sa/sidebody', array('plugin' => get_class($this),'method' => 'actionSaveSettings','surveyId' => $surveyId, 'qid' => $qid)),
            'close' => App()->createUrl('questionAdministration/view', array('surveyid' => $surveyId, 'qid' => $qid))
        );
        $content = $this->renderPartial('settings', $aData, true);
        return $content;
    }

    /**
     * Main function to replace question Setting
     * @param int $surveyId Survey id
     * @param int $qid question id
     * @return string
     */
    public function actionSaveSettings($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, gT("This survey does not seem to exist."));
        }
        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveycontent', 'update')) {
            throw new CHttpException(403);
        }
        $qid = App()->getRequest()->getParam('qid', 0);
        $oQuestion = Question::model()->with('questionl10ns')->find(
            "sid = :sid and t.qid = :qid and questionl10ns.language = :language",
            [":sid" => $surveyId, ":qid" => $qid, ":language" => $oSurvey->language]
        );
        if (!$oQuestion) {
            throw new CHttpException(404, gT("This question does not seem to exist."));
        }
        if ($oQuestion->type != ";") {
            throw new CHttpException(400, gT("This question are not valid."));
        }
        $arrayTextAdaptSetting = App()->getRequest()->getPost('arrayTextAdapt');
        $aoSubQuestionY = Question::model()->with('questionl10ns')->findAll(array(
            'condition' => "parent_qid=:parent_qid and language=:language and scale_id=1",
            'order' => 'question_order ASC',
            'params' => array(":parent_qid" => $qid,':language' => $oSurvey->language)
        ));
        $haveSettings = false;
        foreach ($aoSubQuestionY as $oSubQuestionY) {
            if (!empty($arrayTextAdaptSetting['question-' . $oSubQuestionY->qid])) {
                $haveSettings = true;
                $oAttribute = QuestionAttribute::model()->find(
                    "qid=:qid and attribute=:attribute",
                    [':qid' => $oSubQuestionY->qid,":attribute" => 'arrayTextAdaptation']
                );
                if (!$oAttribute) {
                    $oAttribute = new QuestionAttribute();
                    $oAttribute->qid = $oSubQuestionY->qid;
                    $oAttribute->attribute = 'arrayTextAdaptation';
                }
                $oAttribute->value = $arrayTextAdaptSetting['question-' . $oSubQuestionY->qid];
                $oAttribute->save();
            } else {
                QuestionAttribute::model()->deleteAll(
                    "qid=:qid and attribute=:attribute",
                    array(':qid' => $oSubQuestionY->qid, ":attribute" => 'arrayTextAdaptation')
                );
            }
        }
        /* Tag the primary question with a status */
        $oAttribute = QuestionAttribute::model()->find(
            "qid=:qid and attribute=:attribute",
            [':qid' => $oSubQuestionY->qid,":attribute" => 'haveArrayTextAdapt']
        );
        if (!$oAttribute) {
            $oAttribute = new QuestionAttribute();
            $oAttribute->qid = $oSubQuestionY->qid;
            $oAttribute->attribute = 'haveArrayTextAdapt';
        }
        if ($haveSettings) {
            $oAttribute->value = '1';
        } else {
            $oAttribute->value = '-1';
        }
        $oAttribute->save();
        $redirectUrl = Yii::app()->createUrl('admin/pluginhelper/sa/sidebody', array('plugin' => get_class($this),'method' => 'actionSettings','surveyId' => $surveyId, 'qid' => $qid));
        Yii::app()->getRequest()->redirect($redirectUrl, true, 303);
    }
    /**
    * Add the readonly attribute
    */
    public function addScriptAttribute()
    {
    }

    /* Just do nothing */
    public function newSurveySettings()
    {
        return;
    }
    public function beforeQuestionRender()
    {
        if (!Yii::getPathOfAlias('toolsDomDocument')) {
            return;
        }
        $oEvent = $this->getEvent();
        $sType = $oEvent->get('type');
        if ($sType == ";") {
            $aoSubQuestionX = Question::model()->findAll(array(
                'condition' => "parent_qid=:parent_qid and scale_id=:scale_id",
                'params' => array(":parent_qid" => $oEvent->get('qid'), ":scale_id" => 1),
                'select' => 'qid, title',
                'index' => 'qid',
            ));
            $oCriteria = new CDbCriteria();
            $oCriteria->condition = "attribute = 'arrayTextAdaptation'";
            $oCriteria->addInCondition("qid", CHtml::listData($aoSubQuestionX, 'qid', 'qid'));
            $oExistingAttribute = QuestionAttribute::model()->resetScope(true)->findAll($oCriteria);
            if (count($oExistingAttribute)) {
                $aSubQuestionsY = Question::model()->findAll(array(
                    'condition' => "parent_qid=:parent_qid and scale_id=:scale_id",
                    'params' => array(":parent_qid" => $oEvent->get('qid'),":scale_id" => 0),
                    'select' => 'qid, title',
                ));
                $oQuestion = Question::model()->findByPk($oEvent->get('qid'));
                $dom = new \toolsDomDocument\SmartDOMDocument();
                $dom->loadHTML("<!DOCTYPE html>" . $oEvent->get('answers'));
                foreach ($oExistingAttribute as $oAttribute) {
                    $oQuestionX = $aoSubQuestionX[$oAttribute->qid];
                    foreach ($aSubQuestionsY as $aSubQuestionY) {
                        $sAnswerId = "answerQ{$oEvent->get('qid')}_S{$aSubQuestionY->qid}_S{$oQuestionX->qid}";
                        if (intval(App()->getConfig('versionnumber')) < 7) {
                            $sAnswerId = "answer{$oEvent->get('surveyId')}X{$oEvent->get('gid')}X{$oEvent->get('qid')}{$aSubQuestionY->title}_{$oQuestionX->title}";
                        }
                        $inputDom = $dom->getElementById($sAnswerId);
                        if (!is_null($inputDom)) {
                            switch ($oAttribute->value) {
                                case 'numeric':
                                    $this->setNumericAttributes($inputDom);
                                    break;
                                case 'integer':
                                    $this->setIntegerAttributes($inputDom);
                                    break;
                                default:
                                    if (substr($oAttribute->value, 0, 5) === "label" && ctype_digit(substr($oAttribute->value, 5))) {
                                        if ($sLabelHtml = $this->getLabelHtml(substr($oAttribute->value, 5), $inputDom, $oQuestion)) {
                                            $newDoc = $dom->createDocumentFragment();
                                            $newDoc->appendXML($sLabelHtml);
                                            $inputDom->parentNode->replaceChild($newDoc, $inputDom);
                                        }
                                    }
                            }
                        }
                    }
                }
                $newHtml = $dom->saveHTMLExact();
                $oEvent->set('answers', $newHtml);
            }
        }
    }

    /**
     * get the array of existing dropdown type
     */
    private function getDropdownType()
    {
        $aDropDownType = array();
        if (Permission::model()->hasGlobalPermission('labelsets', 'read')) {
            $oLabels = LabelSet::model()->findAll(array("order" => "label_name"));
            if (count($oLabels)) {
                $aDropDownType[gT("Labels Sets")] = array();
                foreach ($oLabels as $oLabel) {
                    $aDropDownType[gT("Labels Sets")]['label' . $oLabel->lid] = strip_tags($oLabel->label_name);
                }
            }
        }
        return $aDropDownType;
    }
    /**
     * get the actual value for a qid
     */
    private function getActualValue($iQid)
    {
        $oAttribute = QuestionAttribute::model()->find("qid=:qid and attribute=:attribute", array(':qid' => $iQid,":attribute" => 'arrayTextAdaptation'));
        if ($oAttribute) {
            return $oAttribute->value;
        } else {
            return $this->getDefaultValue($iQid);
        }
    }
    /**
     * get the default value for a qid
     */
    private function getDefaultValue($iQid)
    {
        return '';
    }

    /**
     * Return a saisieVille input
     * @deprecated 5.0.0, remove unfunctional system
     */
    private function setVilleAttributes($inputDom)
    {
        if (!Yii::getPathOfAlias('cpVille')) {
            return;
        }
        $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets-legacy/');
        if (array_key_exists('devbridge-autocomplete', Yii::app()->getClientScript()->packages)) {
            Yii::app()->getClientScript()->registerPackage('devbridge-autocomplete');
            $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/');
        }
        App()->clientScript->registerPackage('cpVille');
        App()->clientScript->registerScriptFile($assetUrl . '/arraytextadapt.js');
        App()->clientScript->registerScriptFile($assetUrl . '/arraytextadapt.css');

        $aOptions = array();
        $aOption['jsonurl'] = Yii::app()->createUrl('plugins/direct', array('plugin' => "cpVille",'function' => 'auto'));
        $sScript = "arrayTextAdapt=" . json_encode($aOption) . ";\n" . "cpvilleinarray();\n";
        Yii::app()->getClientScript()->registerScript("saisievillearray", $sScript, CClientScript::POS_END);
        $class = $inputDom->getAttribute('class');
        $inputDom->setAttribute('class', $class . " saisievillearray");
        return ;
    }
    /**
     * Return a numeric input
     * @deprecated 5.0.0, remove unfunctional system
     */
    private function setNumericAttributes($inputDom)
    {
        $class = $inputDom->getAttribute('class');
        $inputDom->setAttribute('class', $class . " numeric");
        $onkeyup = $inputDom->setAttribute('data-number', 1);
        $onkeyup = $inputDom->setAttribute('data-integer', 0);
    }

    /**
     * Return a integer input
     * @deprecated 5.0.0, remove unfunctional system
     */
    private function setIntegerAttributes($inputDom)
    {
        $class = $inputDom->getAttribute('class');
        $inputDom->setAttribute('class', $class . " numeric integeronly");
        $onkeyup = $inputDom->setAttribute('data-number', 1);
        $onkeyup = $inputDom->setAttribute('data-integer', 1);
    }

    /**
     * return a dropdown input by label set
     * @var integer $iLid label id
     * @var \DOMElement $inputDom the input
     * @var \Question $oQuestion
     * @retuirn null|string the html of the dropdown
     */
    private function getLabelHtml($iLid, $inputDom, $oQuestion)
    {
        /* static */
        static $alabelsHtml = [];
        /* Get this label */
        if (!isset($alabelsHtml[$iLid])) {
            if ($LabelSet = LabelSet::model()->findByPk($iLid)) {
                /* Check the language */
                $labelSetLanguages = explode(" ", $LabelSet->languages);
                $language = App()->getConfig("defaultlanguage");
                if (in_array(App()->language, $labelSetLanguages)) {
                    $language = App()->language;
                } elseif (in_array(Survey::model()->findByPk($oQuestion->sid)->language, $labelSetLanguages)) {
                    $language = Survey::model()->findByPk($oQuestion->sid)->language;
                } elseif (in_array(App()->getConfig("defaultlanguage"), $labelSetLanguages)) {
                    $language = App()->getConfig("defaultlanguage");
                } elseif (in_array('en', $labelSetLanguages)) {
                    $language = 'en';
                } else {
                    // Add a alert on display if have permission ?
                    $alabelsHtml[$iLid] = [];
                    return null;
                }
                $oLabels = Label::model()->with('labell10ns')->findAll([
                    "condition" => "t.lid = :lid and labell10ns.language=:language",
                    "order" => "sortorder",
                    "params" => array(":lid" => $iLid,":language" => $language)
                ]);
                if ($oLabels && count($oLabels)) {
                    $alabelsHtml[$iLid] = Chtml::listData($oLabels, 'code', function ($oLabel) use ($language) {
                        return $oLabel->labell10ns[$language]['title'];
                    });
                } else {
                    $alabelsHtml[$iLid] = [];
                }
            } else {
                $alabelsHtml[$iLid] = [];
            }
        }
        if (empty($alabelsHtml[$iLid])) {
            return null;
        }
        $data = $alabelsHtml[$iLid];
        $htmlOptions = array ();
        if ($inputDom->getAttribute("value") == "") {
            $htmlOptions['empty'] = gT('Please choose...');
        } elseif ($oQuestion->mandatory == "N" && Survey::model()->findByPk($oQuestion->sid)->getIsShowNoAnswer()) {
            $data[''] = gT('No answer');
        }
        $htmlOptions['id'] = 'answer' . $inputDom->getAttribute("name");
        $htmlOptions['class'] = 'form-control';
        $newHtml = CHtml::dropDownList(
            $inputDom->getAttribute("name"),
            $inputDom->getAttribute("value"),
            $data,
            $htmlOptions
        );
        return CHtml::tag("div", array('class' => 'select-item'), $newHtml);
    }
}
