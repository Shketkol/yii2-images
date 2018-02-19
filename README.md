Image extension
===============
Image extension 

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist shketkol/yii2-images "*"
```

or add

```
"shketkol/yii2-images": "*"
```

to the require section of your `composer.json` file.


Usage
-----
1. run migrate 
```
 php yii migrate/up --migrationPath=@vendor/shketkol/yii2-images/migrations
```
2. setup modules
```
'modules' => [
        'yii2images' => [
            'class' => 'shketkol\images\src\Module',
            'imagesStorePath' => $_SERVER['DOCUMENT_ROOT'].'/uploads/store', //path to origin images
            'imagesCachePath' => $_SERVER['DOCUMENT_ROOT'].'/uploads/cache', //path to resized copies
            'imagesTempPath' => $_SERVER['DOCUMENT_ROOT'].'/uploads/file', //path to resized copies
            'pathImageOrigin' => '/uploads/store',
            'graphicsLibrary' => 'Imagick', //but really its better to use 'Imagick'
            'userId' => 1 // userId save previews false user login id
        ],
    ]

```
3. attach behaviour to your model (be sure that your model has "id" property)
```
public function behaviors()
    {
        return [
            'file' => [
                'class' => 'shketkol\images\src\behaviors\ImageBehave',
            ]
        ];
    }
```
4.render view widget
```
<?=$form->field($model, 'file[]')->widget(FileInput::classname(),
    [
        'options' => ['accept' => 'image/*',
            'multiple' => true,
        ],
        'pluginOptions' => [
            'allowedExtensions' => ['jpg', 'gif', 'png'],
            'initialPreview' => $model->getPreviews(10),
            'initialPreviewAsData' => true,
            'initialPreviewConfig' => $model->getPreviewsConfig(10),
            'overwriteInitial' => false,
            'previewFileType' => 'image',
            'showCaption' => false,
            'showCancel' => false,
            'showUpload' => false,
            'browseClass' => 'btn btn-default btn-sm',
            'browseLabel' => 'Добавить картинку',
            'browseIcon' => '<i class="glyphicon glyphicon-picture"></i>',
            'removeClass' => 'btn btn-danger btn-sm',
            'removeLabel' => ' Удалить',
            'removeIcon' => '<i class="fa fa-trash"></i>',
            'append' => true,
            'maxFileCount' =>10,
            'validateInitialCount' => true,
            'minImageHeight' => 120,
            'minImageWidth' => 120,

            'uploadUrl' => '/yii2images/upload/upload',
            'uploadAsync' => true,
            'uploadExtraData' => [
                'model' => $model::className(),
                'id' => ($model->isNewRecord) ? '' : $model->id,
                'name' => '10',
                'field' => $field,
                'max' => 50
            ],

        ],
        'pluginEvents' => [
            'filebatchselected' => 'function(event, data) {
                    jQuery("#catalogitems-image").fileinput("upload");
                }',
            'filesorted' => 'function(event, params) {
                    
                   for(var i in params.stack){
                        var index = parseInt(i) + parseInt(1);
                        $.ajax({
                            type: "GET",
                            url: "/yii2images/upload/change-position",
                            data: "id="+params.stack[i].key+"&position="+index,
                            success: function(data) {
                                                    
                            }
                    });
                   }
                    
                    
                }',

        ]
    ]) ?>

```
4.in controller action create
```
$model->setImages($name);
```
