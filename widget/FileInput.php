<?php
namespace shketkol\images\widget;

use kartik\base\InputWidget;
use kartik\base\TranslationTrait;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class FileInput extends InputWidget
{
    use TranslationTrait;

    /**
     * @var boolean whether to resize images on client side
     */
    public $resizeImages = false;

    /**
     * @var boolean whether to load sortable plugin to rearrange initial preview images on client side
     */
    public $sortThumbs = true;

    /**
     * @var boolean whether to load dom purify plugin to purify HTML content in purfiy
     */
    public $purifyHtml = true;

    /**
     * @var boolean whether to show 'plugin unsupported' message for IE browser versions 9 & below
     */
    public $showMessage = true;

    /*
     * @var array HTML attributes for the container for the warning
     * message for browsers running IE9 and below.
     */
    public $messageOptions = ['class' => 'alert alert-warning'];

    /**
     * @var array the internalization configuration for this widget
     */
    public $i18n = [];

    /**
     * @inheritdoc
     */
    public $pluginName = 'fileinput';

    /**
     * @var array the list of inbuilt themes
     */
    private static $_themes = ['fa', 'gly', 'explorer', 'explorer-fa'];

    /**
     * @var array initialize the FileInput widget
     */
    public function init()
    {
        parent::init();
        $this->_msgCat = 'fileinput';
        $this->initI18N(__DIR__);
        $this->initLanguage();
        $this->registerAssets();
        if ($this->pluginLoading) {
            Html::addCssClass($this->options, 'file-loading');
        }
        if (isset($this->field) && isset($this->field->form) && !isset($this->field->form->options['enctype'])) {
            $this->field->form->options['enctype'] = 'multipart/form-data';
        }
        $input = $this->getInput('fileInput');
        $script = 'document.getElementById("' . $this->options['id'] . '").className.replace(/\bfile-loading\b/,"");';
        if ($this->showMessage) {
            $validation = ArrayHelper::getValue($this->pluginOptions, 'showPreview', true) ?
                Yii::t('fileinput', 'file preview and multiple file upload') :
                Yii::t('fileinput', 'multiple file upload');
            $message = '<strong>' . Yii::t('fileinput', 'Note:') . '</strong> ' .
                Yii::t(
                    'fileinput',
                    'Your browser does not support {validation}. Try an alternative or more recent browser to access these features.',
                    ['validation' => $validation]
                );
            $content = Html::tag('div', $message, $this->messageOptions) . "<script>{$script};</script>";
            $input .= "\n" . $this->validateIE($content);
        }
        echo $input;
    }

    /**
     * Validates and returns content based on IE browser version validation
     *
     * @param string $content
     * @param string $validation
     *
     * @return string
     */
    protected function validateIE($content, $validation = 'lt IE 10')
    {
        return "<!--[if {$validation}]><br>{$content}<![endif]-->";
    }

    /**
     * Registers the asset bundle and locale
     */
    public function registerAssetBundle()
    {
        $view = $this->getView();
        if ($this->resizeImages) {
            PiExifAsset::register($view);
            $this->pluginOptions['resizeImage'] = true;
        }
        $theme = ArrayHelper::getValue($this->pluginOptions, 'theme');
        if (!empty($theme) && in_array($theme, self::$_themes)) {
            FileInputThemeAsset::register($view)->addTheme($theme);
        }
        if ($this->sortThumbs) {
            SortableAsset::register($view);
        }
        if ($this->purifyHtml) {
            DomPurifyAsset::register($view);
            $this->pluginOptions['purifyHtml'] = true;
        }
        FileInputAsset::register($view)->addLanguage($this->language, '', 'js/locales');
    }

    /**
     * Registers the needed assets
     */
    public function registerAssets()
    {
        $this->registerAssetBundle();
        $this->registerPlugin($this->pluginName);
    }
}