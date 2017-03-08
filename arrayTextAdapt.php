<?php
/**
 * arrayTextAdapt : a LimeSurvey plugin to update array text question with some dropdpown
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016 Comité Régional du Tourisme de Bretagne <http://www.tourismebretagne.com>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>

 * @license GPL v3
 * @version 1.0.2
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class arrayTextAdapt  extends \ls\pluginmanager\PluginBase {
    protected $storage = 'DbStorage';

    static protected $name = 'arrayTextAdapt';
    static protected $description = 'Use array text question type to show multiple dropdown to your users';


    public function init()
    {
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeQuestionRender');
    }

    public function beforeSurveySettings()
    {
        $event = $this->event;
        $aSettings=array();
        $oSurvey=Survey::model()->findByPk($event->get('survey'));
        $aoQuestionArrayText=Question::model()->with('groups')->findAll(array(
            'condition'=>"t.sid=:sid and t.language=:language and type=:type and parent_qid=0",
            'order'=>'group_order ASC, question_order ASC',
            'params'=>array(':sid'=>$oSurvey->sid,':language'=>$oSurvey->language,':type'=>';')
        ));
        $aDropDownType=$this->getDropdownType();
        foreach($aoQuestionArrayText as $oQuestionArrayText)
        {
            $aoSubQuestionY=Question::model()->findAll(array(
                'condition'=>"parent_qid=:parent_qid and language=:language and scale_id=1",
                'order'=>'question_order ASC',
                'params'=>array(":parent_qid"=>$oQuestionArrayText->qid,':language'=>$oSurvey->language)
            ));
            $aSettings["info-{$oQuestionArrayText->qid}"]=array(
                'type'=>'info',
                'content'=>"<p><span class='label label-primary'>{$oQuestionArrayText->title}</span>".viewHelper::flatEllipsizeText($oQuestionArrayText->question,true,80)."</p>",
                'class'=>'questiontitle'
            );
            foreach($aoSubQuestionY as $oSubQuestionY)
            {
                $aSettings["question-{$oSubQuestionY->qid}"]=array(
                    'type'=>'select',
                    'label'=>"<span class='label label-info'>{$oSubQuestionY->title}</span>".viewHelper::flatEllipsizeText($oSubQuestionY->question,true,80),
                    'options'=>$aDropDownType,
                    'selectOptions'=>array(
                        'placeholder'=> gT('None'),
                    ),
                    'htmlOptions'=>array(
                        'empty' => gT('None'),
                    ),
                    'current' => $this->getActualValue($oSubQuestionY->qid),
                );

            }
        }
        if(!empty($aSettings))
        {
            $event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => $aSettings,
            ));
            $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/admin/');
            App()->clientScript->registerCssFile($assetUrl.'/dropdownarray.css');

        }
    }
    public function newSurveySettings()
    {
        $event = $this->event;
        $aSettings=$event->get('settings');
        if(!empty($aSettings))
        {
            foreach ($aSettings as $name => $value)
            {
                /* Save as Question attribute settings : Bad hack */
                $aSetting=explode("-",$name);
                if($aSetting[0]=="question" && isset($aSetting[1]))
                {
                    $iQid=intval($aSetting[1]);
                    if($value==$this->getDefaultValue($aSetting[1]))
                    {
                        QuestionAttribute::model()->deleteAll("qid=:qid and attribute=:attribute",array(':qid'=>$iQid,":attribute"=>'arrayTextAdaptation'));
                    }
                    else
                    {
                        $oAttribute=QuestionAttribute::model()->find("qid=:qid and attribute=:attribute",array(':qid'=>$iQid,":attribute"=>'arrayTextAdaptation'));
                        if(!$oAttribute)
                        {
                            $oAttribute=new QuestionAttribute;
                            $oAttribute->qid=$iQid;
                            $oAttribute->attribute='arrayTextAdaptation';
                        }
                        $oAttribute->value=$value;
                        $oAttribute->save();
                    }
                }
                else
                {
                    $default=$event->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:null);
                    $this->set($name, $value, 'Survey', $event->get('survey'),$default);
                }
            }
        }
    }
    public function beforeQuestionRender()
    {
        $oEvent=$this->getEvent();
        $sType=$oEvent->get('type');
        if($sType==";")
        {
            $aoSubQuestionX=Question::model()->findAll(array(
                'condition'=>"parent_qid=:parent_qid and language=:language and scale_id=:scale_id",
                'params'=>array(":parent_qid"=>$oEvent->get('qid'),":language"=>App()->language,":scale_id"=>1),
                'index'=>'qid',
            ));
            $oCriteria = new CDbCriteria;
            $oCriteria->condition="attribute='arrayTextAdaptation'";
            $oCriteria->addInCondition("qid",CHtml::listData($aoSubQuestionX,'qid','qid'));
            $oExistingAttribute=QuestionAttribute::model()->findAll($oCriteria);
            if(count($oExistingAttribute))
            {
                $aSubQuestionsY=Question::model()->findAll(array(
                    'condition'=>"parent_qid=:parent_qid and language=:language and scale_id=:scale_id",
                    'params'=>array(":parent_qid"=>$oEvent->get('qid'),":language"=>App()->language,":scale_id"=>0),
                    'select'=>'title',
                ));
                Yii::setPathOfAlias('archon810', dirname(__FILE__)."/vendor/archon810/smartdomdocument/src");
                Yii::import('archon810.SmartDOMDocument');
                $dom = new \archon810\SmartDOMDocument();
                $dom->loadHTML("<!DOCTYPE html>".$oEvent->get('answers'));
                foreach($oExistingAttribute as $oAttribute)
                {
                    $oQuestionX=$aoSubQuestionX[$oAttribute->qid];
                    foreach($aSubQuestionsY as $aSubQuestionY)
                    {
                        $sAnswerId="answer{$oEvent->get('surveyId')}X{$oEvent->get('gid')}X{$oEvent->get('qid')}{$aSubQuestionY->title}_{$oQuestionX->title}";
                        $inputDom=$dom->getElementById($sAnswerId);

                        if(!is_null($inputDom))
                        {
                            switch ($oAttribute->value)
                            {
                                case 'ville':
                                    $this->setVilleAttributes($inputDom);
                                    break;
                                case 'numeric':
                                    $this->setNumericAttributes($inputDom);
                                    break;
                                case 'integer':
                                    $this->setIntegerAttributes($inputDom);
                                    break;
                                default:
                                    if(substr($oAttribute->value, 0, 5) === "label" && ctype_digit(substr($oAttribute->value, 5)))
                                    {
                                        if($sLabelHtml=$this->getLabelHtml(substr($oAttribute->value, 5),$inputDom))
                                        {
                                            $newDoc = $dom->createDocumentFragment();
                                            $newDoc->appendXML($sLabelHtml);
                                            $inputDom->parentNode->replaceChild($newDoc,$inputDom);
                                        }
                                    }
                            }
                        }
                    }
                }
                $newHtml = $dom->saveHTMLExact();
                $oEvent->set('answers',$newHtml);
            }
        }

    }

    /**
     * get the array of existing dropdown type
     */
    public function getDropdownType()
    {
        $aDropDownType=array();
        /* Test if saisieVille exist and is activated */
        if(Plugin::model()->find("name='cpVille' and active=1"))
        {
            $aDropDownType['ville']='Saisie de ville';
        }
        else
        {
            tracevar("cpVille plugin not present or not activated.");
        }
        $aDropDownType['numeric']=gT("Numerical Input");
        $aDropDownType['integer']=gT("Integer only");
        if (Permission::model()->hasGlobalPermission('labelsets','read'))
        {
            $oLabels=LabelSet::model()->findAll(array("order"=>"label_name"));
            if(count($oLabels))
            {
                $aDropDownType[gT("Labels Sets")]=array();
                foreach($oLabels as $oLabel)
                {
                    $aDropDownType[gT("Labels Sets")]['label'.$oLabel->lid]=strip_tags($oLabel->label_name);
                }
            }

        }
        return $aDropDownType;
    }
    /**
     * get the actual value for a qid
     */
    public function getActualValue($iQid)
    {
        $oAttribute=QuestionAttribute::model()->find("qid=:qid and attribute=:attribute",array(':qid'=>$iQid,":attribute"=>'arrayTextAdaptation'));
        if($oAttribute)
        {
            return $oAttribute->value;
        }
        else
        {
            return $this->getDefaultValue($iQid);
        }

    }
    /**
     * get the default value for a qid
     */
    public function getDefaultValue($iQid)
    {
        return '';
    }

    /**
     * return a saisieVille input
     */
    public function setVilleAttributes($inputDom)
    {
        if(YII_DEBUG)
        {
            Yii::app()->getClientScript()->registerScriptFile(rtrim(Yii::app()->getConfig('publicurl'),"/")."/plugins/arrayTextAdapt/assets/public/arraytextadapt.js");
            Yii::app()->getClientScript()->registerScriptFile(rtrim(Yii::app()->getConfig('publicurl'),"/")."/plugins/arrayTextAdapt/assets/public/arraytextadapt.css");
            Yii::app()->clientScript->registerCssFile(rtrim(Yii::app()->getConfig('publicurl'),"/") . '/plugins/cpVille/assets/cpville.css');
        }
        else
        {
            $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/public/');
            App()->clientScript->registerScriptFile($assetUrl.'/arraytextadapt.js');
            App()->clientScript->registerScriptFile($assetUrl.'/arraytextadapt.css');
            Yii::app()->clientScript->registerCssFile(rtrim(Yii::app()->getConfig('publicurl'),"/") . '/plugins/cpVille/assets/cpville.css'); // @todo : move it to asset too

        }
        $aOptions=array();
        $aOption['jsonurl']=$this->api->createUrl('plugins/direct', array('plugin' => "cpVille",'function' => 'auto'));
        $sScript="arrayTextAdapt=".json_encode($aOption).";\n"."cpvilleinarray();\n";
        Yii::app()->getClientScript()->registerScript("saisievillearray",$sScript,CClientScript::POS_END);

        $class=$inputDom->getAttribute('class');
        $inputDom->setAttribute('class',$class." saisievillearray");
        return ;
    }
    /**
     * return a numeric input
     */
    public function setNumericAttributes($inputDom)
    {
        $class=$inputDom->getAttribute('class');
        $inputDom->setAttribute('class',$class." numeric");
        $onkeyup=$inputDom->getAttribute('onkeyup');
        $inputDom->setAttribute('onkeyup',"fixnum_checkconditions(this.value, this.name, this.type,'onchange',0)");
    }
    /**
     * return a integer input
     */
    public function setIntegerAttributes($inputDom)
    {
        $class=$inputDom->getAttribute('class');
        $inputDom->setAttribute('class',$class." numeric integeronly");
        $onkeyup=$inputDom->getAttribute('onkeyup');
        $inputDom->setAttribute('onkeyup',"fixnum_checkconditions(this.value, this.name, this.type,'onchange',1)");
    }
    /**
     * return a saisieVille input
     */
    public function getLabelHtml($iLid,$inputDom)
    {
        /* Get this label */
        if(LabelSet::model()->find("lid=:lid",array(":lid"=>$iLid)))
        {
            $oLabelsSets=Label::model()->findAll(array("condition"=>"lid=:lid and language=:language","order"=>"sortorder","params"=>array(":lid"=>$iLid,":language"=>App()->language)));
            if(!$oLabelsSets)
            {
                $oLabelsSets=Label::model()->findAll(array("condition"=>"lid=:lid and language=:language","order"=>"sortorder","params"=>array(":lid"=>$iLid,":language"=>Survey::model()->findByPk($this->event->get('surveyId'))->language)));
            }
            if(!$oLabelsSets)
            {
                $oLabelsSets=Label::model()->findAll(array("condition"=>"lid=:lid and language=:language","order"=>"sortorder","params"=>array(":lid"=>$iLid,":language"=>App()->getConfig("defaultlanguage"))));
            }
            if(!$oLabelsSets)
            {
                $oLabelsSets=Label::model()->findAll(array("condition"=>"lid=:lid and language=:language","order"=>"sortorder","params"=>array(":lid"=>$iLid,":language"=>"en")));
            }
            if($oLabelsSets && count($oLabelsSets))
            {
                $data=CHtml::listData($oLabelsSets,'code','title');
                $htmlOptions=array ( );
                if($inputDom->getAttribute("value")=="")
                {
                    $htmlOptions['empty']=gT('Please choose...');
                }
                elseif($this->event->get('man_class')!="mandatory" && SHOW_NO_ANSWER)
                {
                    $data['']=gT('No answer');
                }
                $htmlOptions['id']='answer'.$inputDom->getAttribute("name");
                $newHtml=CHtml::dropDownList(
                    $inputDom->getAttribute("name"), $inputDom->getAttribute("value"),
                    $data,
                    $htmlOptions
                );
                return CHtml::tag("div",array('class'=>'select-item'),$newHtml);
            }
        }


    }
}
